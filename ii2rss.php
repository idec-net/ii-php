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
	checkEcho($_GET['echo'])
) {
	$echo=htmlspecialchars($_GET['echo']);
	$msglist=explode("\n", getecho($echo));
	$c=count($msglist);
	$shown_messages=array_slice($msglist, $c-$limit-1);

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
		$msglist=explode("\n", getecho($echo));
		$c=count($msglist);
		$shown_messages=array_merge($shown_messages, array_slice($msglist, $c-$limit-1));
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

	foreach ($shown_messages as $msgid) {
		if (!empty($msgid)) {
			$msg=explode("\n", htmlspecialchars(getmsg($msgid)));

			$item = $channel->addChild('item');
			$title = $item->addChild('title', $msg[6]);
			$pubdate = $item->addChild('pubDate', gmdate(DATE_RFC2822, intval($msg[2])));
			$link = $item->addChild('link', $webclient_link.'?msgid='.$msgid);
			$guid = $item->addChild('guid', $msgid);
			$guid->addAttribute('isPermaLink', 'false');
			
			$description = $item->addChild('description', "<b>".$msg[3].' ('.$msg[4].') to '.$msg[5]."</b><br /><br />".implode("<br />\n", array_slice($msg, 8)));
		}
	}

	echo $rss->asXML();
}

?>
