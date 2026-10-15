<?
namespace Westpower\Fiji\Repository\Orm;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\Type\DateTime;

class PagerQueueTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'westpower_fiji_pager_queue';
    }

    /**
     * @return array
     */
    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('PAGER_ID',['required' => true]),
            new IntegerField('TABLE_ID',['required' => true]),
            new IntegerField('ORDER_ID'),
            new StringField('STATUS'),
            new DatetimeField('DATE_INSERT', ['default_value' => new DateTime]),
            new DateTimeField('LAST_TRY', ['default_value' => new DateTime]),
            new IntegerField('COUNT_TRY', ['default_value' => 0]),
            new TextField('RESPONSE', ['default_value' => ''])
        ];
    }
}