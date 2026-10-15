<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\DealerService;

class DealerController extends Controller
{
    public function configureActions()
    {
        return [];
    }

    /**
     * @param array $userIds
     * @param int $dealerId
     */
    public function setDealerAction(array $userIds, int $dealerId): void
    {
        DealerService::setDealer($userIds, $dealerId);
    }
}