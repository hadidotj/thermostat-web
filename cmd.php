<?php
header('Content-Type: application/json');

$s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if($s !== FALSE && socket_connect($s, 'localhost', 44147) !== FALSE) {
	$in = $_GET['cmd'];
	socket_write($s, $in, strlen($in));
	
	while(($out = socket_read($s, 2048)) !== FALSE) {
		echo $out;
	}
	
	socket_close($s);
} else {
	http_response_code(500);
	echo '{"status":500,"err":"Failed to connect to thermostat server: ' . trim(socket_strerror(socket_last_error($s))) . '"}';
}
?>