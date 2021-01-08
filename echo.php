<?php
error_reporting(E_ERROR);
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
    $resolution = $slots['location']['resolutions']['resolutionsPerAuthority'][0]['values'][0]['value'];
    $location = $resolution['id'];
    $status = sendCmd('status');
    if($location != NULL) {
        $tmp = $status['rooms'][$location][0];
        $name = $resolution['name'];
        $name = ($name != NULL) ? $name : $slots['location']['value'];
        if($tmp != NULL) {
            $speak = 'The ' . $name . ' temperature is ' . $tmp . ' degrees.';
        } else {
            $speak = 'Unknown location ' . $name;
        }
    } else {
        $speak = 'The average temperature is ' . ($status['avgTmp']) . ' degrees.';
    }
} else if($intent == 'SetTemperatureIntent') {
    $newtmp = intval($slots['newtmp']['value']);
	if($newtmp >= 50 && $newtmp <= 90) {
		sendCmd('setTmp,' . $newtmp);
		$speak = 'Ok';
	} else if ($newtmp >= 90) {
		$speak = $newtmp . ' degrees is too hot.';
	} else {
		$speak = $newtmp . ' degrees is too cold.';
	}
} else {
    $speak = 'Sorry, I am unsure of your intent.';
}

// Print result back!
$outputSpeech = "";
if($speak != null and strlen($speak) > 0) {
    $outputSpeech = '"outputSpeech": {"type": "PlainText","text": "' . $speak . '"}';
}

echo '{"version": "1.0","response": {' . $outputSpeech . '},"sessionAttributes": {}}';
?>