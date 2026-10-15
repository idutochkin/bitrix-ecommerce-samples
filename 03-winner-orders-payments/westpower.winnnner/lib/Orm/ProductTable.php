<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

class ProductTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_product';
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
            new StringField('NAME', [
                'title' => Loc::getMessage('NAME'),
                'required' => true,
            ]),
            new IntegerField('PRICE', [
                'title' => Loc::getMessage('PRICE'),
                'required' => true,
            ]),
            new IntegerField('VALUE', [
                'title' => Loc::getMessage('VALUE'),
                'required' => true,
            ]),
            new IntegerField('TYPE', [
                'title' => Loc::getMessage('TYPE'),
                'required' => true,
            ]),
        ];
    }
}