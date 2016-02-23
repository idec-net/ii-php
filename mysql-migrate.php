<?php
require("ii-functions.php");

$texttransport=new TextBase("echo/", "msg/");

class newBase extends MysqlBase {
	function saveMessage($msgid=NULL, $echo, $message, $raw) {
		if ($raw) {
			if (!$msgid) $msgid=hsh($message);
			$message=$this->makeReadable($message);
		}
		if (!$msgid) $msgid=hsh(serialize($message));
		$message["id"]=$msgid;

		$message["tags"]=$this->collectTags($message["tags"]);
		$message=$this->prepareInsert($message);

		$this->insertData($message);

		return $msgid;
	}
}

$db=new newBase($mysqldata);

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
		$db->saveMessage($msgid, $echo, $message, $raw=false);
		if(substr($db->db->error, 0, 9)!="Duplicate") {
			echo $db->db->error."\n";
		}
	}
}

?>
