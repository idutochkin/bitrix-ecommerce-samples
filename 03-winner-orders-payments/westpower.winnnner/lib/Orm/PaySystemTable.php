<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DateTimeField;

class PaySystemTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_pay_system';
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
            new BooleanField('ACTIVE', [
                'title' => Loc::getMessage('ACTIVE'),
                'nullable' => true,
                'values' => ['N', 'Y'],
                'default' => 'Y',
                'default_value' => 'Y',
            ]),
            new StringField('NAME', [
                'title' => Loc::getMessage('NAME'),
                'required' => true,
            ]),
            new StringField('DESCRIPTION', [
                'title' => Loc::getMessage('DESCRIPTION'),
                'nullable' => true,
            ]),
            new IntegerField('PICTURE', [
                'title' => Loc::getMessage('PICTURE'),
                'nullable' => true,
            ]),
            new StringField('HANDLER', [
                'title' => Loc::getMessage('HANDLER'),
                'required' => true,
            ]),
            new IntegerField('COST_IN', [
                'title' => Loc::getMessage('COST_IN'),
                'nullable' => true,
            ]),
            new IntegerField('COST_OUT', [
                'title' => Loc::getMessage('COST_OUT'),
                'nullable' => true,
            ]),
            new IntegerField('CURRENCY_ID', [
                'title' => Loc::getMessage('CURRENCY_ID'),
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