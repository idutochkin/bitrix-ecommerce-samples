<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DateTimeField;
use CFile;

class GameTable extends DataManager
{
    const MODULE_ID = 'westpower.winnnner';

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'westpower_winnnner_game';
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
            new StringField('DESCRIPTION', [
                'title' => Loc::getMessage('DESCRIPTION'),
                'nullable' => true,
            ]),
            new IntegerField('PICTURE', [
                'title' => Loc::getMessage('PICTURE'),
                'nullable' => true,
                'save_data_modification' => fn() => [function($value) {
                    if (is_array($value))
                        $value = CFile::SaveFile($value, self::MODULE_ID);
                    return $value;
                }],
                'fetch_data_modification' => fn() => [fn($value) => !$value ? [] : CFile::GetFileArray($value)],
            ]),
            new BooleanField('ACTIVE', [
                'title' => Loc::getMessage('ACTIVE'),
                'nullable' => true,
                'values' => ['N', 'Y'],
                'default' => 'Y',
                'default_value' => 'Y',
            ]),
            new IntegerField('SORT', [
                'title' => Loc::getMessage('SORT'),
                'nullable' => true,
                'default_value' => 100,
            ]),
            new DateTimeField('TIMESTAMP', [
                'title' => Loc::getMessage('TIMESTAMP'),
                'nullable' => true,
                'default_value' => new DateTime(),
            ]),
            new IntegerField('START_TIME', [
                'title' => Loc::getMessage('START_TIME'),
                'nullable' => true,
            ]),
            new IntegerField('STOP_TIME', [
                'title' => Loc::getMessage('STOP_TIME'),
                'nullable' => true,
            ]),
            new IntegerField('SOUND_TRUE', [
                'title' => Loc::getMessage('SOUND_TRUE'),
                'nullable' => true,
                'save_data_modification' => fn() => [function($value) {
                    if (is_array($value))
                        $value = CFile::SaveFile($value, self::MODULE_ID);
                    return $value;
                }],
                'fetch_data_modification' => fn() => [fn($value) => !$value ? [] : CFile::GetFileArray($value)],
            ]),
            new IntegerField('SOUND_FALSE', [
                'title' => Loc::getMessage('SOUND_FALSE'),
                'nullable' => true,
                'save_data_modification' => fn() => [function($value) {
                    if (is_array($value))
                        $value = CFile::SaveFile($value, self::MODULE_ID);
                    return $value;
                }],
                'fetch_data_modification' => fn() => [fn($value) => !$value ? [] : CFile::GetFileArray($value)],
            ]),
            new TextField('SETTINGS', [
                'title' => Loc::getMessage('SETTINGS'),
                'nullable' => true,
                'serialized' => true,
            ]),
            new TextField('TEXTS', [
                'title' => Loc::getMessage('TEXTS'),
                'nullable' => true,
                'serialized' => true,
            ]),
            new IntegerField('FUND', [
                'title' => Loc::getMessage('FUND'),
                'nullable' => true,
            ]),
            new IntegerField('BOTS', [
                'title' => Loc::getMessage('BOTS'),
                'nullable' => true,
            ]),
            new IntegerField('WINNER_MP', [
                'title' => Loc::getMessage('WINNER_MP'),
                'nullable' => true,
            ]),
            new IntegerField('WINNER_A', [
                'title' => Loc::getMessage('WINNER_A'),
                'nullable' => true,
            ]),
            new IntegerField('WINNER_MPR', [
                'title' => Loc::getMessage('WINNER_MPR'),
                'nullable' => true,
            ]),
            new IntegerField('WINNER_MV', [
                'title' => Loc::getMessage('WINNER_MV'),
                'nullable' => true,
            ]),
            new TextField('FRAUD', [
                'title' => Loc::getMessage('FRAUD'),
                'nullable' => true,
                'serialized' => true,
            ]),
            new BooleanField('SUBSCRIBE', [
                'title' => Loc::getMessage('SUBSCRIBE'),
                'values' => ['N', 'Y'],
                'default' => 'Y',
                'default_value' => 'Y',
            ]),
            new BooleanField('SUBSCRIBE_TO_BUY_GAME', [
                'title' => Loc::getMessage('SUBSCRIBE_TO_BUY_GAME'),
                'values' => ['N', 'Y'],
                'default' => 'Y',
                'default_value' => 'Y',
            ]),
        ];
    }
}