<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\DateTimeField;

class AuthListTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_auth_list';
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
            new StringField('HTTP_REFERER', [
                'title' => Loc::getMessage('HTTP_REFERER'),
                'required' => true,
            ]),
            new StringField('HTTP_USER_AGENT', [
                'title' => Loc::getMessage('HTTP_USER_AGENT'),
                'required' => true,
            ]),
            new StringField('IP', [
                'title' => Loc::getMessage('IP'),
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