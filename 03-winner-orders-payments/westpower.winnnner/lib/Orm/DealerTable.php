<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

class DealerTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_dealer';
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
            new StringField('DOMAIN', [
                'title' => Loc::getMessage('DOMAIN'),
                'nullable' => true,
                'serialized' => true
            ]),
            new IntegerField('PERCENT', [
                'title' => Loc::getMessage('PERCENT'),
                'required' => true,
            ]),
        ];
    }
}