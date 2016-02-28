<?php
require("ii-functions.php");

$oldtransport=new TextBase("echo/", "msg/");
/*
require_once("old-mysql-transport.php");
$oldmysqldata=array(
	"host" => "localhost",
	"db" => "test",
	"user" => "root",
	"pass" => "",
	"table" => "old-table"
);
$oldtransport=new OldMysqlBase($oldmysqldata, "echo/");
*/
$db=new MysqlBase($mysqldata);

$creation=$db->executeQuery("
CREATE TABLE IF NOT EXISTS `$db->tablename`
	(
		`number` bigint NOT NULL auto_increment,
		`id` varchar(20) NOT NULL,
		`tags` text,
		`echoarea` text NOT NULL,
		`date` varchar(30) NOT NULL default '0',
		`msgfrom` text,
		`addr` text,
		`msgto` text,
		`subj` text not NULL,
		`msg` text not NULL,
		primary key(number, id)
	) ENGINE InnoDB default charset='utf8';
");
echo $creation;
echo $db->db->error;

$echos=$oldtransport->fullEchoList();

foreach($echos as $echo) {
	$msgids=$oldtransport->getMsgList($echo);
	echo "trying to save echo ".$echo."\n";
	foreach($msgids as $msgid) {
		$message=$oldtransport->getMessage($msgid);
		$db->saveMessage($msgid, $echo, $message, $raw=false);
		if(substr($db->db->error, 0, 9)!="Duplicate") {
			echo $db->db->error;
		}
	}
}

?>
