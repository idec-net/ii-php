<?php
require("ii-functions.php");

class IIFrontend {
	public $echoes;
	public $echoesPath;
	public $nomessage=array(
		"echo" => "none",
		"time" => "0",
		"from" => "",
		"addr" => "",
		"to" => "",
		"subj" => "",
		"msg" => "no message",
		"repto" => false,
		"id" => ""
	);

	function __construct($echoes,$echoespath) {
		$this->echoes=$echoes;
		$this->echoesPath=$echoespath;
	}
	function getMessagesArray($msgids) {
		global $usemysql;
		if($usemysql) {
			$msgsArr=getMessages($msgids);
		} else {
			$msgsArr=[];
			foreach($msgids as $msgid) {
				$msgsArr[$msgid]=getmsg($msgid);
			}
		}
		return $msgsArr;
	}
	function parseMessage($plainMessage, $msgid) {
		$msgone=htmlspecialchars($plainMessage);
		$msg=explode("\n",$msgone);
		$meta=[];
		$tags=explode("/",$msg[0]);
		$newtags=[];

		for($i=0;$i<count($tags);$i+=2) {
			if(!empty($tags[$i+1])) {
				$newtags[$tags[$i]]=$tags[$i+1];
			} else {
				$newtags[$tags[$i]]=false;
			}
		}

		if(isset($newtags['repto'])) {
			$repto=$newtags['repto'];
		} else {
			$repto=false;
		}
		if(count($msg)>=8) {
			$meta=array(
				"echo" => $msg[1],
				"time" => $msg[2],
				"from" => $msg[3],
				"addr" => $msg[4],
				"to" => $msg[5],
				"subj" => $msg[6],
				"msg" => implode("<br />\n", array_slice($msg, 8)),
				"repto" => $repto,
				"id" => $msgid
			);
		} else {
			$meta=$this->nomessage;
		}
		
		return $meta;
	}
	function getMsgList($echo) {
		if(checkEcho($echo) && file_exists($this->echoesPath.$echo)) {
			$msgs=explode("\n",getecho($echo));
			array_pop($msgs);
		} else $msgs=[];
		return $msgs;
	}
}

?>
