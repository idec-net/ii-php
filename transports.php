<?php

interface AbstractTransport {
	public function saveMessage($msgid=NULL, $echo, $message, $raw);
	public function updateMessage($msgid, $message);
	public function deleteMessage($msgid, $echo=NULL);
	public function deleteMessages($msgids, $echo=NULL);

	public function getMsgList($echo, $offset=0, $length=NULL);
	public function setMsgList($echo, $list);

	public function deleteEchoarea($echo, $with_contents=true);

	public function getRawMsg($msgid);
	public function getMessage($msgid);
	public function getMessages($msgids);

	public function fullEchoList();
	public function countMessages($echo);
}

class TextBase implements AbstractTransport {
	public $nomessage=array(
		"tags" => [],
		"echo" => "no.echo",
		"time" => "0",
		"from" => "",
		"addr" => "",
		"to" => "",
		"subj" => "",
		"msg" => "no message",
		"repto" => false,
		"id" => NULL
	);

	public function __construct($indexdir, $msgdir) {
		$this->indexdir=$indexdir;
		$this->msgdir=$msgdir;
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

	function getRawMsg($msgid) {
		if (file_exists($this->msgdir."/".$msgid)) {
			return file_get_contents($this->msgdir."/".$msgid);
		} else {
			return "";
		}
	}

	function getMessage($msgid) {
		$rawmsg=$this->getRawMsg($msgid);
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
				"id" => $msgid
			);
		} else {
			return $this->nomessage;
		}
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
			deleteMessage($msgid, $withechoes);
		}
	}
	function fullEchoList() {
		return scandir($this->indexdir);
	}
	function countMessages($echo) {
		return count($this->getMsgList($echo));
	}

	function makeRaw($message) {
		$rawmsg=implode("/", $message["tags"])."\n".
			$message["echo"]."\n".
			$message["time"]."\n".
			$message["from"]."\n".
			$message["addr"]."\n".
			$message["to"]."\n".
			$message["subj"]."\n\n".
			$message["msg"];
		return $rawmsg;
	}

	function saveMessage($msgid=NULL, $echo, $message, $raw=true) {
		if (!$raw) $message=$this->makeRaw($message);
		if ($msgid == NULL) {
			$msgid=hsh($rawmsg);
		}
		$this->appendMsgList($echo, [$msgid]);

		$f=fopen($this->msgdir."/".$msgid, "wb");
		fwrite($f, $message);
		fclose($f);

		return $msgid;
	}

	function updateMessage($msgid, $message) {
		$message=$this->makeRaw($message);
		$f=fopen($this->msgdir."/".$msgid, "wb");
		fwrite($f, $message);
		fclose($f);
	}

	function getMsgList($echo, $offset=0, $length=NULL) {
		// todo: срезы
		$list=explode("\n", file_get_contents($this->indexdir."/".$echo));
		array_pop($list);
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

$transport=new TextBase("echo/", "msg/");

?>
