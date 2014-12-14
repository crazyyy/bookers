<?php

function qcRequest($url, & $oResult, $params = null)
{
    $errors = array();
    $isValidRequest = false;

    $isPost = null !== $params;
    if (is_array($params)) {
        $params = http_build_query($params);
    } elseif (!is_string($params)) {
        $params = '';
    }

    $http = array(
        'method'    => $isPost ? 'POST' : 'GET',
        'header'    => "Content-type: application/x-www-form-urlencoded\r\n",
        'timeout'   => 10
    );

    if ($isPost) {
        $http['content'] = $params;
    }
    $context = stream_context_create(array('http'  => $http));

    $microtime = microtime(true);

    $oResult = @ file_get_contents($url, false, $context);
    unset($context);

    if (false !== $oResult) {
        return true;
    }

    $errors[] = 'Неудачное обновление через функцию file_get_contents<br />URL: <code>' . $url . '</code><br/>' .
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

    if ($isPost) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    }

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

function qcRequestBulk(array $locations, & $oResult, $params = null)
{
    $isValidRequest = false;
    $oErrors = array();
    foreach($locations as $url) {

        $oResult = false;
        if (true === ($errors = qcRequest($url, $oResult, $params))) {

            $oResult = trim($oResult);
            if (empty($oResult)) {
                continue;
            }

            $isValidRequest = true;
            break;
        }

        $oErrors = array_merge($oErrors, $errors);
    }

    return $isValidRequest ? true : $oErrors;
}