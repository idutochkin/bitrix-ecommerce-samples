<?
namespace Westpower\Winnnner\PaySystem\Handlers;

use Westpower\Winnnner\Orm\OrderTable;
use Westpower\Winnnner\Orm\PaySystemTable;
use Westpower\Winnnner\Orm\ProductTable;
use Westpower\Winnnner\Service\OrderService;
use Westpower\Winnnner\Service\ProductService;
use Bitrix\Main\UserTable;

class Inner extends Base
{
    /**
     * @param $request
     * @param int $paySystemId
     * @return bool
     */
    public function isMyResponse($request, int $paySystemId): bool
    {
        return false;
    }

    /**
     * @param $request
     * @return array
     */
    public function processRequest($request): array
    {
        return [];
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function initiatePay(int $orderId): array
    {
        $result = [];

        $order = OrderTable::getById($orderId)->fetch();
        $order['PAY_SYSTEM'] = PaySystemTable::getById($order['PAY_SYSTEM_ID'])->fetch();

        if(!empty($order)) {
            $order['USER'] = $GLOBALS['USER']->GetByID($order['USER_ID'])->Fetch();
            $order['PRODUCT'] = ProductTable::getById($order['PRODUCT_ID'])->fetch();

            $user = UserTable::getList(["filter" => ["ID" => $order['USER_ID']], "select" => ['ID', "UF_BALANCE_JETON", "UF_DEPOSIT", 'UF_REFERRER', 'UF_REFERRAL_ACCRUED', 'UF_DEALER']])->fetch();
            (new \CUser)->Update($user['ID'], ["UF_DEPOSIT" => $user["UF_DEPOSIT"] - $order['PRODUCT']['PRICE']]);

            switch ($order['PRODUCT']['TYPE']) {
                case ProductService::TYPE_BUY_JETON:
                    OrderService::buyJeton($order['USER_ID'], $order['PRODUCT']);
                    break;
                case ProductService::TYPE_BUY_SUBSCRIBE:
                    OrderService::buySubscribe($order['USER_ID'], $order['PRODUCT']);
                    break;
            }

            OrderTable::update($order['ID'], ['STATUS' => OrderService::STATUS_COMPLETE]);
        }

        return $result;
    }
}