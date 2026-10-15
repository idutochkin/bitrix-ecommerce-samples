<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\BonusService;

class BonusController extends Controller
{
    public function configureActions()
    {
        return [];
    }

    /**
     * @param array $userIds
     * @param int $value
     */
    public function increaseJetonAction(array $userIds, int $value): void
    {
        foreach ($userIds as $id) {
            BonusService::increase($id, $value, BonusService::TYPE_JETON);
        }
    }

    /**
     * @param array $userIds
     * @param int $value
     */
    public function increaseWinnnnerAction(array $userIds, int $value): void
    {
        foreach ($userIds as $id) {
            BonusService::increase($id, $value, BonusService::TYPE_WINNNNER);
        }
    }
}