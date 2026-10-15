<?
namespace Westpower\Fiji\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Application;
use Exception;
use DOMDocument;
use Bitrix\Main\Engine\ActionFilter;
use Westpower\Fiji\Service\OrderService;
use Westpower\Fiji\Service\MenuService;
use Westpower\Fiji\Repository\OptionRepository;
use Bitrix\Sale\Order;

class OrderController extends Controller
{
    /**
     * @return \string[][][]
     */
    public function configureActions(): array
    {
        return [
            'tracker' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                    ActionFilter\Csrf::class,
                ],
            ],
        ];
    }

    /**
     * @throws Exception
     */
    public function checkAccess()
    {
        $tableId = (int) str_replace('table_', '', $GLOBALS['USER']->GetLogin());
		if ($tableId <= 0 || OptionRepository::get('active_order') !== 'Y')
            throw new Exception('Нет доступа');
    }

    /**
     * @param int $productId
     * @param int $modifierId
     * @param int $quantity
     * @return array
     */
    public function add2BasketAction(int $productId, int $modifierId, int $quantity): array
    {
        $this->checkAccess();
        return OrderService::add2Basket($productId, $modifierId, $quantity);
    }

    /**
     * @param int $productId
     * @param int $modifierId
     * @return array
     */
    public function checkModifierInBasketAction(int $productId, int $modifierId): array
    {
        $this->checkAccess();
        return OrderService::checkModifierInBasket($productId, $modifierId);
    }

    /**
     * @return array
     */
    public function toOrderAction(): array
    {
        $this->checkAccess();
        return OrderService::toOrder();
    }

    /**
     * @return array
     */
    public function initPayAction(): array
    {
        $this->checkAccess();
        return OrderService::initPay();
    }

    /**
     * @param string $data
     */
    public function trackerAction(string $data = ''): void
    {
        $request = Application::getInstance()->getContext()->getRequest();

        // только с IP кассового сервера
        $allowedIps = array_filter(array_map('trim', explode(',', OptionRepository::get('rkeeper_tracker_ips'))));
        if (!in_array($request->getRemoteAddress(), $allowedIps, true)) {
            \CHTTP::setStatus('403 Forbidden');
            return;
        }

        $data = $data ?: (string) $request->getInput();

        if (OptionRepository::get('rkeeper_logging') === 'Y') {
            $logDir = dirname(Application::getDocumentRoot()) . '/logs/rkeeper';
            if (!is_dir($logDir))
                mkdir($logDir, 0750, true);

            file_put_contents(
                $logDir . '/order.subscribe.' . date('Y-m') . '.txt',
                date('Y.m.d H:i:s - ') . $data . "\n\n\n",
                FILE_APPEND
            );
        }

        $xml = new DOMDocument;
        $xml->formatOutput = true;
        $xml->loadXML($data);

        $result = $xml->getElementsByTagName('a')->item(0);
        $action = $result->getAttribute('name');

        $order = $result->getElementsByTagName('Order')->item(0);
        $visitId = $order->getAttribute('visit');
        $orderId = $order->getAttribute('orderIdent');
        
        $table = $order->getElementsByTagName('Table')->item(0);
        $tableId = $table->getAttribute('code');

        $arOrder = OrderService::getOrderByVisitId($visitId, $orderId, $tableId, true);
        if (!$arOrder) 
            return;
        
        $changes = $result->getElementsByTagName('ChangeLog')->item(0);

        switch ($action) {
            case 'Save Order':
                $products = [];
                $uni = 0;

                foreach ($changes->childNodes as $item) {
                    if ($item instanceof \DOMElement) {
                        switch ($item->tagName) {
                            case 'Modi':
                                if ($uni)
                                    $products[$uni]['MODIFIER_ID'] = $item->getAttribute('id');

                                break;
                            case 'Dish':
                                $uni = (int) $item->getAttribute('uni');

                                $products[$uni]['UNI'] = $uni;
                                $products[$uni]['XML_ID'] = $item->getAttribute('id');
                                $products[$uni]['PRICE'] = (int) ($item->getAttribute('price') / 100);
                                $products[$uni]['QUANTITY'] = ($item->getAttribute('newvalue')
                                        - (isset($products[$uni]['OLD_QUANTITY']) ? $products[$uni]['OLD_QUANTITY'] : $item->getAttribute('oldvalue'))
                                    ) / 1000;
                                $products[$uni]['OLD_QUANTITY'] = $products[$uni]['OLD_QUANTITY'] ?: (int) $item->getAttribute('oldvalue');
                                $products[$uni]['NEW_QUANTITY'] = $products[$uni]['NEW_QUANTITY'] ?: (int) $item->getAttribute('newvalue');

                                break;
                        }
                    }
                }

                foreach ($products as &$product) {
                    if ($product['NEW_QUANTITY'] === 0) {
                        $sessions = $order->getElementsByTagName('Session');

                        foreach ($sessions as $session) {
                            $dishes = $session->getElementsByTagName('Dish');

                            foreach ($dishes as $dish) {
                                if ((int) $dish->getAttribute('uni') === $product['UNI']) {
                                    $modifier = $dish->getElementsByTagName('Modi');

                                    if ($modifier->length > 0) {
                                        $modifier = $modifier->item(0);

                                        $product['MODIFIER_ID'] = $modifier->getAttribute('id');
                                    }
                                }
                            }
                        }
                    }
                }

                if ($products) {
                    foreach ($products as &$product) {
                        $arProduct = MenuService::getProductByXmlId($product['XML_ID'], $product['PRICE']);

                        $product['ID'] = $arProduct['ID'];
                        $product['NAME'] = $arProduct['NAME'];
                    }

                    OrderService::update(Order::load($arOrder['ID']), $products, false);
                }

                $prePay = $changes->getElementsByTagName('Prepay');
                if ($prePay->length)
                    OrderService::payment($arOrder['ID'], $prePay->item(0)->getAttribute('amount') / 100);

                $discount = $changes->getElementsByTagName('Discount');
                if ($discount->length) {
                    $sessions = $order->getElementsByTagName('Session');

                    foreach ($sessions as $session) {
                        $discount = $session->getElementsByTagName('Discount');

                        if ($discount->length)
                            OrderService::payment($arOrder['ID'], ($discount->item(0)->getAttribute('amount') * -1) / 100);
                    }
                }

                break;
            case 'Quit Order':
                $pay = $changes->getElementsByTagName('Pay');
                if ($pay->length)
                    OrderService::payment($arOrder['ID'], $pay->item(0)->getAttribute('amount') / 100);

                $printCheck = $changes->getElementsByTagName('PrintCheck');
                if ($printCheck->length)
                    OrderService::finished($arOrder['ID']);

                break;
        }
    }
}