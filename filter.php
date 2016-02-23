<?php
class BaseAccess {
	function __construct($transport, $blacklist_file, $msgtextlimit) {
		$this->msgtextlimit=$msgtextlimit;
		$this->blacklist=$this->getBlackList($blacklist_file);
		$this->transport=$transport;
	}

	function getBlackList($blacklist_file) {
		return file($blacklist_file);
	}

	function isBlackListed($msgid) {
		if(in_array($msgid."\n", $this->blacklist)) {
			return true;
		} else return false;
	}

	function applyBlacklist($echo) {
		foreach($this->blacklist as $msgid) {
			$echo=str_replace($msgid, "", $echo);
		}
		return $echo;
	}

	static function checkHash($s) {
		if(!b64d($s)) {
			return false;
		} else return true;
	}

	static function checkEcho($echo) {
		$filter='/^[a-z0-9_!-.]{1,60}.[a-z0-9_!-]{1,60}$/';
		if(!preg_match($filter,$echo) or strpos($echo, ".")===false) return false;
		else return true;
	}

	function saveMessage($msgid=NULL, $echo, $message, $raw) {
		if (!$this->checkEcho($echo)) {
			echo "error: wrong echo";
			return 0;
		}

		if ($msgid != NULL) {
			if (!$this->checkHash($msgid)) {	
				echo "error: incorrect msgid\n";
				return 0;
			}

			if ($this->isBlackListed($msgid)) {
				echo "error: msgid is blacklisted: ".$msgid."\n";
				return 0;
			}
		}

		if ($raw) {
			if (strlen($message) > $this->msgtextlimit) {
				echo "error: msg big\n";
				return 0;
			}

			if (!$this->validateRawMsg($message)) {
				echo "invalid message: ".$msgid."\n";
				return 0;
			}
		}

		return $this->transport->saveMessage($msgid, $echo, $message, $raw);
	}

	function getMsgList($echo, $offset=NULL, $length=NULL) {
		if (!$this->checkEcho($echo)) return [];
		return $this->applyBlackList($this->transport->getMsgList($echo, $offset, $length));
	}

	static function validateRawMsg($message) {
		$msgparts = explode("\n", $message);
		if (count($msgparts) < 9) return false;

		$mesg = implode("\n", array_slice($msgparts, 8));
		if(strlen($mesg)==0) return false;

		for ($i=0;$i<7;$i++) {
			if(strlen($msgparts[$i])==0) {
				return false;
			}
		}
		return true;
	}

	function msgidCheck($msgid) {
		return (!$this->isBlackListed($msgid) and $this->checkHash($msgid));
	}

	function getRawMessage($msgid) {
		if ($this->msgidCheck($msgid)) return $this->transport->getRawMessage($msgid);
		else return "";
	}

	function getRawMessages($msgids) {
		$msgids_new=[];
		foreach ($msgids as $msgid) {
			if ($this->msgidCheck($msgid)) $msgids_new[]=$msgid;
		}
		return $this->transport->getRawMessages($msgids_new);
	}

	function getMessage($msgid) {
		if ($this->msgidCheck($msgid)) return $this->transport->getMessage($msgid);
		else return $transport->nomessage;
	}

	function getMessages($msgids) {
		$msgids_new=[];
		foreach ($msgids as $msgid) {
			if ($this->msgidCheck($msgid)) $msgids_new[]=$msgid;
		}
		return $this->transport->getMessages($msgids_new);
	}

	function countMessages($echo) {
		if (!$this->checkEcho($echo)) return 0;
		else return $this->transport->countMessages($echo);
	}
}

?>
