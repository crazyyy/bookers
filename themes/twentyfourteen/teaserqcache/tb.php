<?php

/**
 * Traffback
 * Используется в случаи, когда офер не отвечает требованиям ГЕО, клиентом и т.п.
 */

if (empty($_GET['tbid'])) {
    return;
}

define('QC_LINK_TRAFFBACK_ID', (int) $_GET['tbid']);

$content = require dirname(__FILE__) . '/w.php';

?>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
</head>
<body>
    <?=$content; ?>
</body>
</html>