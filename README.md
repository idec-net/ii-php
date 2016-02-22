ii-php
======
ii - это русская фидообразная сеть для обмена сообщениями, подробно узнать о ней вы можете [здесь](http://ii-net.tk/ii-doc/). 
В этом репозитории находится php-реализация "серверной" части ii. Имеется поддержка как своего собственного формата базы данных, так и БД mysql (через mysqli), см. *config.php*. Поддерживаются расширения ii /x/c/, /x/file, /list.txt, /blacklist.txt, расширенный /u/e (на отдачу).

Конфигурация
======
По-умолчанию хранится в файле *config.default.php*, который при первом запуске любого скрипта, использующего ii, копируется в *config.php*. В дальнейшем для работы используется последний.

Рекомендуется назначить в php.ini или ещё где-нибудь путь для сохранения сессий, отличный от /tmp, для нормальной работы веб-интерфейса.

Минимальная установка
======
Поместить в одну директорию файлы ii-point.php, ii-functions.php, filter.php, transports.php, config.default.php (или config.php), blacklist-func.php. Также надо будет прописать поинтов (пользователей) в конфиг.
При такой установке можно отправлять и получать сообщения с помощью клиента. Для синхронизации нужен будет либо push-скрипт, либо отдельный фетчер.

Но для удобства использования лучше скопировать все файлы из репозитория, т.к. будет всё необходимое.

Отправка сообщений в ii с сайта
======
```php
require("ii-functions.php");
msg_to_ii($echoarea, $message, $usersent, "myforum, 1", time(), $userget, $subject, $repto);
// $usersent - отправитель; $userget - получатель (или All); $repto - id сообщения, на которое отвечаем (можно передавать пустую строку)
```

Синхронизация
======
Данная php нода поддерживает push-синхронизацию (когда другой узел закачивает сообщения на данный), а также fetch-синхронизацию (когда данный узел скачивает сообщения у другого) через webfetch.php, для использования которого надо создать рядом php скрипт примерно такого содержания
```php
<?php
require("webfetch.php");
$fetchconfig=[
	"http://your-ii-node.ru/ii-point.php?q=/",
	"echoarea1.10",
	"echoarea2.14",
	"myecho.2015"
];
fetch_messages($fetchconfig);
/* дополнительные параметры:
	$one_request_limit=20 - количество сообщений в одном скачиваемом бандле
	$fetch_limit=false - максимум сообщений в индексе (расширенный /u/e)
		(подробности на странице Расширения в документации)
*/
?>
```
и настроить его запуск в cron.

RSS
======
Также здесь есть скрипт RSS/Atom граббера, который может импортировать ленты в ii. Для его использования достаточно создать рядом php скрипт, написать туда
```php
<?php
require("ii-rss.php");

ii_rss("feedname", "http://your-feed/adress", "echoname.2014");
/* дополнительно:
	$include_link=true - оставлять ссылку на оригинальную новость
	$post_old_feeds=true - при первом запуске запостить все старые новости в ленте
	$point="sexyrobot" - от имени кого отправляем сообщение
*/

?>
```
и поместить его вызов в cron. Для хранения данных rss ленты требуется создать рядом каталог *feeds*.

Также есть скрипт *ii2rss.php*, который проделывает обратную работу - отдаёт rss-ленту эхоконференции. Принимает GET-параметр *echo* с названием нужной эхи.

Чёрный список
======
Есть возможность игнорировать неправильные или плохие сообщения при синхронизации с другими станциями или с клиентами. Для этого есть файл blacklist.txt, в который можно записывать msgid таких сообщений. Образец указан в самом файле, можно пользоваться разделителями, файл заканчивать переносом строки (но при этом не ставить пустых строк среди id сообщений).

Список эхоконференций
======
Он задаётся в конфиге config.php, вместе с описаниями. Служит для автоматического доступа клиента к ресурсам сети. Также используется веб-интерфейсом.
Образец списка:
```php
$echolist=[
	["pipe.2032", "Наша болталка"],
	["ii.14", "Обсуждение сети"],
];
```

Миграция на mysql
======
В случае большого количества сообщений стоит мигрировать на mysql. Алгоритм таков:

* Убедитесь в наличии расширения php mysqli, так как всё работает через него.
* В конфиге в `$mysqldata` по образцу записываются нужные данные (база данных, логин, пароль, название таблицы). Прописываете нужный транспорт в `config.php` (образец в комментариях).
* Запускаете скрипт `mysql-migrate.php`, который создаст таблицу и начнёт сбрасывать по порядку содержимое каждой эхи. Если скрипт прервался на полпути, запускаете снова (продолжит с того же места).
* Попробуйте переименовать каталог *msg/* и смотрите, всё ли работает. В случае чего можно вернуться к использованию обычной базы через конфиг. Индекс для удобства ещё хранится в *echo/*
