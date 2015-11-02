<?php
require("ii-functions.php");
$limit=20;
$webclient_link="http://".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/ii-web.php";

if (
	isset($_GET['echo']) &&
	checkEcho($_GET['echo'])
) {
	$echo=$_GET['echo'];
} else {
	$echo="pipe.2032";
}

$msglist=explode("\n", getecho($echo));
$c=count($msglist);

$shown_messages=array_slice($msglist, $c-$limit-1);

if (count($shown_messages)>0) {
	header('Content-Type: text/xml; charset=utf-8', true);
	
	$rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/"></rss>');
	$channel = $rss->addChild('channel');

	$title = $rss->addChild('title', htmlspecialchars($echo));
	$description = $rss->addChild('description','Лента ii-эхоконференции '.$echo);
	$link = $rss->addChild('link', $webclient_link.'?echo='.$echo);
	$language = $rss->addChild('language','ru');

	$date_f = date("D, d M Y H:i:s T", time());
	$build_date = gmdate(DATE_RFC2822, strtotime($date_f));
	$lastBuildDate = $rss->addChild('lastBuildDate',$date_f);

	foreach ($shown_messages as $msgid) {
		if (!empty($msgid)) {
			$msg=explode("\n", htmlspecialchars(getmsg($msgid)));

			$item = $rss->addChild('item');
			$title = $item->addChild('title', $msg[6]);
			$title = $item->addChild('author', $msg[3].' ('.$msg[4].') -&gt; '.$msg[5]);
			$link = $item->addChild('link', $webclient_link.'?msgid='.$msgid);
			$guid = $item->addChild('guid', $msgid);
			$guid->addAttribute('isPermaLink', 'false');
			
			$description = $item->addChild('description', implode("<br />\n", array_slice($msg, 8)));
		}
	}

	echo $rss->asXML();
}

?>
