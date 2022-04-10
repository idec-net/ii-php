<?php
require("ii-functions.php");

$oldtransport=new TextBase("echo/", "msg/");
$db=new PostgreBase($postgredata);

$creation=$db->executeQuery("
CREATE TABLE IF NOT EXISTS $db->tablename
	(
		number SERIAL PRIMARY KEY,
		id varchar(20) NOT NULL,
		tags text,
		echoarea text NOT NULL,
		date varchar(30) NOT NULL default '0',
		msgfrom text,
		addr text,
		msgto text,
		subj text not NULL,
		msg text not NULL,
        CONSTRAINT msgid_unique UNIQUE(id)
	);
");
echo $creation;
echo pg_last_error($db->db);

$echos=$oldtransport->fullEchoList();

foreach($echos as $echo) {
	$msgids=$oldtransport->getMsgList($echo);
	echo "trying to save echo ".$echo."\n";
	foreach($msgids as $msgid) {
		$message=$oldtransport->getMessage($msgid);
		$db->saveMessage($msgid, $echo, $message, $raw=false);
        echo pg_last_error($db->db);
	}
}

?>
