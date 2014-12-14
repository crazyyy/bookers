<?php

/**
 * Синхронизация модуля с партнерским кабинетом
 * Возвращает статусы для дебага и выявления ошибок
 *
 * true - синхронизация не требуется
 * 1 - успешно
 * 2 - неверный ответ от сервера
 * 3 - невалидные данные при синхронизации
 * 4 - нет доступа
 */
if (!defined('QC_DEBUG')) return;

$dbInstance = QcDb::getInstance('configuration', true);
$domain = $dbInstance->toValueCondition('domain');
define('QC_TDS_DOMAIN', !empty($domain) ? $domain : 'a.qtds.biz');

if (
    !$dbInstance->isBlank() && !$dbInstance->isExpiredDb(QC_REFRESH_DB_MINUTES * 60)
) {
    return array('cpa', true, 'Success');
}

$dbInstance->setWithCondition('unixtime', time());
$dbInstance->save();

$path = ROOT_DIR . '/resources/';
if (
    false === ($path = realpath($path))
    || !is_writable($path)
) {
    return array('cpa-resources', 4, 'Permission denied in resources directory');
}

set_time_limit(180);

require_once ROOT_DIR . '/api/request.php';
require_once dirname(__FILE__) . '/pack.php';
ini_set('memory_limit', '256M');

$link = sprintf('/api/cpa.php?token=%s&encoding=%d', QC_TOKEN, toOutputHeaderQc());
if (true !== ($errors = qcRequestBulk(array(
    'http://' . QC_SYNCHRONIZE_MIRROR_1 . $link
), $oResponse))) {
    return array('cpa', 2, join(PHP_EOL, $errors));
}
unset($params);

if (
    empty($oResponse)
    || false === ($oResult = @ unserialize($oResponse))
    || !is_array($oResult)
    || true !== $oResult['success']
) {
    return array('cpa', 3, 'Unable to decode data');
}
unset($oResponse);

$dbInstance->setWithCondition('domain', $oResult['domain']);

if (!empty($oResult['offers'])) {
    $dbInstance->setWithCondition('offer_ids', array_keys($oResult['offers']));
}
$dbInstance->save();

/**
 * Сначала пробегаемся по тизерах и если есть изменения закидываем в $offersIds
 * В следующей итерации по оферах, если были изменения в тизерах - делаем
 * обновление оферов или ресурсов
 */
$offersIds = array();
foreach(array(
    'tizers' => false,
    'offers' => true
) as $resource => $isOfferResource) {

    if (empty($oResult[$resource]) || !is_array($oResult[$resource])) {
        continue;
    }

    foreach($oResult[$resource] as $id => $ihash) {

        $offersQcDb = QcDb::getInstanceSplit($resource, $id, true);
        $ohash = $offersQcDb->toValueCondition('hash');

        $imagesRefresh = false;

        if (empty($ohash) || $ohash != $ihash) {

            $oResultResources = doSynchronizeResource($resource, $id);
            if (is_int($oResultResources)) {
                continue;
            }

            $offersQcDb->refresh($oResultResources);
            $offersQcDb->save();

            if (true === $isOfferResource) {
                $imagesRefresh = true;
            } elseif (!empty($oResultResources['offer_ids_calculated']) && is_array($oResultResources['offer_ids_calculated'])) {
                $offersIds = array_merge($offersIds, $oResultResources['offer_ids_calculated']);
            }
        } elseif (true === $isOfferResource && in_array($id, $offersIds)) {
            $imagesRefresh = true;
        }

        if (true === $imagesRefresh) {
            doSynchronizeOffersImages($id);
        }

        $offersQcDb->disconnect();
    }
}

function doSynchronizeResource($resource, $id)
{
    $link = sprintf('/api/cpa-%s.php?token=%s&id=%d&encoding=%s', $resource, QC_TOKEN, $id, toOutputHeaderQc());
    if (true !== ($errors = qcRequestBulk(array(
        'http://' . QC_SYNCHRONIZE_MIRROR_1 . $link
    ), $oResponse))) {
        return 2;
    }
    unset($params);

    if (
        empty($oResponse)
        || false === ($oResult = @ unserialize($oResponse))
        || true !== $oResult['success']
        || !isset($oResult['row']) || !is_array($oResult['row'])
    ) {
        return 3;
    }
    unset($oResponse);

    return $oResult['row'];
}

function doSynchronizeOffersImages($id)
{
    $rootpath = ROOT_DIR . '/resources';
    $params = array('images' => array());
    if (false !== ($path = realpath($rootpath . '/' . $id))) {

        foreach(new DirectoryIterator($path) as $dim) {

            /**
             * @var $dim DirectoryIterator
             */
            if ($dim->isDot() || !$dim->isDir()) {
                continue;
            }

            $imageId = (int) $dim->getBasename();
            if (empty($imageId)) {
                continue;
            }

            foreach(new DirectoryIterator($dim->getRealPath()) as $dif) {
                /**
                 * @var $dif DirectoryIterator
                 */
                if ($dif->isDot()) {
                    continue;
                }


                $params['images'][$imageId][filesize($dif->getRealPath())] = $dif->getBasename();
            }
        }
    }

    $link = sprintf('/api/cpa-offers-images.php?token=%s&id=%d', QC_TOKEN, $id);
    if (true !== ($errors = qcRequestBulk(array(
        'http://' . QC_SYNCHRONIZE_MIRROR_1 . $link
    ), $oResponse, $params))) {
        return 2;
    }

    $qcPack = new QcPackDecode($rootpath);
    return $qcPack->decode($oResponse);
}

return array('cpa-resources', empty($oResult) ? 1 : 2, empty($oResult) ? 'Success' : 'Unable to fetch resources');