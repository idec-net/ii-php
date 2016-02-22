<?php
require("ii-functions.php");

$db=new MysqlBase($mysqldata);
$texttransport=new TextBase("echo/", "msg/");

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

$echos=$texttransport->fullEchoList();

foreach($echos as $echo) {
	$msgids=$texttransport->getMsgList($echo);
	echo "trying to save echo ".$echo."\n";
	foreach($msgids as $msgid) {
		$message=$texttransport->getMessage($msgid);
		$db->saveMessage($message["id"], $echo, $message, $raw=false);
		if(substr($db->db->error, 0, 9)!="Duplicate") {
			echo $db->db->error."\n";
		}
	}
}

?>
