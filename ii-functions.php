<?php
include_once("config.php");

function checkHash($s) {
	if(!b64d($s)) {
		return false;
	} else return true;
}

function getmsg($t) { 
	$t = preg_replace("/[^a-zA-Z0-9]+/", "", $t); 
	return @file_get_contents ("msg/$t");
}

function getecho($t) { 
	$t = preg_replace("/[^a-z0-9!_.-]+/", "", $t); 
	return @file_get_contents ("echo/$t");
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
	if(!preg_match($filter,$echo)) die("error: wrong echo!");
}

function pointSend($msg,$authname,$addr) {
	$goodmsg=explode("\n",b64d($msg));
	
	$echo=$goodmsg[0];
	checkEcho($echo);
	
	$receiver=$goodmsg[1];
	$subj=$goodmsg[2];
	$rep=$goodmsg[4];
	$addr="mira, ".$addr;
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

	$sent=msg_to_ii($echo,$othermsg,$authname,$addr,$time,$receiver,$subj,$repto);
	echo "msg ok:".$sent." <a href='/$echo'>$echo</a>";
}

function msg_to_ii($echo,$msg,$username,$addr,$time,$receiver,$subj,$repto) {
	checkEcho($echo);
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
	if(count($msgwrite)>64099) die("error:msg big!");

	$msgid=hsh($msgwrite);

	@$echofile=fopen("echo/".$echo,"a");
	@fputs($echofile,$msgid."\n"); fclose($echofile);
	@$msgfile=fopen("msg/".$msgid,"w");
	@fputs($msgfile,$msgwrite);
	
	fclose($msgfile);
	return $msgid;
}

function validatemsg($m) {
	$msgparts = explode("\n", $m);
	if (count($msgparts) < 9))
		return false;
	$tags = $msgparts[0];
	$area = $msgparts[1];
	$date = $msgparts[2];
	$from = $msgparts[3];
	$addr = $msgparts[4];
	$to = $msgparts[5];
	$subj = $msgparts[6];
	$mesg = join("\n", array_slice($msgparts, 8));
	if (strlen($area) == 0 || strlen($date) == 0 ||
	  strlen($from) == 0 || strlen($to) == 0 ||
	  strlen($subj) == 0 || strlen($mesg) == 0)
		return false;
	
	return true;
}

function savemsg($h,$e,$t) {
	global $savemsgOverride;
	if (!validatemsg($t)) {
		echo "invalid message: ".$h."\n";
		return;
	}
	checkEcho($e);
	if(checkHash($h)) {
		if(!file_exists('msg/'.$h) or $savemsgOverride==true) {
			$fp = fopen('msg/'.$h, 'wb'); fwrite($fp, $t); fclose($fp);
			$fp = fopen('echo/'.$e, 'ab'); fwrite($fp, "$h\n"); fclose($fp);
			echo "message saved: ok\n";
		} else {
			echo "error: '".$h."' this message exists\n";
		}
	} else echo "error: incorrect msgid\n";
}

?>
