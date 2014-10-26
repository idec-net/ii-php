<?php
require_once("config.php");

class SQLuser {
	public $db;
	public $tablename;
	public $userdata;
	public $datatorint;
	public $onpage=4;
	public $wasquery=false;
	function __construct($host,$db,$user,$pass,$table)
	{
		$this->db=new mysqli($host,$user,$pass,$db);
		$db=$this->db;
		$db->query("SET NAMES 'utf8'");

		$this->tablename=$table;

		if($this->userSent("data")) {
			$data=$this->userdata;
			$data=$this->defenceHacker($data);
			$this->insertData($data);
		} elseif($this->userSent("query")) {
			$this->executeQuery($this->userdata['query']);
		}

		echo $db->error;
		$this->printInf();

		$db->close();
	}
	function insertData($data)
	{
		$this->executeQuery("insert into $this->tablename values($data[0],'$data[1]','$data[2]')");
	}
	function executeQuery($query)
	{
		$db=$this->db;
		$this->datatorint=$db->query($query);

		if(is_object($this->datatorint)) {
			$this->wasquery=true;
		}
	}
}

if($usemysql) {
	global $db,$mysqldata;
	$md=$mysqldata;
	$db=new SQLuser($md["host"],$md["db"],$md["user"],$md["pass"],$md["table"]);
}

?>
