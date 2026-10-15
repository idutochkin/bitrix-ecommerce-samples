<?
namespace Westpower\Winnnner\Service;

use Bitrix\Main\Type\Date;
use Westpower\Winnnner\Orm\SubscribeTable;

class SubscribeService
{
    /**
     * @param array $userIds
     */
    public static function deactivate(array $userIds): void
    {
        $dateTo = (new Date())->add('-1 day');

        $subscribes = SubscribeTable::getList(['filter' => ['USER_ID' => $userIds]]);
        while ($subscribe = $subscribes->fetch())
            SubscribeTable::update($subscribe['ID'], ['DATE_TO' => $dateTo]);
    }
}