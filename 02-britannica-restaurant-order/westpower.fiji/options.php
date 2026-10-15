<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Engine\UrlManager;
use Westpower\Fiji\Service\Catalog\ImportService;
use Westpower\Fiji\Service\Catalog\UpdateService;
use Westpower\Fiji\Repository\Orm\PagerQueueTable;

global $USER, $APPLICATION;

$moduleId = $module_id = 'westpower.fiji';

Loader::includeModule($moduleId);

$request = Application::getInstance()->getContext()->getRequest();

$mid = $request->getQuery('mid');
$post = $request->getPostList();
$file = $request->getFileList();

$request = Application::getInstance()->getContext()->getRequest();
$host = $request->getHttpHost();
$siteUrl = ($request->isHttps() ? 'https://' : 'http://') . $host;

$rkeeper_url = (string) Option::get($moduleId, 'rkeeper_url');

$arAllOptions = [
    'main' => [
        ['active_order', 'Активность системы заказов:', '', ['checkbox']],
        ['max_tables', 'Количество столов:', '', ['text', 30], '', ''],
        ['cash_day_closing_time', 'Время закрытия кассового дня (в часах):', 10, ['text', 10], '', ''],
    ],
    'rkeeper' => [
        'Подключение',
        ['rkeeper_url', 'Адрес:', '', ['text', 30]],
        ['rkeeper_login', 'Логин:', '', ['text', 30]],
        ['rkeeper_password', 'Пароль:', '', ['password', 30]],
        ['rkeeper_ssl_verify', 'Проверять SSL-сертификат кассы:', 'Y', ['checkbox']],
        ['rkeeper_tracker_ips', 'IP кассового сервера для уведомлений (через запятую):', '', ['text', 30]],
        ['rkeeper_logging', 'Логировать активность станции:', '', ['checkbox'], '', '../logs/rkeeper/order.subscribe.ГГГГ-ММ.txt'],
        !empty($rkeeper_url)
            ? ['note' => '<b>Активация механизма подписок на заказы:</b><br>' .
            'Начиная с версии 7.6.5.371 настройки Http Order Notify находятся в «Свойствах» кассового сервера.<br>' .
            'У версий ниже указанной интерфейс добавляется на закладку «Устройства» кассового сервера (Сервис → Станции и устройства).<br>' .
            'В секции настроек «Http Order Notify» в параметре DestURLs вписываем значение:<br>' .
            '<i>' . $rkeeper_url .
            '/rk7api/v1/subscribe.xml?service=httpnotf&url=' . $siteUrl . UrlManager::getInstance()->create('westpower:fiji.OrderController.tracker') .
            '</i>']
            : [''],
        'Дополнительные параметры',
        ['rkeeper_station_code', 'Код станции:', '', ['text', 30]],
        ['rkeeper_cashier_code', 'Код кассира "Система":', '', ['text', 30]],
        'Обмен каталога товаров',
        ['rkeeper_catalog_update_pause', 'Пауза обновления цен (в секундах):', '', ['text', 30]],
        ['rkeeper_catalog_import_pause', 'Пауза обновления каталога (в секундах):', '', ['text', 30]],
        ['rkeeper_catalog_import_sections_id', 'ID разделов каталога, из которых переносить товары в меню (через запятую):', '', ['text', 30]],
        ['rkeeper_filter_section_id', 'Фильтер разделов rkeeper (ID через запятую):', '', ['text', 30]],
        ['rkeeper_limit_set_element', 'Лимит создание и обновление элементов (по умолчанию 300, измененное значение будет применено при следующей итерации):', '', ['text', 30]],
        ['rkeeper_limit_set_section', 'Лимит создание и обновление разделов (по умолчанию 300, измененное значение будет применено при следующей итерации):', '', ['text', 30]],
        ['rkeeper_limit_set_price', 'Лимит создание и обновление цен (по умолчанию 300, измененное значение будет применено при следующей итерации):', '', ['text', 30]],
        ['note' => (new ImportService)->GetInfo()],
        ['note' => (new UpdateService)->GetInfo()],
    ],
	'pager'=>[
        ['pager_active', 'Активность:', 'Y', ['checkbox']],
		['pager_ip', 'Адрес:', '', ['text', 30]],
        ['pager_port', 'Порт:', '', ['text', 30]],
		['pager_queue_active', 'Использовать очередь уведомлений', '', ['checkbox']],
	]

];

