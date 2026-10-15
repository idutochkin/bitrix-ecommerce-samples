<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\DateTimeField;

class OrderTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_order';
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
            new IntegerField('PRODUCT_ID', [
                'title' => Loc::getMessage('PRODUCT_ID'),
                'required' => true,
            ]),
            new IntegerField('PAY_SYSTEM_ID', [
                'title' => Loc::getMessage('PAY_SYSTEM_ID'),
                'required' => true,
            ]),
            new IntegerField('SUM', [
                'title' => Loc::getMessage('SUM'),
                'required' => true,
            ]),
            new IntegerField('STATUS', [
                'title' => Loc::getMessage('STATUS'),
                'nullable' => true,
            ]),
            new DateTimeField('DATE_CREATE', [
                'title' => Loc::getMessage('DATE_CREATE'),
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