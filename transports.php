<?php

interface AbstractTransport {
	public function saveMessage($msgid, $echo, $message, $raw);
	public function updateMessage($msgid, $message);
	public function deleteMessage($msgid, $echo=NULL);
	public function deleteMessages($msgids, $echo=NULL);

	public function getMsgList($echo, $offset, $length);
	public function deleteEchoarea($echo, $with_contents=true);

	public function getRawMessage($msgid);
	public function getRawMessages($msgids);
	public function getMessage($msgid);
	public function getMessages($msgids);

	public function fullEchoList();
	public function countMessages($echo);
}

class TransportCommon {
	public $nomessage=array(
		"tags" => [],
		"echo" => "no.echo",
		"time" => "0",
		"from" => "",
		"addr" => "",
		"to" => "",
		"subj" => "no subj",
		"msg" => "no message",
		"repto" => false,
		"id" => NULL
	);

	function makeRaw($message) {
		if (is_array($message["tags"])) {
			if ($message["repto"]) $message["tags"]["repto"]=$message["repto"];
			$message["tags"]=$this->collectTags($message["tags"]);
		}

		$rawmsg=$message["tags"]."\n".
			$message["echo"]."\n".
			$message["time"]."\n".
			$message["from"]."\n".
			$message["addr"]."\n".
			$message["to"]."\n".
			$message["subj"]."\n\n".
			$message["msg"];
		return $rawmsg;
	}
	
	function makeReadable($rawmsg) {
		$msg=explode("\n", $rawmsg);

		if(count($msg)>=8) {
			$tags=$this->parseTags($msg[0]);
			$repto=(isset($tags["repto"])) ? $tags["repto"] : false;

			return array(
				"tags" => $tags,
				"echo" => $msg[1],
				"time" => $msg[2],
				"from" => $msg[3],
				"addr" => $msg[4],
				"to" => $msg[5],
				"subj" => $msg[6],
				"msg" => implode("\n", array_slice($msg, 8)),
				"repto" => $repto,
				"id" => NULL
			);
		} else {
			return $this->nomessage;
		}
	}

	function parseTags($string) {
		$tags=explode("/",$string);
		$newtags=[];

		for($i=0;$i<count($tags);$i+=2) {
			if(!empty($tags[$i+1])) {
				$newtags[$tags[$i]]=$tags[$i+1];
			} else {
				$newtags[$tags[$i]]=false;
			}
		}
		return $newtags;
	}

	function collectTags($tags) {
		$fragments=[];
		foreach ($tags as $key => $value) {
			if ($value) $fragments[]=$key."/".$value;
		}
		return implode("/", $fragments);
	}
}

class TextBase extends TransportCommon implements AbstractTransport {
	public function __construct($indexdir, $msgdir) {
		if (!file_exists($indexdir)) {
			mkdir($indexdir, $recursive=true);
		}

		if (!file_exists($msgdir)) {
			mkdir($msgdir, $recursive=true);
		}

		$this->indexdir=$indexdir;
		$this->msgdir=$msgdir;
	}

	function getRawMessage($msgid) {
		if (file_exists($this->msgdir."/".$msgid)) {
			return @file_get_contents($this->msgdir."/".$msgid);
		} else {
			return "";
		}
	}

	function getRawMessages($msgids) {
		$messages=[];
		foreach ($msgids as $msgid) {
			$messages[$msgid]=$this->getRawMessage($msgid);
		}
		return $messages;
	}

	function getMessage($msgid) {
		$rawmsg=$this->getRawMessage($msgid);
		$readable=$this->makeReadable($rawmsg);
		$readable["id"]=$msgid;
		return $readable;
	}

	function getMessages($msgids) {
		$output=[];
		foreach ($msgids as $msgid) {
			$output[$msgid]=$this->getMessage($msgid);
		}
		return $output;
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
		if (file_exists($this->msgdir."/".$msgid)) {
			unlink($this->msgdir."/".$msgid);
		}
	}

