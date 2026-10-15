<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\PaySystemService;

class PaySystemController extends Controller
{
    /**
     * @return array
     */
    public function configureActions(): array
    {
        return [];
    }

    /**
     * @param bool $increase
     * @return array
     */
    public function getListAction(bool $increase = true): array
    {
        return $this->convertKeysToCamelCase(PaySystemService::getList($increase));
    }
}