<?php
require("ii-frontend.php");

// постраничная навигация

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

		$htmlbottom=file_get_contents("reader/reader-bottom.html");

		if(!$this->userSent("echoname")) {
			$htmltop=file_get_contents("reader/list-top.html");
			$htmltop=str_replace("{header}","онлайн читалка",$htmltop);
			echo $htmltop;

			$this->printEchos();
		}
		else {
			$htmltop=file_get_contents("reader/reader-top.html");
			$echo=$this->userSent("echoname");
			$htmltop=str_replace("{header}",$echo,$htmltop);
			echo $htmltop;

			$this->printMsgs($echo);
		}
		echo $htmlbottom;
	}

	function checkLogin() {
		if(isset($fucking_login)) return $fucking_login;
		else return false;
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
			$ret=$_GET['echo'];
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
			$ret.= "<span class='subj'>".$message['subj']."</span>";
		}
		$ret.= "<a name='".$msgid."'>&nbsp;&nbsp;</a>";
		$ret.= "<span class='date'>".date("Y-m-d h:i:s", $message['time']). "</span>";
		$ret.= "<span class='sender'>".$message['from']." (".$message['addr'].") -> ".$message['to']."</span>\n";
		$ret.="<br /><br /><span class='msgtext'>".$message['msg']."</span>\n";

		$ret.="</div>";
		return $ret;
	}
	function printEchos() {
		$arr=$this->echoes;
		echo "<h3>Выберите эхоконференцию</h3>\n<ul>";
		foreach($arr as $echo) {
			if(!file_exists("echo/$echo")) {
				$countmsgs=0;
			} else {
				$countmsgs=count(file($this->echoesPath.$echo));
			}
			echo "<li><a href='?echo=".$echo."'>".$echo."</a> - $countmsgs сообщений</li>";
		}
		echo "</ul>";
	}
	function printMsgs($echo) {
		$listMsgid=$this->getMsgList($echo);
		echo lister($listMsgid,$this->onPage,"<h3>Сообщения в этой эхоконференции отсутствуют</h3>", $this, $echo);
	}

}

$echoes=[
	"im.100",
	"ii.dev.14",
	"lor-opennet.2014",
	"obsd.rss.14",
	"obsd.talk.14",
	"game.rogue.14",
	"ru.humor.14",
	"vit01.2014"
];

$ii_reader=new IIReader($echoes,20);
?>
