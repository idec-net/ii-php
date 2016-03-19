<?php
require("ii-functions.php");
header ('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['q'])) {
	$q = $_GET['q'];
	$opts = [];

	$all_options = explode('/', $q);
	foreach ($all_options as $option) {
		if (!empty($option)) $opts[]=$option;
	}

	$optc = count($opts);
	if ($optc == 0) exit();
} else {
	exit();
}

$auth=0;
$authname=0;

if ($opts[0] == 'e') {
	echo implode("\n", $access->getMsgList($opts[1]))."\n";
}

elseif ($opts[0] == 'm') {
	echo $access->getRawMessage($opts[1]);
}

elseif ($opts[0] == 'blacklist.txt') {
	echo implode("\n", $access->blacklist);
}

elseif ($opts[0] == 'list.txt') {
	displayEchoList(null, $counter=true, $descriptions=true);
}

if ($optc < 2) exit();

if ($opts[0] == 'u' and $opts[1] == 'm') {
	$msgids=array_slice($opts, 2);
	$messages=$access->getRawMessages($msgids);

	foreach($messages as $msgid => $text) {
		echo $msgid.":".base64_encode($text)."\n";
	}
}

elseif ($opts[0] == 'u' and $opts[1] == 'e') {
	$work_options=array_slice($opts, 2);
	$w_opts_count=count($work_options);
	
	if (
		count($work_options > 1) and
		strstr($work_options[$w_opts_count-1], ":")!==false
	) {
		$buffer="";
		$numbers=explode(":", $work_options[$w_opts_count-1]);
		
		$a=intval($numbers[0]);
		$b=intval($numbers[1]);
		
		$echoareas=array_slice($work_options, 0, $w_opts_count-1);
		$messages=[];

		foreach ($echoareas as $echo) {
			$slice = $access->getMsgList($echo, $a, $b);

			if (count($slice) > 0) {
				$buffer.=$echo."\n".implode("\n", $slice)."\n";
			} else {
				$buffer.=$echo."\n";
			}
		}
		echo $buffer;

	} else {
		foreach($work_options as $echo) { 
			echo $echo."\n".implode("\n", $access->getMsgList($echo))."\n";
		}
	}
}

elseif ($opts[0] == 'u' and $opts[1] == 'point') {
	if (isset($opts[2]) && isset($opts[3]) &&
		$opts[2] && $opts[3]
	) {
		$au=$opts[2];
		$ms=$opts[3];
	} elseif (
		isset($_POST['pauth']) && isset($_POST['tmsg']) &&
		$_POST['pauth'] && $_POST['tmsg']
	) {
		$au=$_POST['pauth'];
		$ms=$_POST['tmsg'];
	} else die('error: wrong arguments');
	$addr=0;
	if(count($ms)>$postlimit) die("error: msg big!");
	$pointCheck=checkUser($au);
	if($pointCheck) {
		$auth=$au;
		$authname=$pointCheck[0];
		$addr=$pointCheck[1];
	}
	if($auth and $authname) {
		pointSend($ms, $authname, $nodeName.", ".$addr);
	} else {
		die("error: no auth!");
	}
}

elseif ($opts[0] == 'u' and $opts[1] == 'push') {
	if (
		isset($_POST['nauth']) && isset($_POST['upush']) && isset($_POST['echoarea'])
	) {
		global $pushpassword;
		if (!empty($pushpassword) && $_POST['nauth'] == $pushpassword) {
			$bundle=explode("\n", $_POST['upush']);
			foreach ($bundle as $line) {
				$pieces=explode(":", $line);
				if (count($pieces)==2) {
					$msgid=$pieces[0];
					$message=b64d($pieces[1]);
					$r=$access->saveMessage($msgid, $_POST['echoarea'], $message, $raw=true);
					if ($r) echo "message saved: ok: ".$msgid."\n";
				} else {
					echo "error: wrong data; continue...";
				}
			}
		} else die("error: no auth");
	} else die("error: wrong arguments");
}

elseif ($opts[0] == 'x' and $opts[1] == 'c') {
	$echos=[];
	for ($x=2;$x<$optc;$x++) {
		$echos[]=$opts[$x];
	}
	displayEchoList($echos, $counter=true, $descriptions=false);
}

elseif ($opts[0] == 'x' and $opts[1] == 'e' and !empty($_POST['data'])) {
	$lines=explode("\n", $_POST['data']);
	foreach ($lines as $line) {
		$line=explode(":", $line);
		if (count($line)!=2) continue;

		$echoarea=trim($line[0]);
		$msgid=trim($line[1]);

		$index=$access->getMsgList($echoarea);
		$maxElement=count($index)-1;

		$search=array_search($msgid, $index);
		if ($search!=NULL and $search<$maxElement) {
			$newMessages=array_slice($index, $search+1);
			echo $echoarea."\n".implode("\n", $newMessages)."\n";
		} elseif ($search==$maxElement) {
			continue;
		} else {
			echo $echoarea."\n".$msgids;
		}
	}
}

elseif ($opts[0] == 'x' and $opts[1] == 'file') {
	$filenames=array_keys($public_files);
	$private_filenames=array_keys($private_files);

	$files_info=$public_files;

	if (isset($_POST['pauth']) && !empty ($_POST['pauth'])) {
		$authstr=$_POST['pauth'];
	} elseif (isset($opts[2]) && !empty($opts[2])) {
		$authstr=$opts[2];
	} else $authstr=false;

	if (isset($_POST['filename']) && !empty ($_POST['filename'])) {
		$request_file=$_POST['filename'];
	} elseif (isset($opts[3]) && !empty($opts[3])) {
		$request_file=$opts[3];
	} else $request_file=false;

	if ($authstr!=false && checkUser($authstr) != false) {
		// значит юзер "свой", и ему можно качать "скрытые" файлы
		$filenames=array_merge($filenames, $private_filenames);
		$files_info=array_merge($files_info, $private_files);
	}

	if ($request_file) {
		// значит пользователь запросил файл

		if (
			in_array($request_file, $filenames)
		) {
			// выдаём файл
			if (ob_get_level()) {
				ob_end_clean();
			}
			$file_path=$files_directory."/".$request_file;

			@readfile($file_path);
			exit();
		} else {
			echo "error: file does not exist or wrong authstr";
		}
	} else {
		// иначе выдаём список файлов

		foreach ($filenames as $filename) {
			if (@file_exists($files_directory."/".$filename)) {
				echo $filename.":".filesize($files_directory."/".$filename).":".$files_info[$filename]."\n";
			}
		}
	}
}

elseif ($opts[0] == 'x' and $opts[1] == 'features') {
	// пишем, какие дополнительные фичи умеет данная нода

	echo "u/e\nu/push\nlist.txt\nblacklist.txt\nx/c\nx/file\nx/small-echolist";
}

?>
