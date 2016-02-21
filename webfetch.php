<?php
header('content-type: text/plain; charset=utf-8');
require("ii-functions.php");

function getfile($add) {
	echo "fetch ".$add."\n";
	return file_get_contents($add);
}
function getLocalEcho($echo) {
	$file=getecho($echo);
	if (!$file) return [];

	$arr=explode("\n", $file);
	return $arr;
}

function parseFullEchoList($echobundle) {
	$echos2d=array();
	$echobundle=explode("\n",$echobundle);
	$lastecho="";

	for($i=0;$i<count($echobundle);$i++) {
		if(!empty($echobundle[$i])) {
			$search=strpos($echobundle[$i], ".");
		
			if($search===false) {
				$echos2d[$lastecho][]=$echobundle[$i];
			} else {
				$lastecho=$echobundle[$i];
				$echos2d[$lastecho]=array();
			}
		}
	}
	return $echos2d;
}

function fetch_messages($config, $one_request_limit=20, $fetch_limit=false, $xcenable=false) {
	// xcenable - not implemented, fetch_limit - only N messages at time
	$echoesToFetch=array_slice($config,1);
	$adress=$config[0];

	$bundleAdress=$adress."u/e/".implode("/", $echoesToFetch);
	($fetch_limit!=false) ? $bundleAdress.="/-".intval($fetch_limit).":".intval($fetch_limit) : false;

	$echoBundle=getfile($bundleAdress);
	$remoteEchos2d=parseFullEchoList(applyBlackList($echoBundle));

	foreach($echoesToFetch as $echo) {
		$localMessages=getLocalEcho($echo);
		
		$remoteMessages=$remoteEchos2d[$echo];

		$difference=array_diff($remoteMessages, $localMessages);
		$difference2d=array_chunk($difference, $one_request_limit);
		
		foreach ($difference2d as $diff) {
			echo $echo."\n";
			$impldifference=implode("/",$diff);
			$fullbundle=getfile($adress."u/m/$impldifference");
	
			$bundles=explode("\n",$fullbundle);
			foreach($bundles as $bundle) {
				$arr=explode(":",$bundle);
				if(!empty($arr[0])) {
					$msgid=$arr[0]; $message=b64d($arr[1]);
					$hash=savemsg($msgid, $echo, $message);
					if($hash) {
						echo "message saved: ok\n";
					}
				}
			}
		}
	}
}

?>
