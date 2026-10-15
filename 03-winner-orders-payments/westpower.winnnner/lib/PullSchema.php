<?
namespace Westpower\Winnnner;

use Westpower\Winnnner\Service\PlatformService;

class PullSchema
{
    /**
     * @return array
     */
	public static function OnGetDependentModule(): array
	{
        return [
            "MODULE_ID" => PlatformService::MODULE_ID,
            "USE" => ["PUBLIC_SECTION"]
		];
	}

    /**
     *
     */
	public static function setPullUserId(): void
    {
        if (!isset($_SESSION['SESS_AUTH']['LOGIN']))
            define('PULL_USER_ID', -1);
    }
}