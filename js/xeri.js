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
        url: "/ADISE25_2021039/lib/players.php/player/" + player,
        method: 'PUT',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({ username: username }),
        success: function (data) {
            console.log('LOGIN:', data);

            // αποθήκευση token
            localStorage.setItem('token', data[0].token);
            localStorage.setItem('player', data[0].player);

            $('#game_initializer').hide();
            alert('Συνδέθηκες ως ' + data[0].player);

            load_status();
            setInterval(load_status, 2000);
        },
        error: function (xhr) {
            alert(xhr.responseText);
        }
    });
}

/* ================= STATUS ================= */
function load_status() {
    $.ajax({
        url: "/ADISE25_2021039/lib/game_status.php",
        method: 'GET',
        dataType: 'json',
        success: function (data) {
            if (!data || data.length === 0) return;

            let st = data[0];

            $('#game_info').html(
                'Κατάσταση παιχνιδιού: <b>' + st.status + '</b><br>' +
                'Σειρά: ' + (st.turn ?? '-')
            );

            // 🔥 ΑΥΤΟ ΕΛΕΙΠΕ
            if (st.status === 'playing') {
                load_game_state();
            }
        }
    });
}

/* ================= GAME STATE ================= */
function load_game_state() {
    $.ajax({
        url: "/ADISE25_2021039/lib/game_state.php",
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
    $('#opponent_cards').text(data.opponent_cards);
},

        error: function (xhr) {
            console.error(xhr.responseText);
        }
    });
}

/* ================= RENDER ================= */
function render_my_hand(cards) {
    let html = '<h3>Τα φύλλα μου</h3>';
    cards.forEach(c => {
        html += `<span class="card">${c.value} ${c.suit}</span> `;
    });
    $('#my_hand').html(html);
}

function render_table(cards) {
    let html = '<h3>Φύλλα κάτω</h3>';
    cards.forEach(c => {
        html += `<span class="card">${c.value} ${c.suit}</span> `;
    });
    $('#table_cards').html(html);
}

