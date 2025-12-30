
$( function() {

	

	
  	$('#login_btn').click(login_to_game);
    
}
);
function login_to_game()  {

    const username = $('#username').val().trim();
    const player   = $('#player').val();

    if (username === '') {
        alert('Δώσε όνομα χρήστη');
        return;
    }

    $.ajax({
        url: 'api/players.php/player/' + player,
        method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({ username: username }),
        success: function (data) {
            console.log(data);

            localStorage.setItem('token', data[0].token);
            localStorage.setItem('player', data[0].player);

            alert('Συνδέθηκες ως ' + data[0].player);
        },
        error: function (xhr) {
            alert(xhr.responseText);
        }
    });
};
