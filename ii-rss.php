<?php
require("ii-functions.php");

define('CWD', getcwd()."/feeds");
$limit=63000;

class RssParser
{
	public $obj;
	public $items;
	
	function __construct($adress)
	{
		$this->obj=simplexml_load_file($adress);
		$this->items=$this->obj->channel->item;
	}
}

function ii_rss($feedname,$adress,$echo,$include_link=true) {
	if(!file_exists(CWD."/".$feedname)) {
			touch(CWD."/".$feedname);
			return;
	}

	$news=file(CWD."/".$feedname);
	$news2=new RssParser($adress);
	$guids=fopen(CWD."/".$feedname, "a");

	for($j=count($news2->items)-1;$j>=0;$j--) {
		$remguid=(string)$news2->items[$j]->guid;
		
		if(!in_array($remguid."\n", $news)) {
			ii_post($news2->items[$j],$echo,$include_link);
			fputs($guids,$remguid."\n");
		}
	}
	fclose($guids);

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
