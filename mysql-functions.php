<?php
require_once("config.php");

class SQLuser {
	public $db;
	public $tablename;
	public $datatorint;
	public $onpage=4;
	public $wasquery=false;
	function __construct($host,$db,$user,$pass,$table)
	{
		$this->db=new mysqli($host,$user,$pass,$db);
		$db=$this->db;
		$q1=$db->query("SET NAMES `utf8`");

		$this->tablename=$table;

		if($db->error) {
			echo $db->error;
		}
	}
	function __destruct()
	{
		$this->db->close();
	}
	function insertData($msg)
	{
		return $this->executeQuery("insert into `$this->tablename` values('".$msg['id']."', '".$msg['tags']."', '".$msg['echoarea']."', '".$msg['date']."', '".$msg['msgfrom']."', '".$msg['addr']."', '".$msg['msgto']."', '".$msg['subj']."', '".$msg['msg']."')");
	}
	function executeQuery($query)
	{
		return $this->db->query($query);
	}
	function prepareForInsert($plainmsg, $msgid) {
		$msg=explode("\n", $plainmsg);
		
		for($i=0;$i<count($msg);$i++) {
			$msg[$i]=$this->db->real_escape_string($msg[$i]);
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
		return $msgarr;
	}
}

if($usemysql) {
	global $db,$mysqldata;
	$md=$mysqldata;
	$db=new SQLuser($md["host"],$md["db"],$md["user"],$md["pass"],$md["table"]);
}

function getMessages($msgids) {
	global $db;
	$messages=[];
	
	$part="";

	for($i=0;$i<count($msgids);$i++) {
		$part.="`id`='".$db->db->real_escape_string($msgids[$i])."'";
		if($i!=count($msgids)-1) { $part.=" OR "; }
	}
	$query_text="SELECT * FROM `$db->tablename` WHERE ".$part;
	$query=$db->executeQuery($query_text);

	if(!is_object($query)) {
		echo $db->db->error."\n".$query_text."\n";
		return [];
	}
	while($row=$query->fetch_row()) {
		$n=array("\n"); //for compatibility
		$arr1=array_merge(array_slice($row, 1, 7)+$n+$row);

		$messages[$row[0]]=implode("\n", $arr1);
	}
	return $messages;
}

?>
