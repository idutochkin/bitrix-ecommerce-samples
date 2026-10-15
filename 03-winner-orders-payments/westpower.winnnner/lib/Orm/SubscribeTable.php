<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\DateField;
use Bitrix\Main\ORM\Fields\DateTimeField;

class SubscribeTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_subscribe';
    }

    /**
     * @return array
     */
    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'title' => Loc::getMessage('ID'),
                'primary' => true,
                'autocomplete' => true,
            ]),
            new IntegerField('USER_ID', [
                'title' => Loc::getMessage('USER_ID'),
                'required' => true,
            ]),
            new DateField('DATE_TO', [
                'title' => Loc::getMessage('DATE_TO'),
                'required' => true,
            ]),
            new DateTimeField('TIMESTAMP', [
                'title' => Loc::getMessage('TIMESTAMP'),
                'nullable' => true,
                'default_value' => new DateTime(),
            ]),
        ];
    }
}