	function deleteMessages($msgids, $withechoes=NULL) {
		foreach ($msgids as $msgid) {
			$this->deleteMessage($msgid, $withechoes);
		}
	}

	function fullEchoList() {
		$files=scandir($this->indexdir);
		$echos=[];
		foreach($files as $echofile) {
			if ($echofile!="." && $echofile!="..") {
				$echos[]=$echofile;
			}
		}
		return $echos;
	}

	function countMessages($echo) {
		return count($this->getMsgList($echo));
	}

	function saveMessage($msgid, $echo, $message, $raw) {
		if (!$raw) $message=$this->makeRaw($message);
		if ($msgid == NULL) {
			$msgid=hsh($message);
		}

		$f=fopen($this->msgdir."/".$msgid, "wb");

		if ($f) {
			fwrite($f, $message);
			fclose($f);

			$this->appendMsgList($echo, [$msgid]);
			return $msgid;
		} else return null;
	}

	function updateMessage($msgid, $message) {
		$message=$this->makeRaw($message);
		$f=fopen($this->msgdir."/".$msgid, "wb");
		fwrite($f, $message);
		fclose($f);
	}

	function getMsgList($echo, $offset=NULL, $length=NULL) {
		if (!file_exists($this->indexdir."/".$echo)) return [];

		$list=explode("\n", file_get_contents($this->indexdir."/".$echo));
		array_pop($list);

		if ($offset) {
			$a=intval($offset);

			if ($length != NULL) $b=intval($length);
			else $b=NULL;

			$slice=array_slice($list, $a, $b);
			return $slice;
		}

		return $list;
	}

	function setMsgList($echo, $list) {
		file_put_contents($this->indexdir."/".$echo, implode("\n", $list)."\n");
	}

	function appendMsgList($echo, $msgids) {
		$f=fopen($this->indexdir."/".$echo, "ab");
		
		foreach ($msgids as $msgid) {
			fwrite($f, $msgid."\n");
		}

		fclose($f);
	}

	function deleteEchoarea($echo, $with_contents=true) {
		if ($with_contents) {
			$messages=$this->getMsgList($echo);
			foreach ($messages as $msgid) {
				$this->deleteMessage($msgid);
			}
		}
		unlink($this->indexdir."/".$echo);
	}
}

class MysqlBase extends TextBase implements AbstractTransport {
	function __construct($data) {
		$host=$data["host"];
		$db_name=$data["db"];
		$user=$data["user"];
		$pass=$data["pass"];
		$table=$data["table"];

		$this->db=new mysqli($host, $user, $pass, $db_name);
		unset($this->setMsgList);

		$db=$this->db;
		$q1=$db->query("SET NAMES `utf8`");

		$this->tablename=$table;

		if ($db->error) die($db->error);
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
		return $this->executeQuery("insert into `$this->tablename` values(NULL, '".$msg['id']."', '".$msg['tags']."', '".$msg['echo']."', '".$msg['time']."', '".$msg['from']."', '".$msg['addr']."', '".$msg['to']."', '".$msg['subj']."', '".$msg['msg']."')");
	}

	function saveMessage($msgid, $echo, $message, $raw) {
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
		"\", `date`=\"".$message["time"].
		"\", `msgfrom`=\"".$message["from"].
		"\", `addr`=\"".$message["addr"].
		"\", `msgto`=\"".$message["to"].
		"\", `subj`=\"".$message["subj"].
		"\", `msg`=\"".$message["msg"]."\" ".
		"WHERE `id`=\"$msgid\"";

		return $this->executeQuery($query_text);
	}

	function deleteMessage($msgid, $withecho=NULL) {
		$query_text="DELETE from `$this->tablename` WHERE `id`=\"$msgid\"";
		return $this->executeQuery($query_text);
	}

