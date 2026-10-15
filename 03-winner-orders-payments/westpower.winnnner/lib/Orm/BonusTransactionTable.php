<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\DateTimeField;

class BonusTransactionTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_bonus_transaction';
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
                'title' => Loc::getMessage('FROM'),
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
            new DateTimeField('TIMESTAMP', [
                'title' => Loc::getMessage('TIMESTAMP'),
                'nullable' => true,
                'default_value' => new DateTime(),
            ]),
        ];
    }
}