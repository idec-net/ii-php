<?php

function fe($s) { return preg_replace("/[^a-z0-9!_.-]+/", "", $s); }
function fm($s) { return preg_replace("/[^a-zA-Z0-9]+/", "", $s); }

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
	return base64_decode(str_pad(strtr($s, '-_', '+/'), strlen($s) % 4, '=', STR_PAD_RIGHT));
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

	$msgid=hsh($msg);
	
	$echo=$goodmsg[0];
	checkEcho($echo);
	
	$receiver=$goodmsg[1];
	$subj=$goodmsg[2];
	$rep=$goodmsg[4];
	$addr="mira, ".$addr;
	$repto="";
	$time=time();

	$othermsg="";
	$msgwrite="";

	for($i=5;$i<count($goodmsg);$i++) {
		if($i==(count($goodmsg)-1)) {
			$othermsg.=$goodmsg[$i];
		} else {
			$othermsg.=$goodmsg[$i]."\n";
		}
	}
	if(substr($rep,0,7)=="@repto:") {
		$repto=substr($rep,7);
	}
	
	if($repto) {
		$msgwrite.="ii/ok/repto/$repto\n";
	} else {
		$msgwrite.="ii/ok\n";
		$norep=1;
	}

	$msgwrite.="$echo
$time
$authname
$addr
$receiver
$subj\n\n";

	if(!$norep) {
		$msgwrite.=$othermsg;
	} else {
		$msgwrite.=$rep."\n".$othermsg;
	}

	@$echofile=fopen("echo/".$echo,"a");
	@fputs($echofile,$msgid."\n"); fclose($echofile);
	@$msgfile=fopen("msg/".$msgid,"w");
	@fputs($msgfile,$msgwrite); fclose($msgfile);
	echo "msg ok:".$msgid." <a href='/$echo'>$echo</a>";
}

function msg_to_ii($echo,$msg,$username,$addr,$time,$receiver,$subj,$repto) {
	checkEcho($echo);
	if($repto) {
		$repto="/repto/".$repto;
	}

	$msgwrite.="ii/ok$repto
$echo
$time
$username
$addr
$receiver
$subj\n\n$msg";

	$msgid=hsh($msgwrite);

	@$echofile=fopen("echo/".$echo,"a");
	@fputs($echofile,$msgid."\n"); fclose($echofile);
	@$msgfile=fopen("msg/".$msgid,"w");
	@fputs($msgfile,$msgwrite); fclose($msgfile);
	return $msgid;
}

function savemsg($h,$e,$t) {
	$fp = fopen('msg/'.fm($h), 'wb'); fwrite($fp, $t); fclose($fp);
	$fp = fopen('echo/'.fe($e), 'ab'); fwrite($fp, "$h\n"); fclose($fp);
}

?>
