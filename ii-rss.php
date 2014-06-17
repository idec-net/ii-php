<?php
require("ii-functions.php");

define('CWD', getcwd()."/feeds");
$limit=63000;

$default_template='<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
<item>
<pubDate>'.date("r").'</pubDate>
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

function ii_rss($feedname,$adress,$echo) {
	global $default_template;

	if(!file_exists(CWD."/".$feedname)) {
			file_put_contents(CWD."/".$feedname, $default_template);
			return;
	}

	file_put_contents(CWD."/".$feedname.'-new',file_get_contents($adress,false));

	$news=new RssParser(CWD."/".$feedname);
	$news2=new RssParser(CWD."/".$feedname.'-new');
	$first_date=strtotime($news->items[0]->pubDate);

	$items=$news->items;
	$itemsDates=array();

	foreach($items as $item) {
		$itemsDates[]=strtotime($item->pubDate);
	}

	for($j=count($news2->items)-1;$j>=0;$j--) {
		$item1=$news2->items[$j];
		$loltime=strtotime($item1->pubDate);
		if($loltime>$first_date) {
			ii_post($item1,$echo);
		}
	}
	$item1=false;
	
	copy(CWD."/".$feedname.'-new', CWD."/".$feedname);
	
	unset($news);
	unset($news2);
}

function ii_post($item,$echo) {
	global $limit;

	$point="Новостной_робот";
	$subject=$item->title;
	$message=$item->description;

	$message=trim(strip_tags($message));
	$message=html_entity_decode($message, ENT_QUOTES, 'UTF-8');
	$message=str_replace("\n\n","",$message);
	$message.="\nСсылка: ".$item->link;
	
	if (count($message)<$limit) {
		echo "Сохранение статьи '".$subject."'\n";
		msg_to_ii($echo,$message,$point,"mira, 1",time(),"All",$subject,"");
	} else {
		$message=str_split($message,$limit);
		$lenn=count($message);

		for($i=0;$i<$lenn;$i++) {
			$i1=$i+1;
			echo "Article saved: '".$subject."' [$i1/$lenn]\n";
			msg_to_ii($echo,$message[$i],$point,"mira, 1",time(),"All",$subject." [$i1/$lenn]","");
		}
	}
}

?>
