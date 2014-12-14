<?php

define('QC_INDEX', true);

require_once dirname(__FILE__) . '/bootstrap.php';

header('Content-Type: text/html; charset=' . toOutputHeaderQc());

if (!defined('QC_TEST') || true !== QC_TEST) {
    throwErrorQc('Тестирование остановлено! Включите опцию для дебага в конфигурационном файле!');
}

$id = isset($_GET['id']) ? $_GET['id'] : 0;
if (empty($id)) {

    if (empty($__QC_TIZERS_IDS) || !is_array($__QC_TIZERS_IDS)) {
        throwErrorQc('Необходимо задать айди доступных потоков в конфигурационном файле!');
    }

    $id = $__QC_TIZERS_IDS[array_rand($__QC_TIZERS_IDS)];
}

?>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
</head>
<body>
    <h1>Пример отображения тизера №<?=$id; ?></h1>
    <script type="text/javascript" src="<?=$__QC_BASE_URL; ?>/w.php?id=<?=$id; ?>"></script>
</body>
</html>