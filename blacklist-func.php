<?php
$blacklist=getBlackList();

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

?>
