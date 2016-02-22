<?php
require("ii-functions.php");
header ('Content-Type: text/plain; charset=utf-8');

if(isset($_GET['q'])) {
	$q = $_GET['q'];
	$opts = [];

	$all_options = explode('/', $q);
	foreach ($all_options as $option) {
		if (!empty($option)) $opts[]=$option;
	}

	$optc = count($opts);
} else {
	exit();
}

$auth=0;
$authname=0;

if ($opts[0] == 'e') {
	echo implode("\n", $transport->getMsgList($opts[1]))."\n";
}

if ($opts[0] == 'm') {
	echo $transport->getRawMessage($opts[1]);
}

if ($opts[0] == 'u' and $opts[1] == 'm') {
	$msgids=array_slice($opts, 2);
	$messages=$transport->getRawMessages($msgids);

	foreach($messages as $msgid => $text) {
		echo $msgid.":".b64c($text)."\n";
	}
}

if ($opts[0] == 'u' and $opts[1] == 'e') {
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
			$slice = $transport->getMsgList($echo, $a, $b);

			if (count($slice) > 0) {
				$buffer.=$echo."\n".implode("\n", $slice)."\n";
			} else {
				$buffer.=$echo."\n";
			}
		}
		echo $buffer;

	} else {
		foreach($work_options as $echo) { 
			echo $echo."\n".implode("\n", $transport->getMsgList($echo))."\n";
		}
	}
}

if ($opts[0] == 'u' and $opts[1] == 'point') {
	$error=0;
	if (isset($opts[2]) && isset($opts[3]) &&
		$opts[2] && $opts[3]
	) {
		$au=$opts[2];
		$ms=$opts[3];
	} elseif($_POST['pauth'] && $_POST['tmsg']) {
		$au=$_POST['pauth'];
		$ms=$_POST['tmsg'];
	} else $error=1;
	$addr=0;
	if(count($ms)>$postlimit) die("error:msg big!");
	if(!$error) {
		$pointCheck=checkUser($au);
		if($pointCheck) {
			$auth=$au;
			$authname=$pointCheck[0];
			$addr=$pointCheck[1];
		}
		
		if($auth and $authname) {
			pointSend($ms,$authname,$nodeName.", ".$addr);
		} else {
			die("error: no auth!");
		}
	} else die('error: unknown');
}

if($opts[0] == 'blacklist.txt') {
	echo implode("", $blacklist);
}

if($opts[0] == 'list.txt') {
	displayEchoList(null, $counter=true, $descriptions=true);
}

if($opts[0] == 'x' and $opts[1] == 'c') {
	$echos=[];
	for ($x=2;$x<$optc;$x++) {
		$echos[]=$opts[$x];
	}
	displayEchoList($echos, $counter=true, $descriptions=false);
}

if($opts[0] == 'x' and $opts[1] == 'e' and !empty($_POST['data'])) {
	$lines=explode("\n", $_POST['data']);
	foreach ($lines as $line) {
		$line=explode(":", $line);
		if (count($line)!=2) continue;

		$echoarea=trim($line[0]);
		$msgid=trim($line[1]);

		$index=$transport->getMsgList($echoarea);
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

if($opts[0] == 'x' and $opts[1] == 'file') {
	$filenames=array_keys($public_files);
	
	if(
		!empty($_POST['pauth']) &&
		checkUser($_POST['pauth']) != false
	) {
		// значит юзер "свой", и ему можно качать файлы

		if (
			!empty($_POST['filename'])
		) {
			// значит пользователь запросил файл

			if (
				in_array($_POST['filename'], $filenames)
			) {
				// выдаём файл
				if (ob_get_level()) {
					ob_end_clean();
				}
				$file_path=$files_directory."/".$_POST['filename'];

				@readfile($file_path);
				exit();
			} else {
				echo "error: file does not exist";
			}
		} else {
			// иначе выдаём список файлов

			foreach ($filenames as $filename) {
				if (@file_exists($files_directory."/".$filename)) {
					echo $filename.":".filesize($files_directory."/".$filename).":".$public_files[$filename]."\n";
				}
			}
		}
	} else {
		echo "error: no auth";
	}
}

if ($opts[0] == 'x' and $opts[1] == 'features') {
	// пишем, какие дополнительные фичи умеет данная нода

	echo "u/e\nu/push\nlist.txt\nblacklist.txt\nx/c\nx/file\nx/small-echolist";
}

?>
