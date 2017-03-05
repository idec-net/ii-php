<?php
require("ii-functions.php");
header ('Content-Type: text/plain; charset=utf-8');

function remote ($key, $post=true) {
	$arr=($post) ? $_POST : $_GET;
	return (isset($arr[$key]) && !empty($arr[$key]));
}

if (remote('q', false)) {
	$q = $_GET['q'];
	$opts = [];

	$all_options = explode('/', $q);
	foreach ($all_options as $option) {
		if (!empty($option)) $opts[]=$option;
	}

	$optc = count($opts);
	if ($optc == 0) die("error: can't parse GET arguments");
} else {
	die("error: please specify API query with 'q' GET parameter");
}

if ($opts[0] == 'blacklist.txt') {
	echo implode("\n", $access->blacklist);
}

elseif ($opts[0] == 'list.txt') {
	displayEchoList(null, $counter=true, $descriptions=true);
}
elseif ($optc < 2) die("error: wrong arguments");

elseif ($opts[0] == 'e') {
	echo implode("\n", $access->getMsgList($opts[1]))."\n";
}

elseif ($opts[0] == 'm') {
	echo $access->getRawMessage($opts[1]);
}

elseif ($opts[0] == 'u' and $opts[1] == 'm') {
	if ($optc == 2) die("error: where are your msgids?");

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
		$w_opts_count > 1 and
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
	if ($optc == 4) {
		$au=$opts[2];
		$ms=$opts[3];
	} elseif (remote('pauth') && remote('tmsg')) {
		$au=$_POST['pauth'];
		$ms=$_POST['tmsg'];
	} else die('error: wrong arguments');

	if (count($ms) > $postlimit) die("error: msg big!");

	if ($point=checkUser($au)) {
		$authname=$point[0];
		$addr=$point[1];

		pointSend($ms, $authname, $nodeName.", ".$addr);
	} else {
		die("error: no auth!");
	}
}

elseif ($opts[0] == 'u' and $opts[1] == 'push') {
	if (remote('nauth') && remote('upush') && remote('echoarea')) {
		if (!empty($pushpassword) && $_POST['nauth'] === $pushpassword) {
			$bundle=explode("\n", $_POST['upush']);
			foreach ($bundle as $line) {
				$pieces=explode(":", $line);
				if (count($pieces)==2) {
					$msgid=$pieces[0];
					$message=b64d($pieces[1]);
					$r=$access->saveMessage($msgid, $_POST['echoarea'], $message, $raw=true);
					if ($r) echo "message saved: ok: ".$msgid."\n";
					else echo "error saving message ".$msgid."\n";
				} else {
					echo "error: wrong data; continue...";
				}
			}
		} else die("error: no auth");
	} else die("error: wrong arguments");
}

elseif ($opts[0] == 'x' and $opts[1] == 'c') {
	$echos=array_slice($opts, 2);
	displayEchoList($echos, $counter=true, $descriptions=false);
}

elseif ($opts[0] == 'x' and $opts[1] == 'e' and remote('data')) {
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

elseif ($opts[0] == 'x' and $opts[1] == 'filelist') {
	if (remote('pauth')) {
		$authstr=$_POST['pauth'];
	} elseif ($optc == 3) {
		$authstr=$opts[2];
	} else $authstr=false;

	$filenames=array_keys($public_files);
	$files_info=$public_files;

	if ($authstr!=false && checkUser($authstr) != false) {
		$private_filenames=array_keys($private_files);
		$filenames=array_merge($filenames, $private_filenames);
		$files_info=array_merge($files_info, $private_files);
	}

	foreach ($filenames as $filename) {
		if (@file_exists($files_directory."/".$filename)) {
			echo $filename.":".filesize($files_directory."/".$filename).":".$files_info[$filename]."\n";
		}
	}
}

elseif ($opts[0] == 'x' and $opts[1] == 'file') {
	$filenames=array_keys($public_files);
	$private_filenames=array_keys($private_files);

	if (remote('pauth'))
		$authstr=$_POST['pauth'];
	elseif ($optc == 4)
		$authstr=$opts[2];
	else $authstr=false;

	if (remote('filename')) {
		$request_file=$_POST['filename'];
	} elseif ($optc == 4) {
		$request_file=$opts[3];
	} elseif ($optc == 3) {
		$request_file=$opts[2];
	} else die("error: specify file name");

	if ($authstr!=false && checkUser($authstr) != false) {
		// значит юзер "свой", и ему можно качать "скрытые" файлы
		$filenames=array_merge($filenames, $private_filenames);
	}

	$file_path=$files_directory."/".$request_file;

	if (in_array($request_file, $filenames) && @file_exists($file_path)) {
		// выдаём файл
		header ('Content-Type: application/octet-stream');
		header ('Content-Disposition: attachment; filename="'.$request_file.'"');
		if (ob_get_level()) ob_end_clean();

		@readfile($file_path);
		exit();
	} else echo "error: file does not exist or wrong authstr";
}

elseif ($opts[0] == 'x' and $opts[1] == 'features') {
	// пишем, какие дополнительные фичи умеет данная нода

	echo "u/e\nu/push\nlist.txt\nblacklist.txt\nx/c\nx/file\nx/filelist\nx/small-echolist";
}

else {
	echo "error: wrong api calls";
}

?>
