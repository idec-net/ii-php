<?php
require("ii-functions.php");

ini_set("session.gc_maxlifetime", $session_lifetime);
ini_set("session.cookie_lifetime", $session_lifetime);

session_set_cookie_params($session_lifetime);
session_start();

//поддержка ссылок и разметки
function reparse($string) {
	global $access;
	$pre_flag = false;
	$string = explode ("\n", $string);
	for ($i = 0; $i < count ($string); ++$i) {
		$string[$i] = preg_replace("/([^\w\/])(www\.[a-z0-9\-]+\.[a-z0-9\-]+)/i", "$1http://$2",$string[$i]);
		$string[$i] = preg_replace("/([\w]+:\/\/[\w-?&;#~=\.\/\@]+[\w\/])/i","<a target=\"_blank\" href=\"$1\">$1</a>",$string[$i]);
		$string[$i] = preg_replace("/([\w-?&;#~=\.\/]+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,3}|[0-9]{1,3})(\]?))/i","<a href=\"mailto:$1\">$1</a>",$string[$i]);
		$echo_check = preg_replace("/(.*)\<a target=\"_blank\" href=\"ii:\/\/(.+?)\"\>(.+?)\<\/a\>(.*)/", "$2", $string[$i]);
		if ($access->checkEcho($echo_check)) {
			$string[$i] = preg_replace("/target=\"_blank\" href=\"ii:\/\/(.+?)\"/s", "class=\"iilink\" href=\"?echo=$1\"", $string[$i]);
		} else {
			$string[$i] = preg_replace("/target=\"_blank\" href=\"ii:\/\/(.+?)\"/s", "class=\"iilink\" href=\"?msgid=$1\"", $string[$i]);
		}
		if (preg_match("/^====$/", $string[$i])) {
			if (!$pre_flag) {
				$pre_flag = true;
				$string[$i] = preg_replace("/====/", "<pre>====", $string[$i]);
			} else {
				$pre_flag = false;
				$string[$i] = preg_replace("/====/", "====</pre>", $string[$i]);
			}
		}
		if(!$pre_flag && preg_match("/^\s?[a-zA-Zа-яА-Я0-9_-]{0,20}(&gt;)+.+$/i", $string[$i])) {
			$string[$i]="<span class='quote'>".$string[$i]."</span>";
		}

		if(!$pre_flag) {
			$string[$i]=preg_replace("/(^|\s+)(PS|P\.S|ЗЫ|З\.Ы|\/\/|#).+$/i", "<span class='comment'>\\0</span>", $string[$i]);
		}
	}
	$string = implode("<br />", $string);
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
function parseRemoteEcholist($remotevar) {
	global $access;
	if (!$remotevar) return NULL;

	$cookie_contents=htmlspecialchars(stripslashes($remotevar));
	$remote_raw_echolist=explode("\n", $cookie_contents);
	if (
		!is_array($remote_raw_echolist) or
		empty($remote_raw_echolist) or
		count($remote_raw_echolist) > 50
	) return NULL;

	$remote_true_echolist=[];
	foreach($remote_raw_echolist as $maybe_echo) {
		$maybe_echo=trim($maybe_echo);
		if ($access->checkEcho($maybe_echo)) $remote_true_echolist[]=$maybe_echo;
	}

	if (count($remote_true_echolist)==0) return NULL;
	else return $remote_true_echolist;
}

class IIWeb {
	public $onPage;
	public $echoes;
	public $check_keys=["subj", "time", "to", "from", "addr", "repto", "msg"]; // для репарсинга

	function __construct ($echoareas, $tpldir, $onpage, $access,
		$interface_name="веб-интерфейс IDEC",
		$default_title="Всё для спокойного общения"
	) {
		$this->access=$access;
		$this->onPage=$onpage;
		$this->echoes=$echoareas;
		global $session_lifetime;

		$this->interfacename=$interface_name;
		$this->pageTitle=$default_title;

		$local_echolist=[];

		foreach ($this->echoes as $line) {
				$local_echolist[]=$line[0];
		}

		if (!isset($_COOKIE["echolist"]) or checkData("default_echolist", true)) {
			setcookie("echolist", b64c(implode("\n", $local_echolist)), time()+$session_lifetime);
			$simple_echolist=$local_echolist;
		} elseif (checkData("new_echolist", true)) {
			$simple_echolist=parseRemoteEcholist($_POST["new_echolist"]);
			if (!$simple_echolist) $simple_echolist=$local_echolist;
			setcookie("echolist", b64c(implode("\n", $simple_echolist)), time()+$session_lifetime);
		} else {
			$simple_echolist=parseRemoteEcholist(b64d($_COOKIE["echolist"]));
			if (!$simple_echolist) $simple_echolist=$local_echolist;
		}

		$complex_echolist=[];
		foreach ($simple_echolist as $echo) {
			$found=false;
			foreach ($this->echoes as $line) {
				if ($line[0]==$echo) { $found=$line; break; }
			}
			if ($found) $complex_echolist[]=$found;
			else $complex_echolist[]=[$echo, ""];
		}

		$this->echoes=$complex_echolist;

		$html=""; //html code of page

		$links=[
			'<a class="toplink" href="?">К списку эх</a>'
		];
		$header="";
		
		// шаблоны стилей вебморды
		$htmltop=file_get_contents($tpldir."/top.html");
		$writerform=file_get_contents($tpldir."/writer-form.html");
		$settingsform=file_get_contents($tpldir."/settings.html");
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
			} elseif ($remote["msgid"]) {
				$msgid=$remote["msgid"];
				$message=$this->access->getMessage($msgid);

				$echo=$message['echo'];
				$repto=$msgid;
				$receiver=$message['from'];
			}
			$savedMessage=msg_to_ii($echo, $newmsg["msg"], $newmsg["pointname"], $nodeName.", ".$newmsg["addr"], $newmsg["time"], $receiver, $newmsg["subj"], $repto);
			
			$header=$echo;
			$links[]='<a class="toplink" href="?echo={header}">Обновить</a>';
			$links[]='<a class="toplink" href="?echo={header}&amp;new">Новое</a>';
			$html.=$this->printMsgs($echo);
		} else {
			// иначе юзер хочет что-то посмотреть, либо что-то неправильно
			if ($remote["echoname"]) {
				$echo=$remote["echoname"];

				if ($remote["writenew"]) {
					$title="Ответ в $echo";

					$header="<a class='toplink' href='?echo=$echo'>$echo</a>";
					$html.=$this->printForm($writerform, $echo);
				} else {
					$header=$echo;
					$title="Эха $echo";

					$links[]='<a class="toplink" href="?echo={header}">Обновить</a>';
					$links[]='<a class="toplink" href="?echo={header}&amp;new">Новое</a>';
					$html.=$this->printMsgs($echo);
				}
			} elseif ($remote["msgid"]) {
				$msgid=$remote["msgid"];
				$message=$this->access->getMessage($msgid);

				$echo=$message['echo'];
				$header="<a class='toplink' href='?echo=$echo'>$echo</a>";

				if ($remote["reply"]) {
					$html.=$this->printForm($writerform, $message["echo"], $message["subj"], "Ответ", $this->printMsg($message, true, false), "");
					$title="Ответ на $msgid";
				} else {
					$html.=$this->printMsg($message, false, true);
					$title="Сообщение $msgid";
				}
			} elseif ($remote["action_settings"]) {
				$header="Настройки";
				$title="Настройки";
				$html.=$settingsform;
				$html=str_replace("{list}", implode("\n", $simple_echolist), $html);
				// form with echoareas view
			} else {
				$header=$this->interfacename;
				$links=[
					'<a class="toplink" href="?action=personal">Подписки</a>'
				];

				$html.=$this->printEchos();
			}
		}
		// заканчиваем формировать html код страницы

		if (!isset($title)) $title=$this->pageTitle;
		$html.=$htmlbottom;
		$menu_links="";
		foreach($links as $link) { $menu_links.=$link; }
		$html=str_replace("{links}", $menu_links, $html);
		$html=str_replace("{header}", $header, $html);
		$html=str_replace("{title}", $title, $html);
		
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
			"action_settings" => false,
			"writenew" => false,
			"reply" => false,
			"formdata-validated" => false,
			"message" => null, // это будет массив!
			"form-errors" => null // а здесь строка
		];
		
		if (checkData("echo") && $this->access->checkEcho($_GET['echo']))
		{
			if (isset($_GET["new"])) $userDataArray["writenew"]=true;
			$userDataArray["echoname"]=$_GET["echo"];
		
		} elseif(checkData("msgid") && $this->access->checkHash($_GET["msgid"]))
		{
			if (isset($_GET["reply"])) $userDataArray["reply"]=true;
			$userDataArray["msgid"]=$_GET["msgid"];
		}
		
		if (isset($_GET["logout"])) unset($_SESSION["userAuth"]);

		if (checkData("action") && $_GET["action"] == "personal") $userDataArray["action_settings"] = true;

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
	function printMsg($message, $viewonly=false, $plainlink=false) {
		foreach ($this->check_keys as $param) $message[$param]=htmlspecialchars($message[$param]);

		$styleclass=$viewonly ? " viewonly" : "";
		$ret="";

		$msgid=$message['id'];
		$plainMessagelink=$plainlink ? "ii-point.php?q=/m/".$msgid : "?msgid=$msgid";

		if($message['repto']) {
			$ret.= "<div class='message-with-repto$styleclass'>";
			$ret.= "<a class='subj' href='#".$message['repto']."'>".$message['subj']."</a> ";
		} else {
			$ret.= "<div class='message$styleclass'>";
			$ret.= "<span class='subj'>".$message['subj']."</span> ";
		}
		$ret.="<a name='$msgid' href='$plainMessagelink'>#</a>&nbsp;&nbsp;";
		$ret.= "<span class='date'>".date("Y-m-d H:i:s", $message['time']). "</span>";
		
		$ret.=$viewonly ? "<span class='sender'>" : "<a class='reply sender' href='?msgid=".$msgid."&amp;reply'>";
		$ret.= $message['from']." (".$message['addr'].") → ".$message['to'];
		$ret.=$viewonly ? "</span>\n" : "</a>";
		
		$ret.="<br /><br />\n<span class='msgtext'>".reparse($message['msg'])."</span>\n";

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
		$text.="<table class='echolist'><tr><th>Эхоконференция</th><th>Сообщения</th><th>Описание</th></tr>";
		foreach($arr as $echo) {
			$countmsgs=$this->access->countMessages($echo[0]);
			$text.="<tr><td><a href='?echo=".$echo[0]."'>".$echo[0]."</a></td><td>$countmsgs</td><td>".$echo[1]."</td></tr>";
		}
		$text.="</table>";
		return $text;
	}
	function printMsgs($echo) {
		$output="";
		$count=$this->access->countMessages($echo);
		$pnumber=$this->onPage;

		// постраничная навигация; править осторожно, т.к. это магия
		$myaddr="?echo=".$echo;
		$num_pages=ceil($count/$pnumber);
		$page=(isset($_GET['page'])) ? (int)$_GET['page'] : $num_pages;
		if ($page > $num_pages || $page < 1) $page=$num_pages;
		$start=$page*$pnumber-$pnumber;
		
		if ($count) {
			$msglist=$this->access->getMsgList($echo, $start, $pnumber);
			// сообщения выводятся в обратном порядке
			$msglist=array_reverse($msglist);

			$messages=$this->access->getMessages($msglist);
			foreach($msglist as $msgid) {
				$output.=$this->printMsg($messages[$msgid])."\n";
			}

			$output.='<p id="nav">';
			for($pr = '', $i =1; $i <= $num_pages; $i++)
			{
				$output.=$pr=(($i == 1 || $i == $num_pages || abs($i-$page) < 2) ? ($i == $page ? "<span class='active'>$i</span>" : "<a href=\"".$myaddr."&amp;page=".$i."\">$i</a>") : (($pr == "<span class='inactive'>...</span>" || $pr == '')? '' : "<span class='inactive'>...</span>"));
			}
			$output.='</p>';
		} else {
			$output.="<h3>Сообщения в этой эхоконференции отсутствуют</h3>";
		}
		return $output;
	}
}
?>
