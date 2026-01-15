$(function () {
    $("#login_btn").click(login_to_game);
});

/* ================= LOGIN ================= */
function login_to_game() {
    let username = $('#username').val().trim();
    let player   = $('#player').val();

    if (username === '') {
        alert('Δώσε όνομα χρήστη');
        return;
    }

   $.ajax({
    url: "/~iee2021039/ADISE25_2021039/lib/players.php",
    method: "POST",
    contentType: "application/json",
    dataType: "json",
    data: JSON.stringify({
        username: username,
        player: player
    }),
    success: function (data) {
        console.log('LOGIN:', data);

        localStorage.setItem('token', data.token);
        localStorage.setItem('player', data.player);

        $('#game_initializer').hide();
        alert('Συνδέθηκες ως ' + data.player);

        load_status();
        window.statusInterval = setInterval(load_status, 2000);
    },
    error: function (xhr) {
        alert(xhr.responseText);
    }
});

}

function load_xeria() {
    $.ajax({
        url: "/~iee2021039/ADISE25_2021039/lib/xeri_status.php",
        method: "GET",
        dataType: "json",
        success: function(data) {
            let me = localStorage.getItem('player');
            $('#xeri_normal').text(data[me].normal);
            $('#xeri_jack').text(data[me].jack);
        }
    });
}
let gameEnded = false;

/* ================= STATUS ================= */
function load_status() {
    $.ajax({
       url: "/~iee2021039/ADISE25_2021039/lib/game_status_api.php",
        method: 'GET',
        dataType: 'json',
        success: function (data) {
            if (!data || data.length === 0) return;

            let st = data[0];
            console.log('STATUS:', st);
            $('#game_info').html(
                'Κατάσταση παιχνιδιού: <b>' + st.status + '</b><br>' +
                'Σειρά: ' + (st.turn ?? '-')
            );

            // 🔥 ΑΥΤΟ ΕΛΕΙΠΕ
            if (st.status === 'playing') {
                load_game_state();
            }
       if (st.status === 'aborted') {

    // ⛔ σταματάμε polling ΜΙΑ ΦΟΡΑ
    
        clearInterval(window.statusInterval);
      

    let me = localStorage.getItem('player');

    // 🏆 ΝΙΚΗΤΗΣ
    if (st.result === me) {

        
        
            if (confirm(
                '🏆 Νίκησες!\n' +
                'Ο αντίπαλος άργησε να παίξει.\n\n' +
                'Πάτα ΟΚ για επιστροφή στην αρχή'
            )) {
                end_game();
            }
       

    } else {

        // ❌ ΗΤΤΗΜΕΝΟΣ → απλό reset ΧΩΡΙΣ confirm
        
            end_game();
        
    }
}



if (st.status === 'game_end') {

    clearInterval(window.statusInterval);
    load_game_state(); // τελευταία ενημέρωση

    let me = localStorage.getItem('player');

    let msg =
        (st.result === me)
            ? '🎉 Νίκησες!'
            : (st.result === 'draw')
                ? '🤝 Ισοπαλία'
                : '❌ Έχασες';

    let scoreText =
        `Σκορ:\n` +
        `P1: ${st.score_p1} πόντοι\n` +
        `P2: ${st.score_p2} πόντοι`;

    $('#game_end_msg').text(msg + '\n\n' + scoreText);
    $('#game_end_box').show();

    $('#game_end_ok').off().on('click', function () {
        end_game();
    });
}







        }
    });
}
function end_game() {
    $.ajax({
        url: "/~iee2021039/ADISE25_2021039/lib/end_game.php",
        method: "POST",
        complete: function () {
            localStorage.clear();
            location.reload(true); // 🔥 πλήρες refresh
        }
    });
}


/* ================= GAME STATE ================= */
function load_game_state() {
    $.ajax({
        url: "/~iee2021039/ADISE25_2021039/lib/game_state.php",
        method: 'GET',
        headers: {
            'X-TOKEN': localStorage.getItem('token')
        },
        dataType: 'json',
       success: function (data) {
    console.log('GAME STATE RAW:', data);

    if (data.error) {
        console.error('GAME STATE ERROR:', data.error);
        return;
    }

    if (data.status !== 'playing') return;

    render_my_hand(data.my_hand);
    render_table(data.table_cards);
    update_table_count(data.table_count);

    $('#opponent_cards').text(data.opponent_cards);
    load_xeria();
},

        error: function (xhr) {
            console.error(xhr.responseText);
        }
    });
}
function update_table_count(count) {
    $('#table_count').html(
        `<b>Φύλλα στο τραπέζι:</b> ${count}`
    );
}

/* ================= RENDER ================= */
function render_my_hand(cards) {
    let html = '<h3>Τα φύλλα μου</h3>';

    cards.forEach(c => {
        let code = c.value + c.suit; // πχ AH
        html += `
          <span class="card my-card"
                data-card-id="${c.card_id}"
                data-card-code="${code}">
            ${code}
          </span>
        `;
    });

    $('#my_hand').html(html);

    // click handler
    $('.my-card').click(on_card_click);
}
function on_card_click() {
    let cardId   = $(this).data('card-id');
    let cardCode = $(this).data('card-code');

    if (!confirm(`Θες σίγουρα να παίξεις το ${cardCode};`)) {
        return;
    }

    play_card(cardId);
}


function render_table(cards) {
    let html = '<h3>Τραπέζι</h3>';

    if (cards.length === 0) {
        html += '<div>(Άδειο)</div>';
    } else {
        let c = cards[0];
        html += `<span class="card table-card">${c.value}${c.suit}</span>`;
    }

    $('#table_cards').html(html);
}

function play_card(cardId) {
    $.ajax({
        url: "/~iee2021039/ADISE25_2021039/lib/play_card.php",
        method: 'POST',
        headers: {
            'X-TOKEN': localStorage.getItem('token')
        },
        contentType: 'application/json',
        data: JSON.stringify({ card_id: cardId }),
        success: function (data) {

    // 🔥 αν τελείωσε ο γύρος
    if (data.round_end) {
        load_status();   // θα δει dealing
        return;
    }

    // κανονικό refresh
    load_game_state();
}
,
        error: function (xhr) {
            alert(xhr.responseText);
        }
    });
}


