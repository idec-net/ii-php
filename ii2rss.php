<?php
require("ii-functions.php");
$limit=20; // если выводим одну эху, то по 20 сообщений; иначе с каждой по $limit/2
$take_echoes=5; // берём максимум первые 5 эх $echolist в конфиге

$host=(isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : "localhost";
$self=(isset($_SERVER['PHP_SELF'])) ? dirname($_SERVER['PHP_SELF']) : false;

if (!$self or $self==".") $self="/ii";

$webclient_link="http://".$host.$self."/ii-web.php";

if (count($echolist)<$take_echoes) $take_echoes=count($echolist);

if (
	isset($_GET['echo']) &&
	$access->checkEcho($_GET['echo'])
) {
	$echo=htmlspecialchars($_GET['echo']);
	$shown_messages=$access->getMsgList($echo, -$limit);

	$feed_title=$echo;
	$feed_description="Лента эхоконференции ".$echo;
	$feed_link=$webclient_link.'?echo='.$echo;
} else {
	$limit/=2;

	if (!isset($rss_echoareas)) {
		$rss_echoareas=[];
		for ($i=0;$i<$take_echoes;$i++) $rss_echoareas[]=$echolist[$i][0];
	}

	$shown_messages=[];
	foreach ($rss_echoareas as $echo) {
		$msglist=$access->getMsgList($echo, -$limit);
		$shown_messages=array_merge($shown_messages, $msglist);
	}

	$feed_title="Последние сообщения";
	$feed_description="Лента эхоконференций станции ".$nodeName;
	$feed_link=$webclient_link;
}

if (count($shown_messages)>0) {
	header('Content-Type: text/xml; charset=utf-8', true);

	$rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/"></rss>');
	$channel = $rss->addChild('channel');

	$title = $channel->addChild('title', $feed_title);
	$description = $channel->addChild('description', $feed_description);
	$link = $channel->addChild('link', $feed_link);
	$language = $channel->addChild('language','ru');

	$now=time();
	$build_date = gmdate(DATE_RFC2822, $now);
	$lastBuildDate = $channel->addChild('lastBuildDate', $build_date);

	$messages=$access->getMessages($shown_messages);
	$keys=array_keys($messages);
	$check_keys=["subj", "time", "to", "from", "addr", "repto", "msg"];

	foreach ($keys as $msgid) {
		foreach ($check_keys as $param) {
			$messages[$msgid][$param]=htmlspecialchars($messages[$msgid][$param]);
		}
	}

	foreach ($messages as $msg) {
		$msgid=$msg["id"];
		$item = $channel->addChild('item');
		$title = $item->addChild('title', $msg["subj"]);
		$pubdate = $item->addChild('pubDate', gmdate(DATE_RFC2822, intval($msg["time"])));
		$link = $item->addChild('link', $webclient_link.'?msgid='.$msgid);
		$guid = $item->addChild('guid', $msgid);
		$guid->addAttribute('isPermaLink', 'false');

		$description = $item->addChild('description', "<b>".$msg["from"].' ('.$msg["addr"].') to '.$msg["to"]."</b><br /><br />".str_replace("\n", "<br />\n", $msg["msg"]));
	}

	echo $rss->asXML();
}

?>
