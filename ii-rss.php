<?php
require("ii-functions.php");

define('CWD', getcwd()."/".$rss_cache_directory);

class NewsParser
{
	public $obj;
	public $items;
	
	function __construct($adress)
	{
		$this->obj=simplexml_load_file($adress);
		$this->items=$this->fetch_elements($this->obj);
		if ($this->items == NULL) return NULL;
	}

	function fetch_elements($source)
	{
		if ($source->channel) {
			$type="rss";
			return $source->channel->item;
		} elseif ($source->entry) {
			$type="atom"; // значит линкуем это дело для похожести на rss
			
			foreach ($source->entry as $entry) {
				// так, преобразуем-ка это дело указателями
				// в результате Atom почти неотличим от RSS ;)

				$entry->id["isPermaLink"]=false;
				$entry->guid=(string)$entry->id;
				$entry->description=(string)$entry->summary;
				$entry->link=$entry->link["href"];
			}
			return $source->entry;
		} else {
			return NULL; // фиг, ничего не распарсили
		}
	}
}

function ii_rss($feedname, $adress, $echo, $include_link=true, $post_old_feeds=true, $point="Новостной_робот") {
	if(!file_exists(CWD."/".$feedname)) {
			$first_run=true; // если нет кэша, значит rss-постинг идёт впервые
			touch(CWD."/".$feedname);
	} else $first_run=false;

	$news=file(CWD."/".$feedname);
	$news2=new NewsParser($adress);
	if (!$news2) return false; // значит вместо rss/atom нам подсунули дичь

	$guids=fopen(CWD."/".$feedname, "a");

	for($j=count($news2->items)-1;$j>=0;$j--) {
		$remguid=(string)$news2->items[$j]->guid;
		
		if(!in_array($remguid."\n", $news)) {

			if (!$first_run or ($post_old_feeds and $first_run)) {
				/* когда запускаем скрипт впервые, то смотрим, надо ли постить
					старые записи: если не надо, то это условие не сработает;
					иначе всё, как обычно
				*/
				ii_post($news2->items[$j], $echo, $include_link, $point);
			}
			fputs($guids,$remguid."\n");
		}
	}
	fclose($guids);

	unset($news);
	unset($news2);
}

function ii_post($item, $echo, $include_link=true, $point) {
	global $rss_msgtext_limit, $nodeName;

	$adress=$nodeName.", 1";

	$subject=$item->title;
	$subject=strip_tags($subject);
	
	$message=$item->description;

	$message=html_entity_decode($message, ENT_QUOTES, 'UTF-8');
   	
	$search = array ('/<script.*?>.*?<\/script>/si',  // Strip out javascript
					'/<style.*?>.*?<\/style>/siU'   // Strip style tags proper
					);
	
	$message=preg_replace($search, "", $message);
	$message=strip_tags($message, "<img><a>");
	$message=str_replace("\n\n", "\n", $message);

    $message=preg_replace('/<a.*?href="(.*?)".*?>(.*?)<\/a>/', ' [ \2 ]( \1 ) ', $message);
    $message=preg_replace('/<img.*?src="(.*?)".*?>/', ' \1 ', $message);
    // $message=preg_replace('/\s\s+/', ' ', $message);

	if($include_link) {
		if((string)$item->guid["isPermaLink"]=="true") {
			$link=$item->guid;
		} else {
			$link=$item->link;
		}
		$message.="\nСсылка: ".$link;
	}

	if (count($message) < $rss_msgtext_limit) {
		echo "Saving article '".$subject."'\n";
		msg_to_ii($echo,$message,$point,$adress,time(),"All",$subject,"");
	} else {
		$message=str_split($message, $rss_msgtext_limit);
		$lenn=count($message);

		for($i=0;$i<$lenn;$i++) {
			$i1=$i+1;
			echo "Article saved: '".$subject."' [$i1/$lenn]\n";
			msg_to_ii($echo,$message[$i],$point,$adress,time(),"All",$subject." [$i1/$lenn]","");
		}
	}
}

?>
