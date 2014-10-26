<?php
date_default_timezone_set("UTC");

require_once("config.php");
require_once("mysql-functions.php");

$blacklist=getBlackList();
$logmessages=[];
$savedMessages=[];

function getBlackList() {
	return file("blacklist.txt");
}

function isBlackListed($msgid) {
	global $blacklist;

	if(in_array($msgid."\n", $blacklist)) {
		return true;
	} else return false;
}

function applyBlacklist($echo) {
	global $blacklist;

	foreach($blacklist as $msgid) {
		$echo=str_replace($msgid,"",$echo);
	}
	return $echo;
}

function logm($str) {
	global $logmessages;
	echo $str;
	$logmessages[]=$str;
}

function writeLog() {
	global $logmessages,$logfile;
	@$fp=fopen($logfile, "w");
	@fputs($fp, implode("", $logmessages));
	@fclose($fp);
}

function checkHash($s) {
	if(!b64d($s)) {
		return false;
	} else return true;
}

function getmsg($t) { 
	$t = preg_replace("/[^a-zA-Z0-9]+/", "", $t); 
	if(!isBlackListed($t) && file_exists("msg/$t")) {
		return file_get_contents ("msg/$t");
	} else return "";
}

function getecho($t) { 
	$t = preg_replace("/[^a-z0-9!_.-]+/", "", $t); 
	return applyBlackList(@file_get_contents ("echo/$t"));
}

function b64c($s) {
	return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64d($s) {
	return base64_decode(str_pad(strtr($s, '-_', '+/'), strlen($s) % 4, '=', STR_PAD_RIGHT),true);
}

function hsh($s) {
	$s1 = b64c(hash("sha256",$s,true));
	$s1=str_replace("-","A",$s1);
	$s1=str_replace("_","z",$s1);
	return substr($s1,0,20);
}

function checkEcho($echo) {
	$filter='/^[a-z0-9_!.-]{1,60}\.\d{1,9}$/';
	if(!preg_match($filter,$echo)) return false;
	else return true;
}

function pointSend($msg,$authname,$addr) {
	$goodmsg=explode("\n",b64d($msg));
	
	$echo=$goodmsg[0];
	if(!checkEcho($echo)) die("error: wrong echo");
	
	$receiver=$goodmsg[1];
	$subj=$goodmsg[2];
	$rep=$goodmsg[4];
	$time=time();
	$norep=0;

	$othermsg="";

	for($i=5;$i<count($goodmsg);$i++) {
		if($i==(count($goodmsg)-1)) {
			$othermsg.=$goodmsg[$i];
		} else {
			$othermsg.=$goodmsg[$i]."\n";
		}
	}

	if(substr($rep,0,7)=="@repto:") {
		$repto=substr($rep,7);
	} else {
		$repto="";
	}
	
	if(!$repto) {
		$norep=1;
	}

	if($norep) {
		if(!empty($othermsg)) {
			$othermsg=$rep."\n".$othermsg;
		} else {
			$othermsg=$rep;
		}
	}

	if(empty($subj) or empty($othermsg)) {
		die("error: empty message or subject!");
	}

	$sent=msg_to_ii($echo,$othermsg,$authname,$addr,$time,$receiver,$subj,$repto);
	if($sent) {
		echo "msg ok:".$sent;
	}
}

function msg_to_ii($echo,$msg,$username,$addr,$time,$receiver,$subj,$repto) {
	if(!checkEcho($echo)) die("wrong echo");
	if($repto) {
		$repto="/repto/".$repto;
	}

	$msgwrite="ii/ok$repto
$echo
$time
$username
$addr
$receiver
$subj\n\n$msg";
	$msgid=hsh($msgwrite);

	return savemsg($msgid, $echo, $msgwrite);
}

function validatemsg($m) {
	$msgparts = explode("\n", $m);
	if (count($msgparts) < 9) return false;

	$mesg = implode("\n", array_slice($msgparts, 8));
	
	for($i=0;$i<7;$i++) {
		if(strlen($msgparts[$i])==0) {
			return false;
		}
	}
	if(strlen($mesg)==0) return false;
	return true;
}

function savemsg($h,$e,$t) {
	global $savemsgOverride;
	if (!validatemsg($t)) {
		logm("invalid message: ".$h."\n");
		return 0;
	}
	if(!checkEcho($e)) {
		logm("error: wrong echo ".$e."\n"); 
		return 0;
	}
	if(count($t)>$msgtextlimit) {
		logm("error: msg big\n");
		return 0;
	}
	if(isBlackListed($h)) {
		echo "error: msgid is blacklisted: ".$h."\n";
		return 0;
	}
	if(checkHash($h)) {
		if(!file_exists('msg/'.$h) or $savemsgOverride==true) {
			echo "message saved: ok\n";
			return $h;
		} else {
			logm("error: '".$h."' this message exists\n");
			return 0;
		}
	} else {
		logm("error: incorrect msgid\n");
		return 0;
	}
}

function saveToBase() {
	global $savedMessages,$usemysql,$mysqldata;
	if(!empty($savedMessages)) {
		if($usemysql) {
			// code for mysql group saving
		} else {
			foreach($savedMessages as $message) {
				$fp = fopen('msg/'.$message["hash"], 'wb'); fwrite($fp, $message["text"]); fclose($fp);
				$fp = fopen('echo/'.$message["echo"], 'ab'); fwrite($fp, $message["hash"]."\n"); fclose($fp);
			}
		}
	}
}

function displayEchoList($echos=false, $small=false) {
	header('content-type: text/plain; charset=utf-8');
	if(!$echos) {
		global $echolist;
		if(!$small) {
			foreach($echolist as $echo) {
				$countMessages=count(explode("\n",getecho($echo[0])));
				echo $echo[0].":".$countMessages.":".$echo[1]."\n";
			}
		} else {
			foreach($echolist as $echo) {
				echo $echo[0]."\n";
			}
		}
	} else {
		foreach($echos as $echo) {
			if(checkEcho($echo)) {
				echo $echo.":".count(explode("\n",getecho($echo)))."\n";
			}
		}
	}
}

?>
