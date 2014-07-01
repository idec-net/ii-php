<?php
require("ii-functions.php");

class IIFrontend {
	public $echoes;
	public $echoesPath;
	
	function __construct($echoes,$echoespath) {
		$this->echoes=$echoes;
		$this->echoesPath=$echoespath;
	}
	function getMessageArray($msgid) {
		$msgone=htmlspecialchars(getmsg($msgid));
		$msg=explode("\n",$msgone);
		$meta=[];
		$tags=explode("/",$msg[0]);
		if(isset($tags[3])) {
			$meta['repto']=$tags[3];
		} else {
			$meta['repto']=false;
		}
		$meta['echo']=$msg[1];
		$meta['time']=$msg[2];
		$meta['from']=$msg[3];
		$meta['addr']=$msg[4];
		$meta['to']=$msg[5];
		$meta['subj']=$msg[6];
		$meta['msg']=implode("<br />\n", array_slice($msg, 8));
		
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
