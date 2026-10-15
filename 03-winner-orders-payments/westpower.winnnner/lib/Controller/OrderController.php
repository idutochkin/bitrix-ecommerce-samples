<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Type\DateTime;
use Westpower\Winnnner\Service\OrderService;

class OrderController extends Controller
{
    public function configureActions()
    {
        return [];
    }

    /**
     * @param array $order
     * @return array
     */
    public function createAction(array $order): array
    {
        $arFields = [
            'USER_ID' => $GLOBALS['USER']->GetID(),
            'PAY_SYSTEM_ID' => (int) $order['paySystem']['id'],
            'PRODUCT_ID' => (int) $order['productId'],
            'SUM' => $order['sum'],
            'FIELDS' => $order['additionalFields']
        ];

        return $this->convertKeysToCamelCase(OrderService::create($arFields));
    }

    /**
     * @param int $productType
     * @return array
     */
    public function getListAction(int $productType): array
    {
        $orders = OrderService::getList([
            'PRODUCT.TYPE' => $productType,
            'ORDER.USER_ID' => $GLOBALS['USER']->GetID(),
        ]);

        foreach ($orders as &$order) {
            if ($order['DATE_CREATE'] instanceof DateTime)
                $order['DATE_CREATE'] = $order['DATE_CREATE']->toString();
        }

        return $this->convertKeysToCamelCase($orders);
    }
}