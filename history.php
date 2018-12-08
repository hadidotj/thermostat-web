<?php
// Open DB
header('Content-Type: application/json');
$db = new SQLite3('/opt/thermostat/thermostat.db', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);

// set different views
$views = [
	'1h'=>['time'=>3600,'interval'=>30],
	'8h'=>['time'=>3600*8,'interval'=>120],
	'24h'=>['time'=>3600*24,'interval'=>300],
	'7d'=>['time'=>3600*24*7,'interval'=>3600],
	'1m'=>['time'=>3600*24*30,'interval'=>3600*3]
];
$viewName = ($_GET['v']!='')?$_GET['v']:'1h';
if(isset($views[$viewName]) === FALSE) {
	echo '{"error":"Unknow view name \"'.$viewName.'\"."}';
	exit;
}
$view = $views[$viewName];

// Average Calculator
class AVG {
	public $sum = 0;
	public $cnt = 0;
	
	function add($met) {
		$this->sum += $met;
		++$this->cnt;
	}
	
	function get() {
		return ($this->cnt>0) ? $this->sum/$this->cnt : 0;
	}
}

// Get all of the sensors
$ret = $db->query('SELECT id,name FROM sensors');
$sensors = [];
while($row = $ret->fetchArray()) {
    array_push($sensors, $row);
}

// Get all of the relays
$ret = $db->query('SELECT id,name FROM relays');
$relays = [];
while($row = $ret->fetchArray()) {
    array_push($relays, $row);
}

// Compute overall average temp, average sensor temp, and sensor line data
$sdata = [];
$tavg = new AVG();
$now = time();
$hourAgo = $now-$view['time'];
$freq = $view['interval']/5;
$debug = ['view:'.$_GET['v'],'time:'.$view['time'],'interval:'.$view['interval'],'now:'.$now,'ago:'.$hourAgo,'freq:'.$freq];
foreach($sensors as $sensor) {
	$q = 'SELECT time,temp FROM temphistory WHERE sensor=' . $sensor[0] . ' AND time>' . $hourAgo . ' ORDER BY time ASC';
	$ret = $db->query($q);
	$h = [];
	$i = 0;
	$savg = new AVG();
	$pavg = new AVG();
	while($row = $ret->fetchArray()) {
		$tmp = $row[1];
		$tavg->add($tmp);
		$savg->add($tmp);
		$pavg->add($tmp);
		if($i%$freq==0) {
			array_push($h, Array('x'=>$row[0]*1000,'y'=>$pavg->get()));
			$pavg = new AVG();
		}
		++$i;
	}
	array_push($sdata, Array('id'=>$sensor[1],'data'=>$h,'avg'=>$savg->get()));
}

// Compute relay count and on/off time, average run time, and total run time
$oavg = new AVG();
$rdata = [];
foreach($relays as $relay) {
	$ravg = new AVG();
	$laston = $hourAgo;
	$rh = [];
	$ret = $db->query('SELECT time,state FROM relayhistory WHERE relay=' . $relay[0] . ' AND time>' . $hourAgo . ' ORDER BY time ASC');
	while($row = $ret->fetchArray()) {
		if($row[1]=='1') {
			array_push($debug,'On:'.$row[0]);
			$laston = $row[0];
		} else {
			array_push($debug,'Off:'.$row[0].':'.($row[0]-$laston));
			$ravg->add($row[0]-$laston);
			$oavg->add($row[0]-$laston);
			array_push($rh, Array('x'=>$laston*1000,'y'=>1));	
			array_push($rh, Array('x'=>$row[0]*1000,'y'=>0));
			$laston = 0;
		}
	}
	if($laston != 0 && $ravg->cnt > 0) {
		array_push($debug,'StillOn:'.$now.':'.($now-$laston));
		$ravg->add($now-$laston);
		$oavg->add($now-$laston);
		array_push($rh, Array('x'=>$laston*1000,'y'=>1));
		array_push($rh, Array('x'=>$now*1000,'y'=>0));
	}
	array_push($rdata, Array('id'=>$relay[1],'data'=>$rh,'avg'=>$ravg->get(),'run'=>$ravg->sum,'cnt'=>$ravg->cnt));
}

$db->close();

echo json_encode(Array('debug'=>$debug,'sensors'=>$sdata,'relays'=>$rdata,'tavg'=>$tavg->get(),'ravg'=>$oavg->get(),'run'=>$oavg->sum,'cnt'=>$oavg->cnt));
?>