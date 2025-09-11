<?php
define('DOMAIN', 'https://{your-domain}');
define('PARENT_CONTENTS_NAME', 'U-Linker');
define('SERVERPATH', '/home/{your-domain-name}/www');
define('CURRENT_CONTENTS_NAME', PARENT_CONTENTS_NAME.' Note-Library-Zip Generator');
define('BUNNER', '<img src="https://fontmeme.com/permalink/250523/729f87b5efb165075e41139e9b5227b7.png" alt="minecraft-standard-font" border="0">');
define('FAVICON_HREF', DOMAIN. '/'. PARENT_CONTENTS_NAME .'/storage/images/favicon-16x16.png');

$headLinks = '<meta charset="UTF-8">'
  . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
  . '<title>'.CURRENT_CONTENTS_NAME.'</title>'
  . '<link rel="stylesheet" href="../static/style.css">'
  . '<link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet" />'
  . '<link rel="icon" type="image/png" sizes="16x16" href="'. FAVICON_HREF .'"><meta name="msapplication-TileColor" content="#ffffff"><meta name="theme-color" content="#ffffff">'
  . '<link href="https://fonts.googleapis.com/css2?family=Material+Icons" rel="stylesheet">'
  . '<link href="https://cdn.jsdelivr.net/npm/@mdi/font@5.x/css/materialdesignicons.min.css" rel="stylesheet">';
define('HEAD_LINKS',$headLinks);

$scriptLinks = '<script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>'
  . '<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/0.18.0/axios.js"></script>'
  . '<script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>'
  . '<script type="text/javascript" src="https://code.jquery.com/jquery-3.3.1.js"></script>';
define('SCRIPT_LINKS',$scriptLinks);
