

$( function() {

	

	
  	$("#login_btn").click(login_to_game);
    
}
);
function login_to_game()  {

    var username = $('#username').val().trim();
    var player   = $('#player').val();

    if (username === '') {
        alert('Δώσε όνομα χρήστη');
        return;
    }

    $.ajax({
    url: '/ADISE25_2021039/players.php/player/' + player,
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

}
