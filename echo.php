<?php
header('Content-Type: application/json;charset=UTF-8');
$rawbody = file_get_contents('php://input');
$body = json_decode($rawbody, TRUE);

function sendCmd($cmd) {
	$ret = "";
	$s=stream_socket_client('tcp://127.0.0.1:44147',$errnum,$errstr,5) ;
	if($s!==FALSE) {
		fwrite($s, $cmd);
		while(!feof($s)) {
			$ret .= fgets($s, 128);
		}
		stream_socket_shutdown($s,STREAM_SHUT_RDWR);
		fclose($s);
	} else {
		http_response_code(500);
		$ret .= '{"status":500,"err":"Failed to connect to thermostat server: ' . trim($errstr) . '"}';
	}
	return json_decode($ret, TRUE);
}

$speak = null;
$intent = $body['request']['intent']['name'];
$slots = $body['request']['intent']['slots'];

if($intent == 'CurrentTemperatureIntent') {
	$speak = 'The current temperature is ' . (sendCmd('status')['avgTmp']) . ' degrees.';
}

else {
    $speak = 'Sorry, I am unsure of your intent.';
}

// Print result back!
$outputSpeech = "";
if($speak != null and strlen($speak) > 0) {
    $outputSpeech = '"outputSpeech": {"type": "PlainText","text": "' . $speak . '"}';
}

echo '{"version": "1.0","response": {' . $outputSpeech . '},"sessionAttributes": {}}';
?>