<?php

$pushpassword="";
$savemsgOverride=false; //do Not set to true without need
$nodeName="lenina"; //message fingerprint for your ii node

$postlimit=70000; //limit for base64 pointmsg
$msgtextlimit=65536; //limit for message text (with headers)

$logfile="ii-log.txt";
$logerrors=false;

$usemysql=false;
$mysqldata=array(
	"host" => "localhost",
	"db" => "your-database",
	"user" => "username",
	"pass" => "password",
	"table" => "ii-messages"
);

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

?>
