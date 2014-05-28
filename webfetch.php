<?php
require("ii-functions.php");

function getf($l) {
	echo "fetch $l\n";
	return file_get_contents($l);
}
function get_echoarea($name) {
	if(!file_exists("echo/".$name)) {
		touch("echo/".$name);
		return [];
	} else {
		return explode("\n", file_get_contents("echo/".$name));
	}
}
function sep($l, $step=20) {
	for($x=0;$x<count($l);$x+=$step) {
		yield array_slice($l,$x,$x+$step);
	}
}

function debundle($ea,$s) {
	foreach(explode("\n",$s) as $n) {
		$arr = explode(':',$n,2);
		$mid=$arr[0]; $kod=$arr[1];
		if ($mid!=="\n" & $mid !== "") {
			file_put_contents('msg/'.$mid, b64d($kod));
			file_put_contents('echo/'.$ea, $mid."\n", FILE_APPEND);
		}
	}
}
function walk_el($out) {
	$ea = ''; $el = [];
	foreach(explode("\n", $out) as $n) {
		if (substr_count($n, ".")>0) {
			$ea = $n;
			$el[$ea] = [];
		}
		elseif($ea) {
			$el[$ea][]=$n;
		}
	}
	return $el;
}
function parse() {
	global $cfg;
	$out = getf($cfg[1]."u/e/".implode("/", array_slice($cfg, 2)));
	$el = walk_el($out);
	foreach(array_slice($cfg, 2) as $ea) {
		$myel = array_unique(get_echoarea($ea));
		$dllist=[];
		foreach($el[$ea] as $x) {
			$search=array_search($x, $myel);
			$len=count($myel);
			if((!$search && $search!==0) xor (count($el[$ea])==1)) {
				$dllist[]=$x;
			}
		}
		
		foreach(sep($dllist,40) as $dl) {
			$s = getf($cfg[1]."u/m/".implode("/",$dl));
			debundle($ea,$s);
		}
	}
}
parse();
?>
