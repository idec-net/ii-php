<?php
require("ii-functions.php");

define('CWD', getcwd()."/feeds");
$limit=63000;

$default_template='<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
<item>
<guid>empty</guid>
</item>
</channel>
</rss>';

class RssParser
{
	public $obj;
	public $items;
	
	function __construct($adress)
	{
		$this->obj=simplexml_load_file($adress);
		$obj=$this->obj;

		$this->items=$obj->channel->item;
	}
}

function ii_rss($feedname,$adress,$echo,$include_link=true) {
	global $default_template;

	if(!file_exists(CWD."/".$feedname)) {
			file_put_contents(CWD."/".$feedname, $default_template);
			return;
	}

	file_put_contents(CWD."/".$feedname.'-new',file_get_contents($adress,false));

	$news=new RssParser(CWD."/".$feedname);
	$news2=new RssParser(CWD."/".$feedname.'-new');

	$items=$news->items;
	$itemsGuids=array();

	foreach($items as $item) {
		$itemsGuids[]=(string)$item->guid;
	}

	for($j=count($news2->items)-1;$j>=0;$j--) {
		$item1=$news2->items[$j];
		$lolguid=(string)$item1->guid;
		
		if(!in_array($lolguid, $itemsGuids)) {
			ii_post($item1,$echo,$include_link);
		}
	}
	unset($item1);
	
	copy(CWD."/".$feedname.'-new', CWD."/".$feedname);
	
	unset($news);
	unset($news2);
}

function ii_post($item,$echo,$include_link=true) {
	global $limit;

	$point="Новостной_робот";
	$adress="mira, 1";

	$subject=$item->title;
	$message=$item->description;

	$message=html_entity_decode($message, ENT_QUOTES, 'UTF-8');
   	
	$search = array ('/<script.*?>.*?<\/script>/si',  // Strip out javascript
					'/<style.*?>.*?<\/style>/siU'   // Strip style tags proper
					);

	$message=preg_replace($search, "", $message);

	$message=strip_tags($message, "<img><a>");
	$message=str_replace("\n", " ", $message);

    $message=preg_replace('/<a.*?href="(.*?)">(.*?)<\/a>/', ' [ \2 ]( \1 ) ', $message);
    $message=preg_replace('/<img.*?src="(.*?)".*?>/', ' \1 ', $message);
    $message=preg_replace('/\s\s+/', ' ', $message);

	if($include_link) {
		if((string)$item->guid["isPermaLink"]=="true") {
			$link=$item->guid;
		} else {
			$link=$item->link;
		}
		$message.="\nСсылка: ".$link;
	}

	if (count($message)<$limit) {
		echo "Saving article '".$subject."'\n";
		msg_to_ii($echo,$message,$point,$adress,time(),"All",$subject,"");
	} else {
		$message=str_split($message,$limit);
		$lenn=count($message);

		for($i=0;$i<$lenn;$i++) {
			$i1=$i+1;
			echo "Article saved: '".$subject."' [$i1/$lenn]\n";
			msg_to_ii($echo,$message[$i],$point,$adress,time(),"All",$subject." [$i1/$lenn]","");
		}
	}
}

?>
