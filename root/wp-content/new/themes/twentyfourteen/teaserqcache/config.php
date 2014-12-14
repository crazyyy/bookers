<?php

/**
 * Скрипт для показа тизеров на стороне партнера
 * Осуществляет автоматическую синхронизацию и построение тизера
 * в зависимости от настроек, которые установлены в партнерском кабинете
 *
 * Для тестирования установите опцию QC_DEBUG = true и QC_TEST = true
 * и запустите скрипт /test.php
 * Будет отображен случайный тизер
 * Для определенного тизера скрипт следует запускать с параметром (айди тизера)
 * /test.php?id=236
 *
 *
 * Задаете свой уникальный идентификатор
 * Он доступен по адресу http://qcashload.biz/?p=profile.uniqid
 *
 * Если он будет неправильный, то домены не будут работать!!!
 */
define('QC_TOKEN', '9ca109db5074d221c63899e45c35076d');

/**
 * Кодировка, в которой будет отображаться тизер
 * Доступные значения:
 *      + CP1251
 *      + UTF-8
 *
 * По умолчанию: UTF-8
 */
define('QC_ENCODING', 'UTF-8');

/**
 * Прописываете здесь список id тизеров
 * к примеру:
 *        $__QC_TIZERS_IDS = array(10, 15, 10232);
 *
 * Используются исключительно для тестирования тизера,
 * когда не задан id. В этом случаи будет выбран случайный тизер
 * К примеру:
 *      http://domain.com/test.php?id=10
 */
$__QC_TIZERS_IDS = array(269);

/**
 * Использовать исключительно при установке/настройке тизера
 * Когда тизер не отображается или отображается неверно.
 * Данная опция может помочь выявить ошибки, к примеру когда нет доступа на запись кэша
 * Когда настройка закончена, нужно установить значение false
 */
define('QC_DEBUG', true);

/**
 * Использовать исключительно при установке/настройке тизера
 * Когда требуется тестировать тизер через /test.php
 * Для включения установите true
 * Когда тестирование закончено, нужно установить значение false
 */
define('QC_TEST', true);

/**
 * Через какой период времени делать обновления базы
 * Желательно не делать чаще чем раз в 5 минут, иначе есть
 * риск залететь в бан по айпи
 */
define('QC_REFRESH_DB_MINUTES', 5);

/**
 * Метод синхронизации модуля
 *
 * 1 - Синхронизация через запрос пользователя, т.е.
 *     если пользователь запросил тизер, а он отсутствует или
 *     просрочен - сработает обновление
 *     В этом случаи тизер подождет пока пройдет обновление и отобразится
 *     Задержка 1-2 секунды при отображении
 *
 * 2 - Синхронизация через cron
 *     Следуя инструкциям в файле cron/task/100-synchronize.php настроить
 *     cron и вся синхронизация будет выполнятся в фоне, абсолютно не заметно для пользователя
 *
 * Если есть возможность через cron - рекомендуем использовать cron (2) иначе (1)
 */
define('QC_SYNCHRONIZE_MODE', 1);

/**
 * Домен для синхронизации модуля
 * Данную опцию лучше оставить по умолчанию и не редактировать
 */
define('QC_SYNCHRONIZE_MIRROR_1', 'qcashload.biz');