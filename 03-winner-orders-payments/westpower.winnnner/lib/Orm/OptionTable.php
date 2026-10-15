<?
namespace Westpower\Winnnner\Orm;

use Bitrix\Main\Localization\Loc,
	Bitrix\Main\ORM\Data\DataManager,
	Bitrix\Main\ORM\Fields\StringField,
	Bitrix\Main\ORM\Fields\TextField,
	Bitrix\Main\ORM\Fields\Validators\LengthValidator;

Loc::loadMessages(__FILE__);

/**
 * Class OptionTable
 * 
 * Fields:
 * <ul>
 * <li> MODULE_ID string(50) mandatory
 * <li> NAME string(100) mandatory
 * <li> VALUE text optional
 * <li> DESCRIPTION string(255) optional
 * <li> SITE_ID string(2) optional
 * </ul>
 *
 * @package Bitrix\Option
 **/

class OptionTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'b_option';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return [
			new StringField(
				'MODULE_ID',
				[
					'primary' => true,
					'validation' => [__CLASS__, 'validateModuleId'],
					'title' => Loc::getMessage('OPTION_ENTITY_MODULE_ID_FIELD')
				]
			),
			new StringField(
				'NAME',
				[
					'primary' => true,
					'validation' => [__CLASS__, 'validateName'],
					'title' => Loc::getMessage('OPTION_ENTITY_NAME_FIELD')
				]
			),
			new TextField(
				'VALUE',
				[
					'title' => Loc::getMessage('OPTION_ENTITY_VALUE_FIELD')
				]
			),
			new StringField(
				'DESCRIPTION',
				[
					'validation' => [__CLASS__, 'validateDescription'],
					'title' => Loc::getMessage('OPTION_ENTITY_DESCRIPTION_FIELD')
				]
			),
			new StringField(
				'SITE_ID',
				[
					'validation' => [__CLASS__, 'validateSiteId'],
					'title' => Loc::getMessage('OPTION_ENTITY_SITE_ID_FIELD')
				]
			),
		];
	}

	/**
	 * Returns validators for MODULE_ID field.
	 *
	 * @return array
	 */
	public static function validateModuleId()
	{
		return [
			new LengthValidator(null, 50),
		];
	}

	/**
	 * Returns validators for NAME field.
	 *
	 * @return array
	 */
	public static function validateName()
	{
		return [
			new LengthValidator(null, 100),
		];
	}

	/**
	 * Returns validators for DESCRIPTION field.
	 *
	 * @return array
	 */
	public static function validateDescription()
	{
		return [
			new LengthValidator(null, 255),
		];
	}

	/**
	 * Returns validators for SITE_ID field.
	 *
	 * @return array
	 */
	public static function validateSiteId()
	{
		return [
			new LengthValidator(null, 2),
		];
	}
}