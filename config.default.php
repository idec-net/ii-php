<?php

$pushpassword="";
$nodeName="lenina"; //message fingerprint for your ii node

$postlimit=70000; //limit for base64 pointmsg
$msgtextlimit=65536; //limit for message text (with headers)

$blacklist_file="blacklist.txt";
$mysqldata=array(
	"host" => "localhost",
	"db" => "test",
	"user" => "root",
	"pass" => "",
	"table" => "ii-messages"
);

$transport=new TextBase("echo/", "msg/");
// $transport=new MysqlBase($mysqldata, "echo/"); // для mysql-базы

$parr=[
	["","root"],
	["","point1"]
];

$echolist=[
	["im.100", "Наша болталка"],
	["ii.dev.14", "Обсуждение разработки"],
	["linux.14", "Эха для линуксоидов"],
	["ii.soft.14", "Анонсы и обсуждение ПО"]
];

$rss_echoareas=["mlp.15", "develop.16"];

$files_directory="./";
$rss_cache_directory="./feeds";

$public_files=[
	"README.md" => "Справка по PHP-ноде",
	"123.txt" => "Какой-нибудь текстовый файл"
];

$session_lifetime=1728000; //used for web-interface
$rss_msgtext_limit=$msgtextlimit-400; // used for rss parser

?>
