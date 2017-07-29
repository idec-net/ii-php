<?php
class BaseAccess {
	function __construct($transport, $blacklist_file, $msgtextlimit) {
		$this->msgtextlimit=$msgtextlimit;
		$this->blacklist_file=$blacklist_file;
		$this->blacklist=$this->getBlackList($blacklist_file);
		$this->transport=$transport;
	}

	function getBlackList($blacklist_file) {
		if (file_exists($blacklist_file)) {
			$file=file_get_contents($blacklist_file);
			$blacklist=explode("\n", $file);
			return $blacklist;
		} else return [];
	}

	function isBlackListed($msgid) {
		if(in_array($msgid, $this->blacklist)) {
			return true;
		} else return false;
	}

	function applyBlacklist($echo) {
		$list=[];
		foreach($echo as $msgid) {
			if (!$this->isBlackListed($msgid)) $list[]=$msgid;
		}
		return $list;
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
		return $this->applyBlacklist($this->transport->getMsgList($echo, $offset, $length));
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
		else return $this->transport->nomessage;
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

class FileAccess implements AbstractFileTransport {
	function __construct($transport, $blacklist_file, $filesize_limit, $max_dir_quota) {
		$this->filesize_limit=$filesize_limit;
		$this->max_dir_quota=$max_dir_quota;
		$this->blacklist_file=$blacklist_file;
		$this->blacklist=$this->getBlackList($blacklist_file);
		$this->transport=$transport;
	}

	function getBlackList($blacklist_file) {
		if (file_exists($blacklist_file)) {
			$file=file_get_contents($blacklist_file);
			$blacklist=explode("\n", $file);
			return $blacklist;
		} else return [];
	}

	function isBlackListed($msgid) {
		if(in_array($msgid, $this->blacklist)) {
			return true;
		} else return false;
	}

	function applyBlacklist($fecho) {
		$list=[];
		foreach($fecho as $entry) {
			if (!$this->isBlackListed($entry["id"])) $list[]=$entry;
		}
		return $list;
	}

	function applyBlacklistToRaw($fecho) {
		foreach($fecho as &$entry) {
			$spl = explode(":", $entry);
			$id = $spl[0];

			if ($this->isBlackListed($id)) {
				unset($entry);
			}
		}
		return $fecho;
	}

	static function checkHash($s) {
		if(!b64d($s)) {
			return false;
		} else return true;
	}

	static function checkEcho($echo) {
		$filter='/^[a-z0-9_!-.]{1,120}$/';
		if(!preg_match($filter,$echo)) return false;
		else return true;
	}

	function saveFile($hash=NULL, $fecho, $file, $filename, $address, $description, $check_only=false) {
		if (!$this->checkEcho($fecho)) {
			echo "error: wrong fecho\n";
			return 0;
		}

		if ($hash != NULL) {
			if (!$this->msgidCheck($hash)) {
				echo "error: bad file id\n";
				return 0;
			}

			if ($this->isBlackListed($hash)) {
				echo "error: file is blacklisted: ".$hash."\n";
				return 0;
			}
		}

		if ($file["size"] > $this->filesize_limit) {
			echo "error: file size is too large\n";
			return 0;
		}

		if ($file["size"] + $this->dirSize($this->transport->filedir) > $this->max_dir_quota) {
			echo "error: node storage quota exceeded, stop saving\n";
			return 0;
		}

		if (!BaseAccess::checkEcho($filename)) {
			echo "error: wrong file name\n";
			return 0;
		}

		if (strlen($description) > 1024) {
			echo "error: description maximum size is 1024\n";
			return 0;
		}

		if (strlen($address) > 120) {
			echo "error: address maximum size is 120\n";
			return 0;
		}

		if (strpos($address, "\n") !== false
			or strpos($address, "\r") !== false
			or strpos($description, "\n") !== false
			or strpos($description, "\r") !== false
		) {
			echo "error: wrong address or description\n";
			return 0;
		}

		if ($check_only) return true;
		else return $this->transport->saveFile($hash, $fecho, $file, $filename, $address, $description);
	}

	function dirSize($directory) {
		$size = 0;

		try {
			foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
				$size+=$file->getSize();
			}
		} catch (Exception $e) {
			echo "error: " . $e->getMessage() . "\n";
		}
		return $size;
	}

	public function updateInfo($hash, $fecho, $filename, $address, $description) {
		if ($this->msgidCheck($hash) && $this->checkEcho($fecho)) return $this->transport->updateInfo($hash, $fecho, $filename, $address, $description);
		else return false;
	}

	function getFileList($fecho, $offset=NULL, $length=NULL, $size=false) {
		if (!$this->checkEcho($fecho)) return [];
		return $this->applyBlacklist($this->transport->getFileList($fecho, $offset, $length, $size));
	}

	function getRawFileList($fecho, $offset=NULL, $length=NULL, $size=false) {
		if (!$this->checkEcho($fecho)) return [];
		return $this->applyBlacklistToRaw($this->transport->getRawFileList($fecho, $offset, $length, $size));
	}

	function msgidCheck($msgid) {
		return (!$this->isBlackListed($msgid) and $this->checkHash($msgid));
	}

	public function deleteFile($hash, $echo=NULL) {
		return $this->transport->deleteFile($hash, $echo);
	}

	public function deleteFileEchoarea($fecho, $with_contents=true) {
		return $this->transport->deleteFileEchoarea($fecho, $with_contents);
	}

	public function getFullFilename($hash) {
		if ($this->msgidCheck($hash)) return $this->transport->getFullFilename($hash);
		else return null;
	}

	public function fullFechoList() {
		return $this->transport->fullFechoList();
	}
	public function countFiles($echo) {
		if ($this->checkEcho($echo)) return $this->transport->countFiles($echo);
		else return null;
	}
}

?>
