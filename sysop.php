<?php
require("ii-functions.php");

ini_set("session.gc_maxlifetime", $session_lifetime);
ini_set("session.cookie_lifetime", $session_lifetime);

session_set_cookie_params($session_lifetime);
session_start();

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
class SysopAdm {
	function __construct($tpldir, $transport, $access) {
		$this->transport=$transport;
		$this->access=$access;

		$htmltop=file_get_contents($tpldir."/top.html");
		$msgeditform=file_get_contents($tpldir."/msgedit.html");
		$authform=file_get_contents($tpldir."/auth.html");
		$htmlmain=file_get_contents($tpldir."/sysop.html");
		$htmlbottom=file_get_contents($tpldir."/bottom.html");
		$html=$htmltop;
		$links=[];
		$header="Панель сисопа";

		$remote=$this->fetchUserData();
		if ($remote["authorized"]) {
			$links[]="<a class='toplink' href='?logout'>Выйти</a>";

			if ($remote["delmessages"]) {
				foreach ($remote["delmessages"] as $msgid) {
					$transport->deleteMessage($msgid, $withecho=true);
					$html.="deleted msgid ".$msgid."<br />\n";
				}
			} elseif ($remote["delechoarea"]) {
				$msgids=$access->getMsgList($remote["delechoarea"]);
				foreach ($msgids as $msgid) {
					$transport->deleteMessage($msgid, $withecho=true);
					$html.="deleted msgid ".$msgid." from echo ".$remote["delechoarea"]."<br />\n";
				}
			} elseif ($remote["clearblacklist"]) {
				$msgids=$access->blacklist;
				foreach ($msgids as $msgid) {
					$transport->deleteMessage($msgid, $withecho=true);
					$html.="deleted msgid ".$msgid."<br />\n";
				}
				$html.=$htmlmain;
			} elseif ($remote["editmessage"]) {
				if ($remote["updatemessage-text"]) {
					$rawmsg=str_replace("\r", "", $remote["updatemessage-text"]);
					$readableMsg=$transport->makeReadable($rawmsg);
					$transport->updateMessage($remote["editmessage"], $readableMsg);
				}
				$msg=$transport->getRawMessage($remote["editmessage"]);
				$html.=$msgeditform;
				$html=str_replace("{message}", $msg, $html);
				$html=str_replace("{msgid}", $remote["editmessage"], $html);
				$links[]="<a class='toplink' href='?'>Назад</a>";
			} else {
				// главная страница
				$html.=$htmlmain;
			}
		} else {
			$html.=$authform;
		}

		$html.=$htmlbottom;
		$menu_links="";
		foreach($links as $link) { $menu_links.=$link; }
		$html=str_replace("{links}", $menu_links, $html);
		$html=str_replace("{header}", $header, $html);
		$html=str_replace("{errors}", $remote["auth-errors"], $html);
		$html=str_replace("{token}", '<input name="csrf_token" type="hidden" value="'.generate_csrf_token().'" />', $html);

		$echolist=$transport->fullEchoList();
		$selects="";
		foreach ($echolist as $echo) {
			$selects.="<option>$echo</option>\n";
		}
		$html=str_replace("{options}", $selects, $html);
		echo $html;
	}

	function fetchUserData() {
		// это обработчик всех внешних данных (форм и сессий тоже!)
		global $pushpassword;

		$userDataArray=[
			"authorized" => false,
			"auth-errors" => "",
			"editmessage" => null,
			"updatemessage-text" => null,
			"delmessages" => null,
			"delechoarea" => null,
			"clearblacklist" => false
		];

		if (isset($_GET["logout"])) {
			unset($_SESSION["sysop-auth"]);
			$userDataArray["authorized"]=false;
		}

		if (!isset($_SESSION["sysop-auth"])) {
			if (checkData("authstr", true)) {
				if ($_POST["authstr"] == $pushpassword) {
					$sysop_auth=true;
					$userDataArray["authorized"]=true;
				} else {
					$sysop_auth=false;
					$userDataArray["auth-errors"].="Неверный пароль";
				}
			} else {
				$sysop_auth=false;
			}
			if ($sysop_auth) $_SESSION["sysop-auth"]=$sysop_auth;
		} else $sysop_auth=$_SESSION["sysop-auth"];
		$userDataArray["authorized"]=$sysop_auth;

		if (!$userDataArray["authorized"]) return $userDataArray;

		if (checkData("blacklist-clear", true)) {
			$userDataArray["clearblacklist"]=true;
		}
		elseif (checkData("messages-clear")) {
			$userDataArray["messages-clear"]=explode("\n", $_POST["messages-clear"]);
		}
		elseif (checkData("echoarea-delete", true)) {
			$echo=$_POST["echoarea-delete"];
			if ($this->access->checkEcho($echo)) {
				$userDataArray["delechoarea"]=$echo;
			} else $userDataArray["auth-errors"].="Неправильная эха";
		}
		elseif (checkData("edit-message", true)) {
			$msgid=$_POST["edit-message"];
			$userDataArray["editmessage"]=$msgid;
			if (!$this->access->checkHash($msgid)) $userDataArray["auth-errors"].="Неправильный msgid!";
			else {
				if (checkData("edit-text", true)) {
					$userDataArray["updatemessage-text"]=$_POST["edit-text"];
				}
			}
		}

		return $userDataArray;
	}
}
$adm=new SysopAdm("iiweb-material/", $access->transport, $access);

?>
