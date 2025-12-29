$('#login_btn').click(function () {

    const username = $('#username').val().trim();

    if (username === '') {
        alert('Δώσε όνομα χρήστη');
        return;
    }

    $.ajax({
        url: 'api/player.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ username: username }),
        success: function (data) {
            $('#result').text(JSON.stringify(data, null, 2));

            // αποθήκευση token (για επόμενα calls)
            localStorage.setItem('token', data.token);
            localStorage.setItem('player', data.player);

            alert('Συνδέθηκες ως ' + data.player);
        },
        error: function (xhr) {
            $('#result').text(xhr.responseText);
        }
    });

});
