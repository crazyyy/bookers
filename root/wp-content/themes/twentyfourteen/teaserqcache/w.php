<?php

define('QC_INDEX', true);
if (empty($_SERVER['HTTP_USER_AGENT'])) {
    return;
}

require_once dirname(__FILE__) . '/bootstrap.php';

$tizerId = isset($_GET['id']) ? (int) $_GET['id'] : false;
if (empty($tizerId)) {
    throwErrorQc('Не указан айди тизера. Выберите один из айди тизеров в личном кабинете и укажите через параметр ?id=xxx');
}

if (!defined('QC_SYNCHRONIZE_MODE') || 1 == QC_SYNCHRONIZE_MODE) {
    $oResult = require_once dirname(__FILE__) . '/api/synchronize.php';
    if (
        defined('QC_DEBUG') && true === QC_DEBUG
        && is_array($oResult) && $oResult[1] > 1 && count($oResult) === 3
    ) {
        echo $oResult[2];
    }
}

$tizerRow = QcDb::getInstanceSplit('tizers', $tizerId, false)->toValue();
if (empty($tizerRow)) {
    throwErrorQc(sprintf('Тизер "%d" не существует либо не синхронизован с основного хранилища', $tizerId));
}

$configuration = & $tizerRow['configuration'];
switch(toValueQc($configuration, array('view', 'mode'))) {
    case 'TABLE':
        $iRowCount = max(1, toValueQc($configuration, array('view', 'table', 'rows'), 1));
        $jColumnCount = max(1, toValueQc($configuration, array('view', 'table', 'cols'), 1));
        $itemCount = $iRowCount * $jColumnCount;
        break;

    case 'LIST':
    default:
        $itemCount = max(1, toValueQc($configuration, array('view', 'list', 'item_count'), 5));
        break;
}

$tizerRow['offers'] = array();
$ids = $tizerRow['offer_ids_calculated'];

$clientGeoInformation = null;
$omitIds = array();

$uaCollection = array();
$uaCollection['ANDROID'] = (boolean) preg_match('/android/i', $_SERVER['HTTP_USER_AGENT']);
$uaCollection['IOS'] = ! $uaCollection['ANDROID'] && preg_match('/(?:iphone|ipad|ipod)/i', $_SERVER['HTTP_USER_AGENT']);

$sexIds = array(
    'MALE'   => array(),
    'FEMALE' => array(),
    'BOTH'   => array()
);

$tizerOffersRowset = array();
while(count($ids) > 0) {

    foreach(toValueRandQc($ids, $omitIds, min($itemCount, count($ids))) as $offerId) {

        $offerRow = QcDb::getInstanceSplit('offers', $offerId, false)->toValue();

        $kids = array_search($offerId, $ids);
        if (false === ($offerRowValidated = toValidOfferRowQc($offerRow))) {
            unset($ids[$kids]);
            continue;
        }
        $offerRow = $offerRowValidated;

        if (!empty($offerRow['ua']) && is_array($offerRow['ua'])) {
            $hasMatch = false;
            foreach($offerRow['ua'] as $ua) {
                if (isset($uaCollection[$ua]) && true === $uaCollection[$ua]) {
                    $hasMatch = true;
                    break;
                }
            }

            if (true !== $hasMatch) {
                unset($ids[$kids]);
                continue;
            }
        }

        if (!empty($offerRow['geo'])) {

            if (null === $clientGeoInformation) {
                $clientGeoInformation = toGeoCountryWithRegionQc();
            }

            list($country, $region) = $clientGeoInformation;
            if (!array_key_exists($country, $offerRow['geo'])) {
                unset($ids[$kids]);
                continue;
            }

            if (
                !empty($offerRow['geo'][$country])
                && is_array($offerRow['geo'][$country])
                && !in_array($region, $offerRow['geo'][$country])
            ) {
                unset($ids[$kids]);
                continue;
            }
        }

        $tizerOffersRowset[(int) $offerRow['id']] = $offerRow;
        $sex = !empty($offerRow['gender_target']) ? $offerRow['gender_target'] : 'BOTH';
        $sexIds[$sex][(int) $offerId] = array_filter((array) $offerRow['categories_ids']);

        unset($ids[$kids]);
    }
}