	function getMsgList($echo, $offset=NULL, $length=NULL) {
		$query_text="SELECT `id`, `number` from `$this->tablename` ".
			"where `echoarea`=\"$echo\" order by `number`";

		if ($offset !== NULL) $a=intval($offset);
		else $a=NULL;
		if ($length !== NULL) $b=intval($length);
		else $b=NULL;

		if ($a === NULL) $query_text.=" asc";
		elseif ($a < 0) {
			$a=-$a;
			$query_text="SELECT * from ($query_text desc LIMIT $a) as tmp order by number asc";
			if ($b and $b > 0) $query_text.=" LIMIT $b";
		} else {
			if (!$b or $b < 0) $b=18446744073709551610;
			$query_text.=" asc LIMIT $b";
			if ($a != 0) $query_text.=" OFFSET $a";
		}

		$query=$this->executeQuery($query_text);

		if (is_object($query)) {
			$array=$query->fetch_all();
			foreach ($array as $key => $value) $array[$key]=$value[0];
			return $array;
		} else {
			die($this->db->error."\n".$query_text);
		}
	}

	function countMessages($echo) {
		$query_text="SELECT count(*) from `$this->tablename` where `echoarea`=\"$echo\"";
		$result=$this->executeQuery($query_text);

		$count=$result->fetch_row()[0];
		return $count;
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

	function deleteEchoarea($echo, $with_contents=true) {
		$messages=$this->getMsgList($echo);
		foreach ($messages as $msgid) {
			$this->deleteMessage($msgid);
		}
	}
}

class PostgreBase extends TextBase implements AbstractTransport {
	function __construct($data) {
		$host=$data["host"];
		$db_name=$data["db"];
		$user=$data["user"];
		$pass=$data["pass"];
		$table=$data["table"];
        $port=$data["port"];

		$this->db=pg_connect("host=$host port=$port dbname=$db_name user=$user password=$pass");
		unset($this->setMsgList);

		$db=$this->db;
		$this->tablename=$table;

		if (!$db) die("unable to connect to database");
	}

	function __destruct() {
		pg_close($this->db);
	}

	function executeQuery($query) {
        $ret = pg_query($this->db, $query);
        return $ret;
	}

	function prepareInsert($message) {
		$keys=array_keys($message);
		foreach ($keys as $key) {
			$message[$key]=pg_escape_literal($this->db, $message[$key]);
		}
		return $message;
	}

	function insertData($msg) {
        $query = "insert into $this->tablename values(DEFAULT, ".$msg['id'].", ".$msg['tags'].", ".$msg['echo'].", ".$msg['time'].", ".$msg['from'].", ".$msg['addr'].", ".$msg['to'].", ".$msg['subj'].", ".$msg['msg'].")";
		return $this->executeQuery($query);
	}

