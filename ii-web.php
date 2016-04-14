<?php
require("iiweb.inc.php");

$ii_web=new IIWeb($echolist, "./iiweb-material", $onPage, $access, $interface_name, $default_title, $display_last_msg);

/*
	Дополнительно:
		$interface_name - название вебморды (null - значение по-умолчанию)
		$default_title - заголовок страницы (null - аналогично)
		$display_last_msg - отображать последнее сообщение эхи на главной
		По умолчанию эти параметры заданы в config.php
*/

?>
