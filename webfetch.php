<?php
header('content-type: text/plain; charset=utf-8');
require("ii-functions.php");

function getfile($add) {
	echo "fetch ".$add."\n";
	return file_get_contents($add);
}
function getLocalEcho($echo) {
	if(!file_exists("echo/".$echo)) return [];
	else {
		$file=file_get_contents("echo/".$echo);
		$arr=explode("\n",$file);
		return $arr;
	}
}

function fetch_messages($config) {
	$echoesToFetch=array_slice($config,1);
	$adress=$config[0];

	foreach($echoesToFetch as $echo) {
		$localMessages=getLocalEcho($echo);

		$remoteMessages=array_slice(explode("\n",getfile($adress."u/e/$echo")),1);

		$difference=array_diff($remoteMessages, $localMessages);
		$difference2d=array_chunk($difference, 20);
		
		foreach ($difference2d as $diff) {
			echo $echo."\n";
			$impldifference=implode("/",$diff);
			$fullbundle=getfile($adress."u/m/$impldifference");
	
			$bundles=explode("\n",$fullbundle);
			foreach($bundles as $bundle) {
				$arr=explode(":",$bundle);
				if(isset($arr[1])) {
					$msgid=$arr[0]; $message=b64d($arr[1]);
					savemsg($msgid, $echo, $message);
				}
			}
		}
	}
}

?>
