$('#login_btn').click(function () {

    const username = $('#username').val().trim();

    if (username === '') {
        alert('Δώσε όνομα χρήστη');
        return;
    }

    // ΠΡΟΣΩΡΙΝΑ: πάντα P1 (μετά το κάνουμε έξυπνο)
    const player = 'P1';

    $.ajax({
        url: 'api/player.php/' + player,
        method: 'PUT',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({ username: username }),
        success: function (data) {
            console.log(data);

            localStorage.setItem('token', data[0].token);
            localStorage.setItem('player', data[0].player);

            $('#game_info').html(
                'Παίκτης: ' + data[0].player +
                '<br>Όνομα: ' + data[0].username
            );
        },
        error: function (xhr) {
            alert(xhr.responseText);
        }
    });

});
