<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\MessageService;

class MessageController extends Controller
{
    public function configureActions()
    {
        return [];
    }

    /**
     * @param array $userIds
     * @param string $message
     */
    public function sendAction(array $userIds, string $message): void
    {
        foreach ($userIds as $id) {
            MessageService::send($id, $message);
        }
    }
}