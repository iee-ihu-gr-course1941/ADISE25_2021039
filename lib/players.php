<?php
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'],'/'));

$resource = $request[0] ?? null;
$param    = $request[1] ?? null;

$input = json_decode(file_get_contents("php://input"), true);

if ($resource === 'player') {
    handle_user($method, $param, $input);
}



function handle_user($method, $b,$input) {
	if($method=='PUT') { 
        set_user($b,$input);}}
function set_user($b,$input) {
    if(!isset($input['username']) || $input['username']=='') {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"No username given."]);
		exit;}
	$username=$input['username'];
	global $mysqli;
	$sql = 'select count(*) as c 
	        from players 
			where player=? 
			and username is not null
			and last_action > (NOW() - INTERVAL 5 MINUTE)';
	$st = $mysqli->prepare($sql);
	$st->bind_param('s',$b);
	$st->execute();
	$res = $st->get_result();
	$r = $res->fetch_all(MYSQLI_ASSOC);
	if($r[0]['c']>0) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"Player $b is already set. Please select another color."]);
		exit;}
	$sql = 'update players 
	        set username=?, 
	        token=md5(CONCAT( ?, NOW()))
		    where player=?';
	$st2 = $mysqli->prepare($sql);
	$st2->bind_param('sss',$username,$username,$b);
	$st2->execute();
	$sql = 'select * from players where player=?';
	$st = $mysqli->prepare($sql);
	$st->bind_param('s',$b);
	$st->execute();
	$res = $st->get_result();
	header('Content-type: application/json');
	print json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

