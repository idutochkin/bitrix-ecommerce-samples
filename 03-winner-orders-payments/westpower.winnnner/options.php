<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Westpower\Winnnner\Service\LoggerService;

global $USER, $APPLICATION;

$moduleId = substr(__DIR__, strrpos(__DIR__, '/') + 1);

Loader::includeModule($moduleId);

$request = Application::getInstance()->getContext()->getRequest();
$mid = $request->getQuery('mid');
$post = $request->getPostList();
$file = $request->getFileList();

$arOptions = [
    'main' => [
        'Основные настройки',
        ['test', 'test', '', ['text']],
    ],
];

$arTabs = [];
$arTabs[] = [
    'DIV'   => 'main',
    'TAB'   => 'Настройки',
    'TITLE' => 'Основные настройки',
];

$tabControl = new CAdminTabControl('tabControl', $arTabs);

if ($request->isPost() && check_bitrix_sessid() && $post['Update']) {
    $log = [];

    foreach ($arOptions as $arOption) {
        foreach ($arOption as $option) {
            if (!is_array($option))
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

            if ($new != $old) {
                $log[] = [
                    'field' => $option[1] . ' [' . $option[0] . ']',
                    'old' => $old,
                    'new' => $new
                ];
            }
        }
    }

    if ($log)
        LoggerService::Add(0, LoggerService::SETTINGS_CHANGE, $log);
}

$tabControl->Begin();
?>
<form name="mfc_counters_options" method="POST"
      action="<?=$APPLICATION->GetCurPage()?>?mid=<?=htmlspecialchars($mid)?>&lang=<?=LANG?>&mid_menu=1"
      enctype="multipart/form-data">
    <?
    echo bitrix_sessid_post();

    foreach ($arOptions as $option) {
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

    $tabControl->Buttons();
    ?>
    <input
        type="submit"
        name="Apply"
        value="Сохранить"
        title="Сохранить"
        <?=$request['back_url_settings'] === '' ? ' class="adm-btn-save"' : ''?>
        <?=!$USER->CanDoOperation('edit_other_settings') ? ' disabled' : ''?>>
    <input type="hidden" name="Update" value="Y">
    <?
    echo bitrix_sessid_post();
    $tabControl->End();
    ?>
</form>