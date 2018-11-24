<?php
header('Content-Type: application/json');

$s=stream_socket_client('tcp://127.0.0.1:44147',$errnum,$errstr,5) ;
if($s!==FALSE) {
	fwrite($s, $_GET['cmd']);
    while(!feof($s)) {
        echo fgets($s, 128);
    }
	stream_socket_shutdown($s,STREAM_SHUT_RDWR);
	sleep(1);
    fclose($s);
} else {
	http_response_code(500);
	echo '{"status":500,"err":"Failed to connect to thermostat server: ' . trim($errstr) . '"}';
}
?>