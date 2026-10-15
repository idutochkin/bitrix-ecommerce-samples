<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

class CurrencyTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_currency';
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
            new StringField('CODE', [
                'title' => Loc::getMessage('CODE'),
                'required' => true,
            ]),
            new StringField('NAME', [
                'title' => Loc::getMessage('NAME'),
                'required' => true,
            ]),
            new StringField('SHORT_NAME', [
                'title' => Loc::getMessage('SHORT_NAME'),
                'required' => true,
            ]),
            new IntegerField('RATE', [
                'title' => Loc::getMessage('RATE'),
                'required' => true,
            ]),
        ];
    }
}