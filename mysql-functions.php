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
		$db->query("SET NAMES 'utf8'");

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
		$db=$this->db;
		$this->datatorint=$db->query($query);

		if(is_object($this->datatorint)) {
			$this->wasquery=true;
		}
		return $db->error;
	}
}

if($usemysql) {
	global $db,$mysqldata;
	$md=$mysqldata;
	$db=new SQLuser($md["host"],$md["db"],$md["user"],$md["pass"],$md["table"]);
}

?>