$offerIds = array();

/**
 * Добавляем обязательные офферы
 */
$offerIdsRequired = $tizerRow['offer_ids_required'];
while(count($offerIdsRequired) > 0) {
    $k = array_rand($offerIdsRequired);
    if (array_key_exists($offerIdsRequired[$k], $tizerOffersRowset)) {
        $offerIds[] = $offerIdsRequired[$k];
    }

    unset($offerIdsRequired[$k]);
}

/**
 * Делим поравну по 33.3% и равномерно распределяем по всей демографии
 * Позже будет возможность задавать демографию из тизера
 */
$sexItemCount = ceil(max($itemCount - count($offerIds), 0) / 3);
$omitCategoriesIds = array();

foreach(array(
    array('MALE', 'BOTH', 'FEMALE'),
    array('FEMALE', 'BOTH', 'MALE'),
    array('BOTH', 'MALE', 'FEMALE'),
) as $rule) {

    $ids = array();
    foreach($rule as $sex) {

        /**
         * Первая итерация учитывает уникальность категорий
         * Вторая итерация выбирает максимально возможное количество оферов
         * для данной демографии
         */
        for ($j = 0; $j <= 1, count($ids) < $sexItemCount; $j++) {

            $sexOfferIds = array_keys($sexIds[$sex]);
            $sexOfferIdsCount = count($sexOfferIds);
            if (0 === $sexOfferIdsCount) {
                break;
            }

            $kIdsRandom = array_rand($sexOfferIds, min($sexItemCount, $sexOfferIdsCount));
            foreach((array) $kIdsRandom as $k) {
                $offerId = $sexOfferIds[$k];

                /**
                 * Сначала идем итерациями по всех офферах и стараемся выбрать
                 * офферы из уникальных категорий. Если не получилось набрать достаточное
                 * количество офферов - игнорим категории и выбираем все доступные офферы
                 */
                $offerCategoryIds = & $sexIds[$sex][$offerId];
                if (count($offerCategoryIds) > 0 && 0 === $j) {

                    $intersectIds = array_intersect($offerCategoryIds, $omitCategoriesIds);
                    if (0 === count($intersectIds)) {

                        $omitCategoriesIds[] = $offerCategoryIds[array_rand($offerCategoryIds)];

                        $ids[] = $offerId;
                        unset($sexIds[$sex][$offerId]);
                    }

                } else {

                    /**
                     * Уникальные категории закончились
                     */
                    $ids[] = $offerId;
                    unset($sexIds[$sex][$offerId]);
                }

                $idsCount = count($ids);
                if (
                    $idsCount == $sexItemCount
                    || $idsCount + count($offerIds) >= $itemCount
                ) {

                    /**
                     * Выходим из всех циклов и продолжаем итерации относительно демографии
                     */
                    break 3;
                }
            }
        }
    }

    $offerIds = array_merge($offerIds, $ids);
}

foreach($tizerOffersRowset as $offerRow) {
    if (in_array($offerRow['id'], $offerIds)) {
        $tizerRow['offers'][(int) $offerRow['id']] = $offerRow;
    }
}

define('QC_RESOURCE_PATH', sprintf('http://%s%s/resources/', $_SERVER['HTTP_HOST'], $__QC_BASE_URL));

ob_start();
require ROOT_DIR . '/api/generate.php';
$content = ob_get_clean();

if (defined('QC_LINK_TRAFFBACK_ID')) {
    return $content;
} elseif (
    defined('QC_DEBUG') && true === QC_DEBUG
    && !empty($_GET['mode']) && 0 === strcasecmp($_GET['mode'], 'plain')
) {
    echo $content;
} else {
    header('Content-type: text/javascript; charset=' . toOutputHeaderQc());
    $content = str_replace(array("\r", "\n"), '', $content);
    printf("document.write('%s')", addslashes($content));
}