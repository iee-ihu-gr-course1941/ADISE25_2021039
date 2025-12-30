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
            alert('OK, affected rows: ' + data.affected_rows);
        },
        error: function(xhr) {
            console.error('Status:', xhr.status);
            console.error(xhr.responseText);
            alert('Error: ' + xhr.responseText);
        }
    });
}
