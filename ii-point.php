<?php
require("ii-functions.php");
header ('Content-Type: text/plain; charset=utf-8');

if(isset($_GET['q'])) {
	$q = $_GET['q'];
	$opts = explode('/', $q);
} else {
	exit();
}

$auth=0;
$authname=0;

if ($opts[1] == 'e') {
	echo getecho($opts[2]);
}

if ($opts[1] == 'm') {
	echo getmsg($opts[2]);
}

if ($opts[1] == 'u' and $opts[2] == 'm') {
	for ($x=3;$x<count($opts);$x++) { 
		$hash = base64_encode(getmsg($opts[$x]));
		echo "$opts[$x]:$hash\n";
	}
}

if ($opts[1] == 'u' and $opts[2] == 'e') {
	for ($x=3;$x<count($opts);$x++) { 
		echo $opts[$x] . "\n";
		echo getecho($opts[$x]);
	}
}

if (!empty($_POST['upush'])) {
	$contents = $_POST['upush']; $nodeAuth = $_POST['nauth']; $echoarea = $_POST['echoarea'];
	if (empty($pushpassword) or ($nodeAuth != $pushpassword)) {
		die('auth error');
	}
	$lines = explode("\n",$contents);

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
	if(count($ms)>$postlimit) die("error:msg big!");
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
			pointSend($ms,$authname,$nodeName.", ".$addr);
		} else {
			die("error: no auth!");
		}
	} else die('error: unknown');
}

if($opts[1] == 'list.txt') {
	displayEchoList();
}

if($opts[1] == 'x' and $opts[2] == 't') {
	$echos=[];
	for ($x=3;$x<count($opts);$x++) {
		$echos[]=$opts[$x];
	}
	displayEchoList($echos);
}

if($opts[1] == 'x' and $opts[2] == 'small-echolist') {
	displayEchoList(null, $small=true);
}

?>
