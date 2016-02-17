<?php
define('HOST_NAME', 'bookmakes.ru' );
define('DB_NAME', 'p284179_bokmak');
define('DB_USER', 'p284179_bokmak');
define('DB_PASSWORD', 'rcERSj5CmN');
define('DB_HOST', 'p284179.mysql.ihc.ru');
$table_prefix  = 'booker';
define('WPLANG', 'ru_RU');

define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');

define('CACHE_READ_WHITELIST','_transient|posts WHERE ID IN|limit_login_'); // do not read from cache is sql contains these
define('CACHE_WRITE_WHITELIST','_transient|limit_login_'); // do not reset cache if sql contains these

/** Кодировка базы данных для создания таблиц. */
define('DB_CHARSET', 'utf8');

/** Схема сопоставления. Не меняйте, если не уверены. */
define('DB_COLLATE', '');

define('AUTH_KEY',         'B]9_9_%uF{fdsasgC)pMx/?-+_bVjX;Xrib=1y23rgghdh3a+dadAEIZ1O/z^2Gv`<GLr<7hKI');
define('SECURE_AUTH_KEY',  'Gasgb43@t+eWU&NhkNXw1daVO,adsa>mFU*kC^;8NAi0&;2RIz}a>:uO0[yU_0Cr<IPep&GG0U');
define('LOGGED_IN_KEY',    'PvbNzyB^Z?fl|Kad..Du#4/|Y{iV|ntR22zndahar534L!k)T%~vU[5Tv4Vf*4D<m GXp#wAK_');
define('NONCE_KEY',        'ubFTsbbd34Pf{Bi(ZU^QC!FM=.Qr*|id+i4#/Wvr[tasda~n+RYcs<5I8U+d:C%cb]|d]!|~R=');
define('AUTH_SALT',        '/b2p2we%Gc-NSSxg]R2|P3=+m_*das5mq]a`vc<BZFfg12zsghjhn|^scLAJzF!U@1Lpx1yJhD');
define('SECURE_AUTH_SALT', 'DGqahU{$#{1])WF?2d1{+v4mWhES6`o@))*asdaGcCa(t,+j~0+je]{`7fHc-=k!IC[U{1bjh-');
define('LOGGED_IN_SALT',   '@a*]7xfnT!asd$-,Cw{~{Y~j38>jv!,]v%tr6jVRrH2:A)asrty3sg&56yuYZ=j+k>u@6`M|A}');
define('NONCE_SALT',       '6jyK<[n:Wbnl)`;q2E:eVhp:[ez<+=|-xPadysegg5435g4n?WzGdEIfHqrFjeqV#zl|(oWv<4');


define('WP_DEBUG', false);

/* Это всё, дальше не редактируем. Успехов! */

/** Абсолютный путь к директории WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

define('WP_SITEURL', 'http://'.HOST_NAME );
define('WP_HOME', 'http://'.HOST_NAME );
define('WP_CONTENT_FOLDERNAME', 'assets' ); 
define('WP_PLUGINS_FOLDERNAME',	 '/modules' ); 
define('WP_MUPLUGINS_FOLDERNAME', '/mu-modules' ); 

define('WP_CONTENT_DIR', ABSPATH.WP_CONTENT_FOLDERNAME );
define('WP_CONTENT_URL', WP_SITEURL.'/'.WP_CONTENT_FOLDERNAME );
define('WP_PLUGIN_DIR', WP_CONTENT_DIR.WP_PLUGINS_FOLDERNAME );
define('PLUGINDIR', WP_CONTENT_FOLDERNAME.WP_PLUGINS_FOLDERNAME );
define('WP_PLUGIN_URL', WP_CONTENT_URL.WP_PLUGINS_FOLDERNAME );
define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR.WP_MUPLUGINS_FOLDERNAME );
define('WPMU_PLUGIN_URL',	WP_CONTENT_URL.WP_MUPLUGINS_FOLDERNAME );
define('UPLOADS', WP_CONTENT_FOLDERNAME.'/files' );
define('WP_POST_REVISIONS', false );	
	
/** Инициализирует переменные WordPress и подключает файлы. */
require_once(ABSPATH . 'wp-settings.php');
//Disable File Edits
define('DISALLOW_FILE_EDIT', true);