<?php
define('DSN','localhost');
define('DB_NAME','crud');
define('USER_NAME','root');
define('PASSWORD','');
$connection = new PDO('mysql:host=' . DSN . ';dbname=' . DB_NAME, USER_NAME, PASSWORD);
define('ACCOUNT_TABLE','ulinker_accounts');
define('NOTE_TABLE','ulinker_notes');
define('VIDEO_TABLE','ulinker_videos');
define('BOOKMARK_TABLE','ulinker_bookmarksites');
