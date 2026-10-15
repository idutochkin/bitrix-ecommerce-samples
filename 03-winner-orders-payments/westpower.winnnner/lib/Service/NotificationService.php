<?
namespace Westpower\Winnnner\Service;

use Bitrix\Main\Loader;
use CPullStack;

class NotificationService
{
    /**
     * @param string $action
     * @param array $data
     * @param array $userIds
     */
    public static function send(string $action, array $data = [], array $userIds = []): void
    {
        Loader::includeModule('pull');

        $params = [
            'module_id' => PlatformService::MODULE_ID,
            'command' => $action,
            'params' => $data
        ];

        if ($userIds)
            CPullStack::AddByUsers($userIds, $params);
        else
            CPullStack::AddShared($params);
    }
}