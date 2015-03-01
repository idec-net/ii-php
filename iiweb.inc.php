<?php
// Todo: поменять постраничный вывод

require("ii-functions.php");
session_start();

class IIFrontend {
	public $echoes;
	public $echoesPath;
	public $nomessage=array(
		"echo" => "none",
		"time" => "0",
		"from" => "",
		"addr" => "",
		"to" => "",
		"subj" => "",
		"msg" => "no message",
		"repto" => false,
		"id" => ""
	);

	function __construct($echoes,$echoespath) {
		$this->echoes=$echoes;
		$this->echoesPath=$echoespath;
	}
	function getMessagesArray($msgids) {
		global $usemysql;
		if($usemysql) {
			$msgsArr=getMessages($msgids);
		} else {
			$msgsArr=[];
			foreach($msgids as $msgid) {
				$msgsArr[$msgid]=getmsg($msgid);
			}
		}
		return $msgsArr;
	}
	function parseMessage($plainMessage, $msgid) {
		$msgone=htmlspecialchars($plainMessage);
		$msg=explode("\n",$msgone);
		$meta=[];
		$tags=explode("/",$msg[0]);
		$newtags=[];

		for($i=0;$i<count($tags);$i+=2) {
			if(!empty($tags[$i+1])) {
				$newtags[$tags[$i]]=$tags[$i+1];
			} else {
				$newtags[$tags[$i]]=false;
			}
		}

		if(isset($newtags['repto'])) {
			$repto=$newtags['repto'];
		} else {
			$repto=false;
		}
		if(count($msg)>=8) {
			$meta=array(
				"echo" => $msg[1],
				"time" => $msg[2],
				"from" => $msg[3],
				"addr" => $msg[4],
				"to" => $msg[5],
				"subj" => $msg[6],
				"msg" => implode("<br />\n", array_slice($msg, 8)),
				"repto" => $repto,
				"id" => $msgid
			);
		} else {
			$meta=$this->nomessage;
		}
		
		return $meta;
	}
	function getMsgList($echo) {
		if(checkEcho($echo) && file_exists($this->echoesPath.$echo)) {
			$msgs=explode("\n",getecho($echo));
			array_pop($msgs);
		} else $msgs=[];
		return $msgs;
	}
}

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
			$string[$i] = preg_replace("/target=\"_blank\" href=\"ii:\/\/(.+?)\"/s", "class=\"iilink\" href=\"?echo=$1\"", $string[$i]);
		} else {
			$string[$i] = preg_replace("/target=\"_blank\" href=\"ii:\/\/(.+?)\"/s", "class=\"iilink\" href=\"?msgid=$1\"", $string[$i]);
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
function reparseSubj($str) {
	if(substr($str, 0, 3) != "Re:") {
		$str="Re: ".$str;
	}
	return $str;
}
// проверяет наличие данных от юзера
function checkData($str, $post=false) {
	if($post) $arr=$_POST;
	else $arr=$_GET;

	if (
		isset($arr[$str]) &&
		!empty ($arr[$str])
	) return true;
	else return false;
}
function generate_csrf_token() {
	return $_SESSION['csrf_token'] = substr(str_shuffle('qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM'), 0, 20);
}

class IIWeb extends IIFrontend {
	public $onPage;

	function __construct($echoareas, $tpldir, $onpage) {
		parent::__construct($echoareas,"echo/");
		$this->onPage=$onpage;
		
		$html=""; //html code of page

		$links=[
			'<a class="toplink" href="?">К списку эх</a>'
		];
		$header="";
		
		// шаблоны стилей вебморды
		$htmltop=file_get_contents($tpldir."/top.html");
		$writerform=file_get_contents($tpldir."/writer-form.html");
		$htmlbottom=file_get_contents($tpldir."/bottom.html");
		
		$html=$htmltop;
		
		// получаем и обрабатываем входные параметры и данные формы, если есть
		$remote=$this->fetchUserData();
		
		if(isset($_SESSION["userAuth"])) $links[]="<a class='toplink' href='?logout'>Выйти</a>";
		
		// если ошибок в проверке формы нет, отправляем сообщение в ii и даём эху на просмотр
		if ($remote["formdata-validated"]) {
			global $nodeName;
			$newmsg=$remote["message"];

			if ($remote["echoname"]) {
				$echo=$remote["echoname"];
				$repto=false;
				$receiver="All";
			} elseif($remote["msgid"]) {
				$msgid=$remote["msgid"];
				$output=$this->getMessagesArray([$msgid]);
				
				if (isset($output[$msgid])) {
					$message=$this->parseMessage($output[$msgid], $msgid);
				} else {
					$message=$this->nomessage;
				}
				$echo=$message['echo'];
				$repto=$msgid;
				$receiver=$message['from'];
			}
			$savedMessage=msg_to_ii($echo, $newmsg["msg"], $newmsg["pointname"], $nodeName.", ".$newmsg["addr"], $newmsg["time"], $receiver, $newmsg["subj"], $repto);
			
			$header=$echo;
			$links[]='<a class="toplink" href="?echo={header}">Обновить</a>';
			$links[]='<a class="toplink" href="?echo={header}&new">Новое</a>';
			$html.=$this->printMsgs($echo);
		} else {
			// иначе юзер хочет что-то посмотреть, либо что-то неправильно
			if ($remote["echoname"]) {
				$echo=$remote["echoname"];
	
				if($remote["writenew"]) {
					$header="<a class='toplink' href='?echo=$echo'>$echo</a>";
					$html.=$this->printForm($writerform, $echo);
				} else {
					$header=$echo;
					$links[]='<a class="toplink" href="?echo={header}">Обновить</a>';
					$links[]='<a class="toplink" href="?echo={header}&new">Новое</a>';
					$html.=$this->printMsgs($echo);
				}
			} elseif ($remote["msgid"]) {
				$msgid=$remote["msgid"];
				$output=$this->getMessagesArray([$msgid]);
	
				if (isset($output[$msgid])) {
					$message=$this->parseMessage($output[$msgid], $msgid);
				} else {
					$message=$this->nomessage;
				}
				$echo=$message['echo'];
				$header="<a class='toplink' href='?echo=$echo'>$echo</a>";

				if ($remote["reply"]) {
					$html.=$this->printForm($writerform, $message["echo"], $message["subj"], "Ответ", $this->printMsg($message, true), "");			} else {
					$html.=$this->printMsg($message);
				}
			} else {
				$header="веб-клиент";
				$links=[];
	
				$html.=$this->printEchos();
			}
		}
		// заканчиваем формировать html код страницы

		$html.=$htmlbottom;
		$menu_links="";
		foreach($links as $link) { $menu_links.=$link; }
		$html=str_replace("{links}", $menu_links, $html);
		$html=str_replace("{header}", $header, $html);
		
		$errortext=$remote["form-errors"] ? "<div class='message message-with-repto viewonly'>".$remote['form-errors']."</div>" : "";
		$passinput=(!isset($_SESSION["userAuth"])) ? '<input class="txt" type="password" placeholder="Строка авторизации" name="authstr" />' : "";

		$html=str_replace("{errors}", $errortext, $html);
		$html=str_replace("{passwd}", $passinput, $html);
		$html=str_replace("{token}", '<input name="csrf_token" type="hidden" value="'.generate_csrf_token().'" />', $html);

		echo $html;
	}

	function fetchUserData() {
		// это обработчик всех внешних данных (форм и сессий тоже!)
		$userDataArray=[
			"echoname" => null,
			"msgid" => null,
			"writenew" => false,
			"reply" => false,
			"formdata-validated" => false,
			"message" => null, // это будет массив!
			"form-errors" => null // а здесь строка
		];
		
		if (checkData("echo") && checkEcho($_GET['echo']))
		{
			if (isset($_GET["new"])) $userDataArray["writenew"]=true;
			$userDataArray["echoname"]=$_GET["echo"];
		
		} elseif(checkData("msgid") && checkHash($_GET["msgid"]))
		{
			if (isset($_GET["reply"])) $userDataArray["reply"]=true;
			$userDataArray["msgid"]=$_GET["msgid"];
		}
		
		if (isset($_GET["logout"])) unset($_SESSION["userAuth"]);

		if (checkData("a", true)) {
			// значит, юзер написал что-то в форму, проверяем
			$checkSubj=checkData("subj", true);
			$checkMsg=checkData("msgtext", true);
			
			// если authstr правильный, то ставим данные в сессию
			if (!isset($_SESSION["userAuth"])) {
				if (checkData("authstr", true))	$userAuth=checkUser($_POST["authstr"]);
				else $userAuth=false;
				
				if ($userAuth) $_SESSION["userAuth"]=$userAuth;
			} else $userAuth=$_SESSION["userAuth"];
			
			if($checkSubj) $userDataArray["message"]["subj"]=$_POST["subj"];
			else $userDataArray["form-errors"].="Не заполнена тема\n<br />";

			if($checkMsg) $userDataArray["message"]["msg"]=$_POST["msgtext"];
			else $userDataArray["form-errors"].="Не заполнено сообщение\n<br />";

			if(!$userAuth) $userDataArray["form-errors"].="Проверьте строку авторизации!\n<br />";
			
			// если всё правильно, даём добро на отправку сообщения
			if ( $checkSubj && $checkMsg && $userAuth &&
				($userDataArray["echoname"] or $userDataArray["msgid"]) &&
				isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] == @$_POST['csrf_token']
			) {
				$userDataArray["formdata-validated"]=true;
				$userDataArray["message"]["pointname"]=$userAuth[0];
				$userDataArray["message"]["addr"]=$userAuth[1];
				$userDataArray["message"]["time"]=time();
			}
		}
		return $userDataArray;
	}
	function printMsg($message, $viewonly=false) {
		$styleclass=$viewonly ? " viewonly" : "";
		$ret="";

		if($message['repto']) {
			$ret.= "<div class='message-with-repto$styleclass'>";
			$ret.= "<a class='subj' href='#".$message['repto']."'>".$message['subj']."</a> ";
		} else {
			$ret.= "<div class='message$styleclass'>";
			$ret.= "<span class='subj'>".$message['subj']."</span> ";
		}
		$ret.= "<a name='".$message['id']."' href='?msgid=".$message['id']."'>#</a>&nbsp;&nbsp;";
		$ret.= "<span class='date'>".date("Y-m-d H:i:s", $message['time']). "</span>";
		
		$ret.=$viewonly ? "<span class='sender'>" : "<a class='reply sender' href='?msgid=".$message['id']."&reply'>";
		$ret.= $message['from']." (".$message['addr'].") → ".$message['to'];
		$ret.=$viewonly ? "</span>\n" : "</a>";

		$ret.="<br /><br /><span class='msgtext'>".reparse($message['msg'])."</span>\n";

		$ret.="</div>";
		return $ret;
	}
	function printForm($writerform, $echo="", $subj="", $header="Новое сообщение", $msg="", $msgtext="") {
		$writerform=str_replace("{formheader}", $header, $writerform);
		$writerform=str_replace("{msg}", $msg, $writerform);
		
		if($subj) $subj=reparseSubj($subj);
		$writerform=str_replace("{subj}", $subj, $writerform);
		$writerform=str_replace("{msgtext}", $msgtext, $writerform);

		return $writerform;
	}
	function printEchos() {
		$text="";
		$arr=$this->echoes;
		$text.="<h3>Выберите эхоконференцию</h3>\n<ul>";
		foreach($arr as $echo) {
			if(!file_exists("echo/".$echo[0])) {
				$countmsgs=0;
			} else {
				$countmsgs=count(explode("\n", getecho($echo[0])))-1;
			}
			$text.="<li><a href='?echo=".$echo[0]."'>".$echo[0]."</a> - ".$echo[1]." - $countmsgs сообщений</li>";
		}
		$text.="</ul>";
		return $text;
	}
	function printMsgs($echo) {
		$output="";
		$arr=$this->getMsgList($echo);
		$pnumber=$this->onPage;
		
		//постраничная навигация
		$myaddr="?echo=".$echo;
		$all=count($arr);
		$page=(isset($_GET['page'])) ? (int)$_GET['page'] : 1;
		$num_pages=ceil($all/$pnumber);
		$start=$page*$pnumber-$pnumber;
		if ($page > $num_pages || $page < 1) { $page=1; $start=0; }
		
		if ($all) {
			//элементы выводятся в обратном порядке!!
			$msglist=[];
			for ($i=$all-$start-1; $i>=$all-$start-$pnumber; $i--) {
				if (!isset($arr[$i])) break;
				$msglist[]=$arr[$i];
			}
	
			$messages=$this->getMessagesArray($msglist);
			foreach($msglist as $msgid) {
				if(isset($messages[$msgid])) {
					$parsedMessage=$this->parseMessage($messages[$msgid], $msgid);
				} else {
					$parsedMessage=$this->nomessage;
				}
	
				$output.=$this->printMsg($parsedMessage)."\n";
			}
	
			$output.='<p id="nav">';
			for($pr = '', $i =1; $i <= $num_pages; $i++)
			{
				$output.=$pr=(($i == 1 || $i == $num_pages || abs($i-$page) < 2) ? ($i == $page ? " [$i] " : ' <a href="'.$myaddr.'&page='.$i.'">'.$i.'</a> ') : (($pr == ' ... ' || $pr == '')? '' : ' ... '));
			}
			$output.='</p>';
		} else {
			$output.="<h3>Сообщения в этой эхоконференции отсутствуют</h3>";
		}
		return $output;
	}
}
?>
