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
	$msgids=array_slice($opts, 3);
	
	if($usemysql) {
		$messages=getMessages($msgids);
	} else {
		$messages=[];
		foreach($msgids as $msgid) {
			$messages[$msgid]=getmsg($msgid);
		}
	}

	foreach($msgids as $msgid) {
		echo $msgid.":".base64_encode($messages[$msgid])."\n";
	}
}

if ($opts[1] == 'u' and $opts[2] == 'e') {
	foreach(array_slice($opts, 3) as $echo) { 
		echo $echo."\n".getecho($echo);
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
		$pointCheck=checkUser($au);
		if($pointCheck) {
			$auth=$au;
			$authname=$pointCheck[0];
			$addr=$pointCheck[1];
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

if($opts[1] == 'blacklist.txt') {
	echo implode("", $blacklist);
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

if($opts[1] == 'x' and $opts[2] == 'e' and !empty($_POST['data'])) {
	$lines=explode("\n", $_POST['data']);
	foreach ($lines as $line) {
		$line=explode(":", $line);
		if (count($line)!=2) continue;

		$echoarea=trim($line[0]);
		$msgid=trim($line[1]);
		
		$msgids=getecho($echoarea);
		$index=explode("\n", $msgids);
		array_pop($index);

		$maxElement=count($index)-1;

		$search=array_search($msgid, $index);
		if ($search!=NULL and $search<$maxElement) {
			$newMessages=array_slice($index, $search+1);
			echo $echoarea."\n".implode("\n", $newMessages)."\n";
		} elseif ($search==$maxElement) {
			continue;
		} else {
			echo $echoarea."\n".$msgids;
		}
	}
}

?>
