<?php

require_once dirname(__FILE__) . '/common.php';

if (
    !defined('QC_DEBUG') || !defined('QC_TDS_DOMAIN')
    || empty($tizerRow) || !is_array($tizerRow)
    || empty($tizerRow['configuration']) || !is_array($tizerRow['configuration'])
) {
    throwErrorQc('Неверные входящие параметры');
}

if (empty($tizerRow['offers']) || !is_array($tizerRow['offers'])) {
    throwErrorQc('Нет ни одного активного оффера для отображения');
}

$configuration = & $tizerRow['configuration'];

switch(toValueQc($configuration, array('view', 'mode'))) {
    case 'TABLE':
        $mode = 'TABLE';
        $iRowCount = max(1, toValueQc($configuration, array('view', 'table', 'rows'), 1));
        $jColumnCount = max(1, toValueQc($configuration, array('view', 'table', 'cols'), 1));
        $itemCount = $iRowCount * $jColumnCount;
        break;

    case 'LIST':
    default:
        $mode = 'LIST';
        $iRowCount = 1;
        $jColumnCount = max(1, toValueQc($configuration, array('view', 'list', 'item_count'), 5));
        $itemCount = $jColumnCount;
        break;
}

$buildViewBlocks = array(
    'TABLE' => array(
        'begin'        => '<table class="qccpa-box">',
        'begin_row'    => '<tr>',
        'end_row'      => '</tr>',
        'begin_column' => '<td class="qccpa-item-box qccpa-ta-%s">',
        'end_column'   => '</td>',
        'end'          => '</table>'
    ),
    'LIST'  => array(
        'begin'        => '<ul class="qccpa-box qccpa-clearfix">',
        'begin_row'    => '',
        'end_row'      => '',
        'begin_column' => '<li class="qccpa-item-box qccpa-ta-%s">',
        'end_column'   => '</li>',
        'end'          => '</ul>'
    )
);

$itemRowsetOptimized = array();
$itemRowsetOptimizedIds = array();
while($itemCount > count($itemRowsetOptimized)) {
    foreach(toValueRandQc(array_keys($tizerRow['offers']), $itemRowsetOptimizedIds) as $id) {
        $itemRowsetOptimized[] = $tizerRow['offers'][$id];
    }
}

$omit = array(
    'texts' => array(),
    'images' => array(),
);
foreach($itemRowsetOptimized as & $itemRow) {

    $offerId = $itemRow['id'];
    if (!array_key_exists($offerId, $omit)) {
        $omit[$offerId] = array('widgetsIds' => array(), 'widgets' => array('textsIds' => array(), 'imagesIds' => array(), 'linksIds' => array()));
    }

    $offerRow = & $tizerRow['offers'][$itemRow['id']];

    $widgetIds = array_keys($offerRow['widgets']);
    list($widgetId) = toValueRandQc($widgetIds, $omit[$offerId]['widgetsIds'], 1);
    $widgetRow = & $offerRow['widgets'][$widgetId];

    $textIds = array_keys($widgetRow['texts']);
    list($textId) = toValueRandQc($textIds, $omit[$offerId]['widgets']['textsIds'], 1);
    $textRow = & $widgetRow['texts'][$textId];

    $itemRow['text'] = $textRow['name'];

    $imageIds = array_keys($widgetRow['images']);
    list($imageId) = toValueRandQc($imageIds, $omit[$offerId]['widgets']['imagesIds'], 1);
    $imageRow = & $widgetRow['images'][$imageId];

    $itemRow['image_link'] = rtrim(QC_RESOURCE_PATH, '/') . sprintf(
        $imageRow['image_link'],
        $offerRow['id'],
        toValueQc($configuration, array('image', 'size'), 100)
    );

    $linkIds = array_keys($widgetRow['link_ids']);
    list($linkId) = toValueRandQc(array_values($widgetRow['link_ids']), $omit[$offerId]['widgets']['linksIds'], 1);

    $o = array(
        'of'    => $offerRow['id'],
        'tz'    => $tizerRow['id'],
        'wj'    => $widgetRow['id'],
        'tx'    => $textRow['id'],
        'im'    => $imageRow['id'],
        'ln'    => $linkId,
    );

    if (defined('QC_LINK_TRAFFBACK_ID')) {
        $o['tbid'] = QC_LINK_TRAFFBACK_ID;
    }

    $itemRow['link'] = 'http://' . QC_TDS_DOMAIN . '/jump.php?' . http_build_query($o);
}
unset($itemRow);

shuffle($itemRowsetOptimized);

$taOutput = array(
    'top'       => '',
    'bottom'    => ''
);
$taValue = strtolower(toValueQc($configuration, array('view', 'text-align'), 'bottom'));
if (!array_key_exists($taValue, $taOutput)) {
    $taValue = 'bottom';
}

?>

<div class="qccpa-<?=$tizerRow['id']; ?>">
    <?= $buildViewBlocks[$mode]['begin']; ?>
    <? for ($iRow = 0; $iRow < $iRowCount; $iRow++): ?>
        <?= $buildViewBlocks[$mode]['begin_row']; ?>
        <? for ($jColumn = 0; $jColumn < $jColumnCount; $jColumn++): ?>
            <?= sprintf($buildViewBlocks[$mode]['begin_column'], $taValue); ?>
            <? $itemRow = $itemRowsetOptimized[$itemCount-- - 1]; ?>

            <? $taOutput[$taValue] = <<<TEXT
            <div class="qccpa-text"><a class="qccpa-text-link" target="_blank" href="{$itemRow['link']}" rel="nofollow">{$itemRow['text']}</a></div>
TEXT;

?>
            <?= $taOutput['top']; ?>

            <div class="qccpa-item">
                <a target="_blank" class="qccpa-img-link" href="<?=$itemRow['link']; ?>" rel="nofollow"><img src="<?=$itemRow['image_link'];?>" /></a>
            </div>

            <?= $taOutput['bottom']; ?>
            <div class="qccpa-cfx"></div>
            <?= $buildViewBlocks[$mode]['end_column']; ?>
        <?endfor; ?>
        <?= $buildViewBlocks[$mode]['end_row']; ?>
    <?endfor; ?>
    <?= $buildViewBlocks[$mode]['end']; ?>

    <style type="text/css">
        <? if (
            !empty($tizerRow['override_css_enable'])
            && ($overrideCss = trim($tizerRow['override_css']))
            && !empty($overrideCss)
        ):
            echo $overrideCss;
        else:
            echo $tizerRow['css'];
        endif;
        ?>
    </style>
</div>