$arTabs = [];
$arTabs[] = [
    'DIV'   => 'main',
    'TAB'   => 'Настройки',
    'TITLE' => 'Основные настройки',
];
$arTabs[] = [
    'DIV'   => 'rkeeper',
    'TAB'   => 'RKeeper',
    'TITLE' => 'Настройки обмена с RKeeper',
];
$arTabs[] = [
    'DIV'   => 'pager',
    'TAB'   => 'Пейджер',
    'TITLE' => 'Настройки отправки сообщений на пейджер',
];
$arTabs[] = [
    'DIV'   => 'right',
    'TAB'   => 'Доступ',
    'TITLE' => 'Настройка прав',
];

$tabControl = new CAdminTabControl('tabControl', $arTabs);

if ($request->isPost() && check_bitrix_sessid() && $post['Update']) {
    $log = [];

    foreach ($arAllOptions as $arOption) {
        foreach ($arOption as $option) {
            if (!is_array($option) || !$option[0])
                continue;

            if (isset($option[4]) && $option[4] === 'Y')
                continue;

            $old = Option::get($moduleId, $option[0]);
            $new = '';

            if ($option[3][0] == 'file') {
                $arFile = $file[$option[0]];
                $arFile["old_file"] = (int)Option::get($moduleId, $option[0]);
                $arFile["del"] = $post[$option[0] . "_del"];
                $arFile["MODULE_ID"] = $moduleId;
                $fId = CFile::SaveFile($arFile, $moduleId);

                if ($fId > 0) {
                    Option::set($moduleId, $option[0], $fId);
                    $new = $fId;
                } else
                    $new = $old;
            } else if ($option[3][0] === 'multiselectbox') {
                $new = implode(',', $post[$option[0]]);
                Option::set($moduleId, $option[0], $new);
            } else {
                $new = trim($post[$option[0]]);
                Option::set($moduleId, $option[0], $new);
            }

			if ($option[0] == 'pager_queue_active') {
				if ($new !== $old && $new === 'Y') {
					$connection = Application::getConnection();

					if (!$connection->isTableExists(PagerQueueTable::getTableName()))
                        PagerQueueTable::getEntity()->createDbTable();
				}
			}

            if ($new != $old) {
                $log[] = [
                    'field' => $option[1] . ' [' . $option[0] . ']',
                    'old' => $old,
                    'new' => $new
                ];
            }
        }
    }
}

$tabControl->Begin();
?>
<form name="mfc_counters_options" method="POST"
      action="<?=$APPLICATION->GetCurPage()?>?mid=<?=htmlspecialchars($mid)?>&lang=<?=LANG?>&mid_menu=1"
      enctype="multipart/form-data">
    <?
    echo bitrix_sessid_post();

    foreach ($arAllOptions as $option) {
        $tabControl->BeginNextTab();

        foreach ($option as $arOption) {
            if ($arOption[3][0] == 'file') {
                ?>
                <tr>
                    <td valign="top" width="50%"><?=$arOption[1]?>:</td>
                    <td valign="middle" width="50%">
                        <?=CFile::InputFile($arOption[0], 0, (int)Option::get($moduleId, $arOption[0]))?>
                    </td>
                </tr>
                <?
            } else
                __AdmSettingsDrawRow($moduleId, $arOption);
        }
    }

    $tabControl->BeginNextTab();
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/admin/group_rights.php');

    $tabControl->Buttons();
    ?>
    <input
            type="submit"
            name="Apply"
            value="Сохранить"
            title="Сохранить"
        <?=$_REQUEST['back_url_settings'] === '' ? ' class="adm-btn-save"' : ''?>
        <?//=!$USER->CanDoOperation('edit_other_settings') ? ' disabled' : ''?>
        <?=$APPLICATION->GetGroupRight($moduleId) !== 'W' ? ' disabled' : ''?>>
    <input type="hidden" name="Update" value="Y">
    <input type="hidden" name="Update" value="Y">
    <?
    echo bitrix_sessid_post();
    $tabControl->End();
    ?>
</form>
