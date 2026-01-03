$(function() {
    $("#login_btn").click(login_to_game);
});

function login_to_game() {
    var username = $('#username').val().trim();
    var player   = $('#player').val();

    if (username === '') {
        alert('Δώσε όνομα χρήστη');
        return;
    }

    $.ajax({
        url: "/ADISE25_2021039/lib/players.php/player/" + player,
        method: 'PUT',
        contentType: 'application/json',
        dataType: 'json',  // <--- σημαντικό για να γίνει parsed το JSON
        data: JSON.stringify({ username: username }),
        success: function(data) {
            console.log(data);
            alert('Συνδέθηκες ως ' + data[0].player);
            load_status();
        },
        error: function(xhr) {
            console.error('Status:', xhr.status);
            console.error(xhr.responseText);
            alert('Error: ' + xhr.responseText);
        }
    });
}
function load_status() {
    $.ajax({
        url: "/ADISE25_2021039/lib/game_status.php",
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log('STATUS:', data);

            if (data.length > 0) {
                $('#game_info').html(
                    'Κατάσταση παιχνιδιού: <b>' + data[0].status + '</b><br>' +
                    'Σειρά: ' + (data[0].turn ?? '-')
                );
            }
        },
        error: function(xhr) {
            console.error(xhr.responseText);
        }
    });
}

