<?php
require("ii-functions.php");
header ('Content-Type: text/plain; charset=utf-8');

$q = $_GET['q'];
$opts = explode('/',$q);
$auth=0;
$authname=0;

if ($opts[1] == 'e') {
	echo getecho($opts[2]);
} # e

if ($opts[1] == 'm') {
	echo getmsg($opts[2]);
} # m

if ($opts[1] == 'u' and $opts[2] == 'm') {
	for ($x=3;$x<count($opts);$x++) { 
		$hash = base64_encode(getmsg($opts[$x]));
		echo "$opts[$x]:$hash\n";
	}
} # um

if ($opts[1] == 'u' and $opts[2] == 'e') {
	for ($x=3;$x<count($opts);$x++) { 
		echo $opts[$x] . "\n";
		echo getecho($opts[$x]);
	}
} # ue

if (!empty($_POST['upush'])) {
	$upush = $_POST['upush']; $nauth = $_POST['nauth']; $echoarea = $_POST['echoarea'];
	if (empty($pushpassword) or ($auth != $pushpassword)) {
		die('auth error');
	}
	$lines = explode("\n",$upush);

	for ($x=0;$x<count($lines);$x++) {
		$a = explode(":",$lines[$x]);
		savemsg($a[0],$echoarea,b64d($a[1]));
	}
}

if ($opts[1] == 'u' and $opts[2] == 'point') {
	$error=0;
	if (isset($opts[3]) && isset($opts[4]) &&
		$opts[3] && $opts[4]
	) {
		$au=$opts[3];
		$ms=$opts[4];
	} elseif($_POST['pauth'] && $_POST['tmsg']) {
		$au=$_POST['pauth'];
		$ms=$_POST['tmsg'];
	} else $error=1;
	$addr=0;
	if(count($ms)>65535) die("error:msg big!");
	if(!$error) {
		for($i=0;$i<count($parr);$i++) {
			if($parr[$i][0]==$au) {
				$auth=$au;
				$authname=$parr[$i][1];
				$addr=$i+1;
				break;
			}
		}
		if($auth and $authname) {
			pointSend($ms,$authname,$addr);
		}
		else {
			die("error:no auth!");
		}
	}
	else die('error: unknown');
}

if($opts[1] == 'list.txt' or ($opts[1] == 'x' and $opts[2] == 'echolist')) {
	displayEchoList();
}

if($opts[1] == 'x' and $opts[2] == 't') {
	$echos=[];
	for ($x=3;$x<count($opts);$x++) {
		$echos[]=$opts[$x];
	}
	displayEchoList($echos);
}

?>
