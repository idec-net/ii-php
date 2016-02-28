<?php

class OldMysqlBase extends TextBase implements AbstractTransport {
	function __construct($data, $indexdir="./echo") {
		$host=$data["host"];
		$db=$data["db"];
		$user=$data["user"];
		$pass=$data["pass"];
		$table=$data["table"];

		$this->db=new mysqli($host, $user, $pass, $db);
		$db=$this->db;
		$q1=$db->query("SET NAMES `utf8`");

		$this->tablename=$table;

		if($db->error) {
			echo $db->error;
		}

		if (!file_exists($indexdir)) {
			mkdir($indexdir, $recursive=true);
		}
		$this->indexdir=$indexdir;
	}

	function __destruct() {
		$this->db->close();
	}

	function executeQuery($query) {
		return $this->db->query($query);
	}

	function prepareInsert($message) {
		$keys=array_keys($message);
		foreach ($keys as $key) {
			$message[$key]=$this->db->real_escape_string($message[$key]);
		}
		return $message;
	}

	function insertData($msg) {
		return $this->executeQuery("insert into `$this->tablename` values('".$msg['id']."', '".$msg['tags']."', '".$msg['echo']."', '".$msg['time']."', '".$msg['from']."', '".$msg['addr']."', '".$msg['to']."', '".$msg['subj']."', '".$msg['msg']."')");
	}

	function saveMessage($msgid=NULL, $echo, $message, $raw) {
		if ($raw) {
			if (!$msgid) $msgid=hsh($message);
			$message=$this->makeReadable($message);
		}
		if (!$msgid) $msgid=hsh(serialize($message));
		$message["id"]=$msgid;

		$message["tags"]=$this->collectTags($message["tags"]);
		$message=$this->prepareInsert($message);

		$this->appendMsgList($echo, [$msgid]);
		$this->insertData($message);

		return $msgid;
	}

	function getMessages($msgids) {
		$db=$this->db;
		$messages=[];
		
		$part="";
	
		for($i=0;$i<count($msgids);$i++) {
			$part.="`id`='".$db->real_escape_string($msgids[$i])."'";
			if ($i!=count($msgids)-1) $part.=" OR ";
		}
		$query_text="SELECT * FROM `$this->tablename` WHERE ".$part;
		$query=$this->executeQuery($query_text);
	
		if(!is_object($query)) {
			echo $db->error."\n".$query_text."\n";
			return [];
		}
		while($row=$query->fetch_assoc()) {
			$msgid=$row["id"];
			$messages[$msgid]=[
				"id" => $msgid,
				"tags" => $this->parseTags($row["tags"]),
				"echo" => $row["echoarea"],
				"time" => $row["date"],
				"from" => $row["msgfrom"],
				"addr" => $row["addr"],
				"to" => $row["msgto"],
				"subj" => $row["subj"],
				"msg" => $row["msg"]
			];
			if (isset($messages[$msgid]["tags"]["repto"])) {
				$messages[$msgid]["repto"]=$messages[$msgid]["tags"]["repto"];
			} else $messages[$msgid]["repto"]=false;
		}
		$got_msgids=array_keys($messages);
		$difference=array_diff($msgids, $got_msgids);
		if (count($difference) > 0) {
			foreach($difference as $msgid) $messages[$msgid]=$this->nomessage;
		}
		return $messages;
	}

	function getMessage($msgid) {
		$data=$this->getMessages([$msgid]);
		if (isset($data[$msgid])) return $data[$msgid];
		else return $this->nomessage;
	}

	function getRawMessage($msgid) {
		$message=$this->getMessage($msgid);
		return $this->makeRaw($message);
	}

	function getRawMessages($msgids) {
		$messages=$this->getMessages($msgids);
		$keys=array_keys($messages);
		$output=[];
		foreach ($keys as $msgid) {
			$output[$msgid]=$this->makeRaw($messages[$msgid]);
		}
		return $output;
	}

	function updateMessage($msgid, $message) {
		$message["tags"]=$this->collectTags($message["tags"]);
		$message=$this->prepareInsert($message);

		$query_text="UPDATE `$this->tablename` SET `tags`=\"".$message["tags"].
		"\", `echoarea`=\"".$message["echo"].
		"\", `date`=\"".intval($message["time"]).
		"\", `msgfrom`=\"".$message["from"].
		"\", `addr`=\"".$message["addr"].
		"\", `msgto`=\"".$message["to"].
		"\", `subj`=\"".$message["subj"].
		"\", `msg`=\"".$message["msg"]."\" ".
		"WHERE `id`=\"$msgid\"";

		return $this->executeQuery($query_text);
	}

	function deleteMessage($msgid, $withecho=NULL) {
		if ($withecho) {
			$echo=$this->getMessage($msgid)["echo"];
			$echoContents=$this->getMsgList($echo);

			$key=array_search($msgid, $echoContents);
			if($key!=false) {
				unset($echoContents[$key]);
				$this->setMsgList($echo, $echoContents);
			}
		}
		$query_text="DELETE from `$this->tablename` WHERE `id`=\"$msgid\"";
		return $this->executeQuery($query_text);
	}

	function fullEchoList() {
		$query_text="SELECT DISTINCT `echoarea` from `$this->tablename`";
		$result=$this->executeQuery($query_text);

		if(!is_object($result)) {
			echo $this->db->error."\n".$query_text."\n";
			return [];
		}

		$output=[];
		while($row=$result->fetch_assoc()) {
			$output[]=$row["echoarea"];
		}
		return $output;
	}
}

?>
