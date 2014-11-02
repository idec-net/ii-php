<?php
require("ii-functions.php");

if(!$db) {die("not connected to mysql!\n");}

$creation=$db->executeQuery("
CREATE TABLE IF NOT EXISTS `$db->tablename`
	(
		`id` varchar(20) NOT NULL primary key,
		`tags` text,
		`echoarea` text not NULL,
		`date` int not NULL default 0,
		`msgfrom` text,
		`addr` text,
		`msgto` text,
		`subj` text not NULL,
		`msg` text not NULL
	) ENGINE InnoDB;
");
echo $creation;

function getechoarray($echo) {
		$echofile=explode("\n", file_get_contents("echo/".$echo));
		array_pop($echofile);
		return $echofile;
}
$echolist=scandir("echo/");
$echos=[];

foreach($echolist as $echofile) {
		if($echofile!="." && $echofile!="..") {
				$echos[]=$echofile;
		}
}

foreach($echos as $echo) {
	$msgids=getechoarray($echo);
	echo "trying to save echo ".$echo."\n";
	foreach($msgids as $msgid) {
		$plainmsg=getmsg($msgid);
		if(!validatemsg($plainmsg)) {
			echo "invalid message: ".$msgid."\n";
			continue;
		}

		$msg=explode("\n", $plainmsg);
		for($i=0;$i<count($msg);$i++) {
			$msg[$i]=$db->db->real_escape_string($msg[$i]);
		}
		$msgarr=array(
			"id" => $msgid,
			"tags" => $msg[0],
			"echoarea" => $msg[1],
			"date" => $msg[2],
			"msgfrom" => $msg[3],
			"addr" => $msg[4],
			"msgto" => $msg[5],
			"subj" => $msg[6],
			"msg" => implode("\n", array_slice($msg, 8))
		);
		echo $db->insertData($msgarr);
	}
}

?>
