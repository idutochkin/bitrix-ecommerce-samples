<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

$moduleId = substr(__DIR__, strrpos(__DIR__, '/') + 1);

if (!Loader::includeModule($moduleId))
    throw new Exception('Не удалось подключить модуль "' . $moduleId . '"');