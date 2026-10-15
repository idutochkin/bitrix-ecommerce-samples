<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/**
 *  Важно:
 * 1. Получать Fuser через byCurrentUserId
 * 2. Удалять сессию и куку BITRIX_SM_SALE_UID при смене авторизации для предотвращения переноса корзины на нового юзера
 */