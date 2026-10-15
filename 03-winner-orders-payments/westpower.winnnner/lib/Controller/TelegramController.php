<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\TelegramService;

class TelegramController extends Controller
{
    /**
     * @return array
     */
    public function configureActions(): array
    {
        return [
            'handlerMessage' => [
                'prefilters' => []
            ],
        ];
    }

    /**
     * @throws \Exception
     */
    public function handlerMessageAction(): void
    {
        TelegramService::handlerMessage();
    }
}