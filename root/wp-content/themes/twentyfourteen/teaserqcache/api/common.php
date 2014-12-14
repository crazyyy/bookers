<?php

function toValueQc($data, $name, $default = null)
{
    if (empty($data) || !is_array($data)) {
        return $default;
    }

    if (is_string($name)) {
        return isset($name) ? $name : $default;
    }

    if (is_array($name)) {
        $oResult = & $data;
        foreach($name as $chain) {
            if (!isset($oResult[$chain])) {
                return $default;
            }

            $oResult = & $oResult[$chain];
        }

        return $oResult;
    }

    return $default;
}

function toValueRandQc(array $ids, array & $omitIds, $count = 1, $uniqOnBlank = false)
{
    $diff = array_diff($ids, $omitIds);
    if (empty($diff)) {

        if (false !== $uniqOnBlank) {
            return array();
        }

        $diff = $ids;
        $omitIds = array();
    }

    $count = min($count, count($diff));
    $randIds = array_rand($diff, $count);

    $outputIds = array();
    if ($count > 1) {

        foreach($randIds as $id) {
            $outputIds[] = $ids[$id];
            $omitIds[] = $ids[$id];
        }

        return $outputIds;
    } else {
        $outputIds[] = $ids[$randIds];
        $omitIds[] = $ids[$randIds];
    }

    return $outputIds;
}

function toValidOfferRowQc($offerRow)
{
    if (
        empty($offerRow) || !is_array($offerRow)
        || empty($offerRow['widgets']) || !is_array($offerRow['widgets'])
    ) {
        return false;
    }

    foreach($offerRow['widgets'] as $kw => $widgetRow) {
        if (
            empty($widgetRow['texts'])
            || empty($widgetRow['images'])
            || empty($widgetRow['link_ids'])
        ) {
            unset($offerRow['widgets'][$kw]);
            continue;
        }
    }

    if (empty($offerRow['widgets'])) {
        return false;
    }

    return $offerRow;
}

function throwErrorQc($message, $kill = true)
{
    if (false === $kill) {
        echo $message;
    } else {
        header('Content-type: text/html; charset=' . toOutputHeaderQc());
        die($message);
    }
}

function toGeoCountryWithRegionQc()
{
    $oRecord = array('', '');
    $ip = $_SERVER['REMOTE_ADDR'];
    if (empty($ip)) {
        return $oRecord;
    }
    require_once ROOT_DIR . '/api/geoip/geoipcity.inc';

    $gi = geoip_open(ROOT_DIR . '/api/geoip/geoipcity.dat', GEOIP_STANDARD);
    $record = geoip_record_by_addr($gi, $ip);
    if (empty($record)) {
        return $oRecord;
    }

    $oRecord[0] = $record->country_code;
    if (
        !empty($record->country_code) &&
        !empty($GEOIP_REGION_NAME[$record->country_code][$record->region])
    ) {
        $oRecord[1] = $GEOIP_REGION_NAME[$record->country_code][$record->region];
    }

    geoip_close($gi);

    return $oRecord;
}

function toOutputHeaderQc()
{
    if (!defined('QC_ENCODING') || false === strpos(QC_ENCODING, '1251')) {
        return 'utf-8';
    }

    return 'windows-1251';
}