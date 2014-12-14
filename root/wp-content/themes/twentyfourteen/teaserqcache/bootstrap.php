<?php

define('ROOT_DIR', dirname(__FILE__));
date_default_timezone_set('Etc/GMT-4');

require_once ROOT_DIR . '/config.php';

error_reporting(E_ALL | E_STRICT);

@ ini_set('display_errors', (defined('QC_DEBUG') && true === QC_DEBUG) ? 'On' : 'Off');
@ ini_set('log_errors', 'On');
@ ini_set('error_log', ROOT_DIR . '/cache/php-errors.log');

require_once ROOT_DIR . '/api/common.php';
require_once ROOT_DIR . '/api/db.php';

if (true !== QcDb::setCacheRootPath(ROOT_DIR . '/cache/')) {
    throwErrorQc('Нет прав к папке cache. Установите права 755 или 775');
}

$__QC_BASE_URL = '';
if (defined('QC_INDEX') && true === QC_INDEX) {

    $path = '';
    if (isset($_SERVER['REQUEST_URI'])) {
        $path = dirname($_SERVER['REQUEST_URI']);
    } elseif(isset($_SERVER['PHP_SELF'])) {
        $path = dirname($_SERVER['PHP_SELF']);
    }

    $__QC_BASE_URL = rtrim($path, '/');
}
