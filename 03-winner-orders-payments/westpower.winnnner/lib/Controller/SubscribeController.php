<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\SubscribeService;

class SubscribeController extends Controller
{
    public function configureActions()
    {
        return [];
    }

    /**
     * @param array $userIds
     */
    public function deactivateAction(array $userIds): void
    {
        SubscribeService::deactivate($userIds);
    }
}