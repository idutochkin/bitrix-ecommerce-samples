<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\ProductService;

class ProductController extends Controller
{
    /**
     * @return array
     */
    public function configureActions(): array
    {
        return [];
    }

    /**
     * @param int|null $type
     * @return array
     */
    public function getListAction(int $type = null): array
    {
        return $this->convertKeysToCamelCase(ProductService::getList($type));
    }
}