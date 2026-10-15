<?
namespace Westpower\Winnnner\Service;

use Dbogdanoff\Bitrix\Vue;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Engine\Response\Converter;
use Bitrix\Main\UserConsent\Internals\AgreementTable;
use Westpower\Winnnner\Orm\OptionTable;

class PlatformService
{
    public const MODULE_ID = 'westpower.winnnner';

    public const SERIALIZED_FIELDS = [
        'CALENDAR',
        'BLOCKED_IP',
        'DECREASE_JETONS_BEFORE_GAME_ENDS',
        'PAY_SYSTEM_ACCESS',
    ];

    /**
     * @param bool $strict
     * @return array
     */
    public static function getFields(bool $strict = true): array
    {
        $result = [];

        $options = OptionTable::getList(['filter' => ['MODULE_ID' => self::MODULE_ID]])->fetchAll();
        foreach ($options as $item) {
            if ($strict) {
                if (substr($item['NAME'], 0, 5) == 'GAME_')
                    continue;
            }

            $result[$item['NAME']] = in_array($item['NAME'], self::SERIALIZED_FIELDS) ? unserialize($item['VALUE']) : $item['VALUE'];
            $result[$item['NAME']] = isset($result[$item['NAME']]['VALUE']) ? $result[$item['NAME']]['VALUE'] : $result[$item['NAME']];
        }

        $agreements = AgreementTable::getList()->fetchAll();
        foreach ($agreements as $item) {
            $result['AGREEMENTS'][$item['CODE']] = [
                'TITLE' => $item['NAME'],
                'MESSAGE' => $item['AGREEMENT_TEXT'],
            ];
        }

        return $result;
    }

    /**
     * @param array $calendar
     * @return int
     */
    public static function getCalendarGameId(array $calendar = []): int
    {
        if (!$calendar)
            $calendar = self::getFields()['CALENDAR'];

        return $calendar[(new DateTime)->format('N') - 1];
    }

    /**
     *
     */
    public static function init(): void
    {
        define('DBOGDANOFF_VUE_MINIFY', 'soft');

        $modulePath = '/local/modules/' . self::MODULE_ID;
        $vueDir = $_SERVER['DOCUMENT_ROOT'] . '/local/components-vue/';
        $vueComponents = [];
        $files = scandir($vueDir);

        foreach ($files as $file) {
            if (in_array($file, [
                    '.',
                    '..',
                ]) || !is_dir($vueDir . $file))
                continue;

            $vueComponents[] = $file;
        }

        Vue::includeComponent($vueComponents, [
            $modulePath . '/js/vue.min.js',
            $modulePath . '/js/gsap.min.js',
            $modulePath . '/js/moment-with-locales.min.js',
        ]);

        $platformFields = self::getFields();
        $gameFields = GameService::getFields(self::getCalendarGameId($platformFields['CALENDAR']), true);
        $userFields = UserService::getAccessFieldsCurrentUser();

        $converter = new Converter(Converter::OUTPUT_JSON_FORMAT);

        Asset::getInstance()->addString("<script>
            let winnnnerData = {
                'moduleId': '" . self::MODULE_ID . "',
                'platform': " . Json::encode($converter->process($platformFields), JSON_UNESCAPED_UNICODE) . ",
                'game': " . Json::encode($converter->process($gameFields), JSON_UNESCAPED_UNICODE) . ",
                'user': " . Json::encode($converter->process($userFields), JSON_UNESCAPED_UNICODE) . ",
                'components': " . Json::encode($converter->process($vueComponents), JSON_UNESCAPED_UNICODE) . "
            }
        </script>");

        Asset::getInstance()->addJs($modulePath . '/js/app.js', true);
    }
}