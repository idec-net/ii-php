<?php

$pushpassword=""; // used for sysop panel and /u/push
$nodeName="lenina"; // message fingerprint for your node

$postlimit=87382; // limit for base64 pointmsg
$msgtextlimit=65536; // limit for message text (with headers)

$filesize_limit = 1024 * 1024 * 30; // 30M for uploads
$max_dir_quota = 1024 * 1024 * 1024; // 1G for storage_directory

// web-interface settings
$session_lifetime=1728000;
$display_last_msg=true; // show last message on main page
$interface_name=null;  // string should be here; "null" sets default
$default_title=null;  // also string
$onPage=20; // show N messages per page

// /x/file storage path
$files_directory="./";

// rss bots/parsers settings
$rss_cache_directory="./feeds";
$rss_msgtext_limit=$msgtextlimit-400;

// used for rss displayer
$rss_echoareas=["mlp.15", "develop.16"];

$blacklist_file="blacklist.txt";
$fblacklist_file="fblacklist.txt";

$mysqldata=array(
	"host" => "localhost",
	"db" => "test",
	"user" => "root",
	"pass" => "",
	"table" => "ii-messages"
);

$transport=new TextBase("echo/", "msg/");
// $transport=new MysqlBase($mysqldata); // for mysql base
$file_transport = new NoBaseFileTransport("fecho/", "upload_files/");

$parr=[
	["","root"],
	["","point1"]
]; // array of your points

$echolist=[
	["im.100", "Наша болталка"],
	["ii.dev.14", "Обсуждение разработки"],
	["linux.14", "Эха для линуксоидов"],
	["ii.soft.14", "Анонсы и обсуждение ПО"]
];

$public_files=[
	"README.md" => "Справка по PHP-ноде",
	"123.txt" => "Какой-нибудь текстовый файл"
];

$file_echolist=[
	["books", "Любые другие книги, например, художественные"],
	["pictures", "Просто картинки"],
	["music", "Всякая разная музыка"]
];

$private_files=[
	"filename.txt" => "Файл, который могут скачивать только поинты"
];

?>
