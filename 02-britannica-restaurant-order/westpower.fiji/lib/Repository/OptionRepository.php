<?
namespace Westpower\Fiji\Repository;

use Bitrix\Main\Config\Option;

class OptionRepository
{
    const MODULE_ID = 'westpower.fiji';

    /**
     * @param string $name
     * @param string $default
     * @return mixed
     */
    public static function get(string $name, string $default = '')
    {
        return Option::get(self::MODULE_ID, $name, $default);
    }
}