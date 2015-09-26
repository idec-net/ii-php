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
	) ENGINE InnoDB default charset='utf8';
");
echo $creation;

function file_getmsg($t) {
	$t = preg_replace("/[^a-zA-Z0-9]+/", "", $t);
	if(!isBlackListed($t) && file_exists("msg/$t")) {
		return file_get_contents ("msg/$t");
	} else return "";
}
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
		$plainmsg=file_getmsg($msgid);
		if(!validatemsg($plainmsg)) {
			echo "invalid message: ".$msgid."\n";
			continue;
		}
		
		$msgarr=$db->prepareForInsert($plainmsg, $msgid);
		$db->insertData($msgarr);
		if(substr($db->db->error, 0, 9)!="Duplicate") {
			echo $db->db->error."\n";
		}
	}
}

?>
