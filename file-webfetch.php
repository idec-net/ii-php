<?php
header('content-type: text/plain; charset=utf-8');
require("ii-functions.php");

function getfile_ram($add) {
	echo "fetch ".$add."\n";
	return file_get_contents($add);
}

function parseFullFEchoList($echobundle) {
	global $file_access;
	$echos2d=array();
	$lastecho="";

	for($i=0;$i<count($echobundle);$i++) {
		if(!empty($echobundle[$i])) {
			$search=strpos($echobundle[$i], ":");
		
			if($search===false) {
				$lastecho=$echobundle[$i];
				$echos2d[$lastecho]=array();
			} else {
				$echos2d[$lastecho][]=$echobundle[$i];
			}
		}
	}

	return $echos2d;
}

function parseEntries($list) {
	global $file_access;
	foreach ($list as &$entry) {
		$entry = $file_access -> transport -> parseFileEntry(
			$entry, $size=true, $remote_size=true);
	}

	return $list;
}

function fetch_files($config, $fetch_limit=false, $force_download_files=[]) {
	global $file_access;

	$address = $config[0];
	$echoesToFetch=array_slice($config,1);

	$bundleAdress=$address."f/e/".implode("/", $echoesToFetch);
	$bundleAdress.=($fetch_limit) ? "/-".$fetch_limit.":".$fetch_limit : "";

	$echoBundle=explode("\n", getfile_ram($bundleAdress));
	$remoteEchos2d=parseFullFEchoList($echoBundle);

	foreach($echoesToFetch as $echo) {
		$localMessages=$file_access->getRawFileList($echo);
		$remoteMessages=$remoteEchos2d[$echo];

		$difference=array_diff($remoteMessages, $localMessages);
		$difference=parseEntries($difference);

		foreach ($difference as $target) {
			$size = intval($target["size"]) + 1024;
			$hash = $target["id"];
			$filename = $target["filename"];

			echo "$echo : $filename\n";

			$fuckcontinue = 0;
			foreach (parseEntries($localMessages) as $msg) {
				if (isset($msg["id"]) && $msg["id"] == $hash) {
					echo "file already exists ".$hash."\n";
					$fuckcontinue = 1;
				}
			}
			if ($fuckcontinue) continue;
			$check = $file_access -> saveFile($hash, $echo, ["size" => $size],
				$filename, $target["address"], $target["desc"], $check_only=true);

			if ($check===true or array_search($hash, $force_download_files) != false) {
				exec("echo ".$address."f/f/".$echo."/".$hash." | wget -Q".$size." -i - -O ".$file_access->transport->filedir."/".$hash);
				$raw_entry = $hash . ":" . $filename . "::" . $target["address"] . ":" . $target["desc"];
				$file_access -> transport -> appendMsgList($echo, [$raw_entry]);
			}
		}
	}
}

?>