	function saveMessage($msgid, $echo, $message, $raw) {
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

	function getMessages($msgids) {
		$db=$this->db;
		$messages=[];
		
		$part="";
	
		for($i=0;$i<count($msgids);$i++) {
			$part.="id='".pg_escape_string($this->db, $msgids[$i])."'";
			if ($i!=count($msgids)-1) $part.=" OR ";
		}
		$query_text="SELECT * FROM $this->tablename WHERE ".$part;
		$query=$this->executeQuery($query_text);
	
		if(!$query) {
			echo pg_last_error($this->db)."\n".$query_text."\n";
			return [];
		}
		while($row=pg_fetch_assoc($query)) {
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

		$query_text="UPDATE $this->tablename SET tags=".$message["tags"].
		", echoarea=".$message["echo"].
		", date=".$message["time"].
		", msgfrom=".$message["from"].
		", addr=".$message["addr"].
		", msgto=".$message["to"].
		", subj=".$message["subj"].
		", msg=".$message["msg"]." ".
		"WHERE id='".$msgid."'";

		return $this->executeQuery($query_text);
	}

	function deleteMessage($msgid, $withecho=NULL) {
		$query_text="DELETE from $this->tablename WHERE id='".$msgid."'";
		return $this->executeQuery($query_text);
	}

	function getMsgList($echo, $offset=NULL, $length=NULL) {
		$query_text="SELECT id, number from $this->tablename ".
			"where echoarea='".$echo."' order by number";

		if ($offset !== NULL) $a=intval($offset);
		else $a=NULL;
		if ($length !== NULL) $b=intval($length);
		else $b=NULL;

		if ($a === NULL) $query_text.=" asc";
		elseif ($a < 0) {
			$a=-$a;
			$query_text="SELECT * from ($query_text desc LIMIT $a) as tmp order by number asc";
			if ($b and $b > 0) $query_text.=" LIMIT $b";
		} else {
			if (!$b or $b < 0) $b=18446744073709551610;
			$query_text.=" asc LIMIT $b";
			if ($a != 0) $query_text.=" OFFSET $a";
		}

		$query=$this->executeQuery($query_text);

		if ($query) {
			$array=pg_fetch_all($query);
            $lst = [];
            foreach ($array as $key => $value) {
                $lst[] = $value["id"];
            }
			return $lst;
		} else {
			die(pg_last_error($this->db)."\n".$query_text);
		}
	}

	function countMessages($echo) {
		$query_text="SELECT count(*) from $this->tablename where echoarea='".$echo."'";
		$result=$this->executeQuery($query_text);

		$count=pg_fetch_row($result)[0];
		return $count;
	}

	function fullEchoList() {
		$query_text="SELECT DISTINCT echoarea from $this->tablename";
		$result=$this->executeQuery($query_text);

		if(!$result) {
			echo pg_last_error($this->db)."\n".$query_text."\n";
			return [];
		}

		$output=[];
		while($row=pg_fetch_assoc($result)) {
			$output[]=$row["echoarea"];
		}
		return $output;
	}

	function deleteEchoarea($echo, $with_contents=true) {
		$messages=$this->getMsgList($echo);
		foreach ($messages as $msgid) {
			$this->deleteMessage($msgid);
		}
	}
}


interface AbstractFileTransport {
	public function saveFile($hash, $fecho, $file, $filename, $address, $description);
	public function updateInfo($hash, $fecho, $filename, $address, $description);
	public function deleteFile($hash, $echo=NULL);

	public function getRawFileList($fecho, $offset, $length);
	public function getFileList($fecho, $offset, $length, $size=false);
	public function deleteFileEchoarea($fecho, $with_contents=true);

	public function getFullFilename($hash);

	public function fullFechoList();
	public function countFiles($echo);
}

class NoBaseFileTransport {
	public function __construct($indexdir, $filedir) {
		if (!file_exists($indexdir)) {
			mkdir($indexdir, $recursive=true);
		}

		if (!file_exists($filedir)) {
			mkdir($filedir, $recursive=true);
		}
		$this->indexdir = $indexdir;
		$this->filedir = $filedir;
	}

	public function saveFile($hash, $fecho, $file, $filename, $address, $description) {
		if ($hash != NULL) {
			$echoContents = $this->getFileList($fecho);

			$changed = false;
			foreach ($echoContents as &$entry) {
				if ($entry["id"] == $hash) {
					$entry["filename"] = $filename;
					$entry["address"] = $address;
					$entry["desc"] = $description;

					$changed = true;
				}
			}

			if ($changed) $this->setFileList($fecho, $echoContents);
		} else {
			$hash = hsh_file($file["tmp_name"]);
			$raw_entry = $hash . ":" . $filename . "::" . $address . ":" . $description;

			if (!file_exists($this->filedir . "/" . $hash))
				$this->appendMsgList($fecho, [$raw_entry]);
			else {
				echo "error: file exists\n";
				return null;
			}
		}

		if (!move_uploaded_file($file["tmp_name"], $this->filedir . "/" . $hash)) {
			return null;
		} else return $hash;
	}

	function appendMsgList($echo, $msgids) {
		$f=fopen($this->indexdir."/".$echo, "ab");

		foreach ($msgids as $msgid) {
			fwrite($f, $msgid."\n");
		}

		fclose($f);
	}

	public function updateInfo($hash, $fecho, $filename, $address, $description) {
		$entry = [
			"id" => $hash,
			"filename" => $filename,
			"address" => $address,
			"desc" => $description
		];

		$list = $this->getFileList($fecho);
		foreach ($list as &$file) {
			if ($file["id"] == $hash) $file = $entry;
		}

		$this->setFileList($fecho, $list);
	}

	public function deleteFile($hash, $echo=NULL) {
		if ($echo != NULL) {
			$echoContents=$this->getFileList($echo);

			for ($i=0; $i < count($echoContents); $i++) {
				if ($echoContents[$i]["id"] == $hash) {
					unset($echoContents[$i]);
					$this->setFileList($echo, $echoContents);
					break;
				}
			}
		}
		if (file_exists($this->filedir."/".$hash)) {
			unlink($this->filedir."/".$hash);
		}
	}

	public function getFullFilename($hash) {
		return $this->filedir . "/" . $hash;
	}

	public function getRawFileList($fecho, $offset=NULL, $length=NULL) {
		$filelist = $this->getFileList($fecho, $offset, $length, $size=true);

		foreach ($filelist as &$file) {
			$file = $file["id"] . ":" . $file["filename"] . ":" . $file["size"] . ":" . $file["address"] . ":" . $file["desc"];
		}

		return $filelist;
	}
	public function getFileList($fecho, $offset=NULL, $length=NULL, $size=false) {
		if (!file_exists($this->indexdir."/".$fecho)) return [];

		$list=explode("\n", file_get_contents($this->indexdir."/".$fecho));
		array_pop($list);

		$target_arr = [];

		if ($offset) {
			$a=intval($offset);

			if ($length != NULL) $b=intval($length);
			else $b=NULL;

			$slice=array_slice($list, $a, $b);
			$target_arr = $slice;
		} else $target_arr = &$list;

		foreach ($target_arr as &$rawline) {
			$entry = $this->parseFileEntry($rawline, $size);
			if ($entry != null) $rawline = $entry;
			else unset($rawline);
		}

		return $target_arr;
	}

	public function parseFileEntry($rawline, $size, $remote_size=false) {
		$pieces = explode(":", $rawline);
		if (count($pieces) < 5) return null;
		$entry = [
			"id" => $pieces[0],
			"filename" => $pieces[1],
			"size" => $size ? ($remote_size ? $pieces[2] : filesize($this->filedir . "/" . $pieces[0])) : 0,
			"address" => $pieces[3],
			"desc" => $pieces[4]
		];

		if (count($pieces) > 5) {
			for ($i=5; $i < count($pieces); $i++) {
				$entry["desc"] .= ":" . $pieces[$i];
			}
		}

		return $entry;
	}

	public function setFileList($fecho, $filelist) {
		$rawfile = "";
		foreach($filelist as $entry) {
			$rawfile .= $entry["id"].":".$entry["filename"]."::".$entry["address"].":".$filename["desc"]."\n";
		}

		file_put_contents($this->indexdir."/".$fecho, $rawfile);
	}

	public function deleteFileEchoarea($fecho, $with_contents=true) {
		if ($with_contents) {
			$files=$this->getFileList($echo);
			foreach ($files as $file) {
				$this->deleteFile($file["id"]);
			}
		}
		unlink($this->indexdir."/".$fecho);
	}

	public function getFullPath($hash) {
		return $indexdir . "/" . $hash;
	}

	public function fullFechoList() {
		$files=scandir($this->indexdir);
		$echos=[];
		foreach($files as $echofile) {
			if ($echofile!="." && $echofile!="..") {
				$echos[]=$echofile;
			}
		}
		return $echos;
	}

	public function countFiles($fecho) {
		return count($this->getFileList($fecho));
	}
}

?>
