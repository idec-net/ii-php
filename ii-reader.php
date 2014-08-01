<?php
require("ii-frontend.php");

//поддержка ссылок и разметки
function reparse($string) {
	$pre_flag = false;
	$string = explode ("\n", $string);
	for ($i = 0; $i < count ($string); ++$i) {
		$string[$i] = preg_replace("/([^\w\/])(www\.[a-z0-9\-]+\.[a-z0-9\-]+)/i", "$1http://$2",$string[$i]);
		$string[$i] = preg_replace("/([\w]+:\/\/[\w-?&;#~=\.\/\@]+[\w\/])/i","<a target=\"_blank\" href=\"$1\">$1</a>",$string[$i]);
		$string[$i] = preg_replace("/([\w-?&;#~=\.\/]+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,3}|[0-9]{1,3})(\]?))/i","<a href=\"mailto:$1\">$1</a>",$string[$i]);
		$echo_check = preg_replace("/(.*)\<a target=\"_blank\" href=\"ii:\/\/(.+?)\"\>(.+?)\<\/a\>(.*)/", "$2", $string[$i]);
		if (checkEcho($echo_check)) {
			$string[$i] = preg_replace("/target=\"_blank\" href=\"ii:\/\/(.+?)\"/s", "class=\"iilink\" href=\"ii-reader.php?echo=$1\"", $string[$i]);
		} else {
			$string[$i] = preg_replace("/target=\"_blank\" href=\"ii:\/\/(.+?)\"/s", "class=\"iilink\" href=\"ii-reader.php?msgid=$1\"", $string[$i]);
		}
		if (preg_match("/^====\<br \/\>$/", $string[$i])) {
			if (!$pre_flag) {
				$pre_flag = true;
				$string[$i] = preg_replace("/====/", "<pre>====", $string[$i]);
			} else {
				$pre_flag = false;
				$string[$i] = preg_replace("/====/", "====</pre>", $string[$i]);
			}
		}
	}
	$string = implode($string);
	return $string;
}

//постраничная навигация
function lister($arr, $pnumber, $none, $ii_reader,$echoarea) {
	$echo="";
	$myaddr="?echo=".$echoarea;
	$all=count($arr);
	$page=(isset($_GET['page'])) ? (int)$_GET['page'] : 1;
	$num_pages=ceil($all/$pnumber);
	$start=$page*$pnumber-$pnumber;
	if ($page > $num_pages || $page < 1) { $page=1; $start=0; }
	
	if ($all) {
		//элементы выводятся в обратном порядке!!
		for ($i=$all-$start-1; $i>=$all-$start-$pnumber; $i--) {
			if (!isset($arr[$i])) break;
			$echo.= $ii_reader->printMsg($arr[$i])."\n";
		}
		$echo.= '<p>';
		for($pr = '', $i =1; $i <= $num_pages; $i++)
		{
			$echo.= $pr=(($i == 1 || $i == $num_pages || abs($i-$page) < 2) ? ($i == $page ? " [$i] " : ' <a href="'.$myaddr.'&page='.$i.'">'.$i.'</a> ') : (($pr == ' ... ' || $pr == '')? '' : ' ... '));
		}
		$echo.= '</p>';
	} else {
		$echo.=$none;
	}
	return $echo;
}

class IIReader extends IIFrontend {
	public $onPage;

	function __construct($echoareas,$onpage) {
		parent::__construct($echoareas,"echo/");
		$this->onPage=$onpage;

		$readertop=file_get_contents("reader/reader-top.html");
		$listtop=file_get_contents("reader/list-top.html");
		$htmlbottom=file_get_contents("reader/reader-bottom.html");
		
		if ($this->userSent("echoname")) {
			$echo=$this->userSent("echoname");
			$htmltop=str_replace("{header}",$echo,$readertop);
			echo $htmltop;

			$this->printMsgs($echo);
		} elseif ($this->userSent("msgid")) {
			$msgid=$this->userSent("msgid");
			$echo=$this->getMessageArray($msgid)['echo'];

			$htmltop=str_replace("{header}","<a href='?echo=$echo'>$echo</a>",$readertop);
			echo $htmltop;

			echo $this->printMsg($msgid);
		} else {
			$htmltop=str_replace("{header}","онлайн читалка",$listtop);
			echo $htmltop;

			$this->printEchos();
		}
		echo $htmlbottom;
	}

	function userSent($target) {
		if (
			isset($_GET['echo']) &&
			!empty ($_GET['echo'])
		) {
			$usersent="echoname";
			$ret=$_GET['echo'];
		}
		elseif (
			isset($_GET['msgid']) &&
			!empty ($_GET['msgid'])
		) {
			$usersent="msgid";
			$ret=$_GET['msgid'];
		}
		else {
			$usersent=null;
			$ret=null;
		}
		if ($target==$usersent) return $ret;
		else return false;
	}
	function printMsg($msgid) {
		$ret="";
		$message=$this->getMessageArray($msgid);

		if($message['repto']) {
			$ret.= "<div class='message-with-repto'>";
			$ret.= "<a class='subj' href='#".$message['repto']."'>".$message['subj']."</a> ";
		} else {
			$ret.= "<div class='message'>";
			$ret.= "<span class='subj'>".$message['subj']."</span> ";
		}
		$ret.= "<a name='".$msgid."' href='?msgid=$msgid'>#&nbsp;&nbsp;</a>";
		$ret.= "<span class='date'>".date("Y-m-d H:i:s", $message['time']). "</span>";
		$ret.= "<span class='sender'>".$message['from']." (".$message['addr'].") -> ".$message['to']."</span>\n";
		$ret.="<br /><br /><span class='msgtext'>".reparse($message['msg'])."</span>\n";

		$ret.="</div>";
		return $ret;
	}
	function printEchos() {
		$arr=$this->echoes;
		echo "<h3>Выберите эхоконференцию</h3>\n<ul>";
		foreach($arr as $echo) {
			if(!file_exists("echo/".$echo[0])) {
				$countmsgs=0;
			} else {
				$countmsgs=count(explode("\n", getecho($echo[0])));
			}
			echo "<li><a href='?echo=".$echo[0]."'>".$echo[0]."</a> - ".$echo[1]." - $countmsgs сообщений</li>";
		}
		echo "</ul>";
	}
	function printMsgs($echo) {
		$listMsgid=$this->getMsgList($echo);
		echo lister($listMsgid,$this->onPage,"<h3>Сообщения в этой эхоконференции отсутствуют</h3>", $this, $echo);
	}

}

$ii_reader=new IIReader($echolist,20);
?>
