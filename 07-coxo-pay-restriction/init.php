<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;

EventManager::getInstance()->addEventHandler(
    'sale',
    'onSalePaySystemRestrictionsClassNamesBuildList',
    static function () {
        return new EventResult(EventResult::SUCCESS, [
            '\RestrictionPay' => '/local/php_interface/include/restrictionPay.php',
        ]);
    }
);
