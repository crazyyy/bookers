<?php

/**
 * Синхронизирует модуль с настройками в партнерском кабинете
 *
 * !!! Ставить на крон раз в 5 минут !!!
 * Если будет чаще - айпи попадет в бан
 *
 * В конфиге обязательно указать свой token
 * Если токен будет неправильный - синхронизация работать не будет
 *
 * Логи выполнения и ошибок можно смотреть в файле
 * cache/cron-execution.log
 */
require_once realpath(dirname(__FILE__) . '/../../') . '/bootstrap.php';

file_put_contents(ROOT_DIR . '/cache/cron-execution.log', sprintf(
    '%s | INIT',
    date('Y-m-d H:i:s')
) . PHP_EOL, FILE_APPEND);

$oResult = require_once ROOT_DIR . '/api/synchronize.php';
if (is_array($oResult)) {
    list($resource, $code, $message) = $oResult;
    file_put_contents(ROOT_DIR . '/cache/cron-execution.log', sprintf(
        '%s | %-12s | %4s | %s',
        date('Y-m-d H:i:s'),
        strtoupper($resource),
        true === $code ? 'TRUE' : $code,
        $message
    ) . PHP_EOL, FILE_APPEND);
}