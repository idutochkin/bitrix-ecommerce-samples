<?
namespace Westpower\Winnnner\PaySystem\Handlers;

use Westpower\Winnnner\Orm\CurrencyTable;
use Westpower\Winnnner\Orm\OptionTable;
use Westpower\Winnnner\Orm\OrderTable;
use Westpower\Winnnner\Orm\PaySystemTable;
use Westpower\Winnnner\Orm\ProductTable;
use Westpower\Winnnner\Service\OrderService;
use Westpower\Winnnner\Service\PlatformService;
use Westpower\Winnnner\Service\ProductService;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\SystemException;

class Pult24 extends Base
{
    private const API_URL = 'https://ecommerce.pult24.kz/payment/create';

    private string $USER;
    private string $PASSWORD;
    private string $MERCHANT_ID;
    private string $WITHDRAW_USER;
    private string $WITHDRAW_PASSWORD;
    private string $WITHDRAW_MERCHANT_ID;
    private int $WITHDRAW_ACQUIRING_ID;

    /**
     * Pult24 constructor.
     */
    function __construct()
    {
        $className = strtoupper((new \ReflectionClass(__CLASS__))->getShortName());

        $options = OptionTable::getList(['filter' => ['MODULE_ID' => PlatformService::MODULE_ID, '%NAME' => $className]])->fetchAll();
        $options = array_column($options, 'VALUE', 'NAME');

        $this->USER = (string) $options[$className . '_USER'];
        $this->PASSWORD = (string) $options[$className . '_PASSWORD'];
        $this->MERCHANT_ID = (string) $options[$className . '_MERCHANT_ID'];
        $this->WITHDRAW_USER = (string) $options[$className . '_WITHDRAW_USER'];
        $this->WITHDRAW_PASSWORD = (string) $options[$className . '_WITHDRAW_PASSWORD'];
        $this->WITHDRAW_MERCHANT_ID = (string) $options[$className . '_WITHDRAW_MERCHANT_ID'];
        $this->WITHDRAW_ACQUIRING_ID = (int) $options[$className . '_WITHDRAW_ACQUIRING_ID'];
    }

    /**
     * @param $request
     * @param int $paySystemId
     * @return bool
     */
    public function isMyResponse($request, int $paySystemId): bool
    {
        $data = Json::decode($request->getInput());

        return isset($data['orderId']) && $this->isValidCallback($request, (int) $data['orderId']);
    }

    /**
     * @param $request
     * @return array
     */
    public function processRequest($request): array
    {
        $data = Json::decode($request->getInput());
        $order = OrderTable::getList(['filter' => ['ID' => (int) $data['orderId']]])->fetch();

        if ($data['status'] == 1 && $data['errCode'] == 0 && $order['ID'] > 0 && $order['STATUS'] != OrderService::STATUS_COMPLETE) {
            OrderTable::update($order['ID'], ['STATUS' => OrderService::STATUS_COMPLETE]);

            $product = ProductTable::getList(['filter' => ['ID' => $order['PRODUCT_ID']]])->fetch();

            switch ($product['TYPE']) {
                case ProductService::TYPE_WITHDRAW:
                    OrderService::withdraw($order['USER_ID'], $order);
                    break;
                case ProductService::TYPE_BUY_JETON:
                    OrderService::buyJeton($order['USER_ID'], $product);
                    break;
                case ProductService::TYPE_BUY_SUBSCRIBE:
                    OrderService::buySubscribe($order['USER_ID'], $product);
                    break;
            }
        }

        return ['accepted' => true];
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function initiatePay(int $orderId): array
    {
        return $this->createPayment($orderId, false);
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function initiateWithdraw(int $orderId): array
    {
        return $this->createPayment($orderId, true);
    }

    /**
     * @param int $orderId
     * @param bool $withdraw
     * @return array
     */
    private function createPayment(int $orderId, bool $withdraw): array
    {
        $order = OrderTable::getList(['filter' => ['ID' => $orderId]])->fetch();
        if (!$order)
            return self::error('', 'Заказ ' . $orderId . ' не найден');

        $order['PAY_SYSTEM'] = PaySystemTable::getList(['filter' => ['ID' => $order['PAY_SYSTEM_ID']]])->fetch();
        $order['CURRENCY'] = CurrencyTable::getList(['filter' => ['ID' => $order['PAY_SYSTEM']['CURRENCY_ID']]])->fetch();
        $order['USER'] = $GLOBALS['USER']->GetByID($order['USER_ID'])->Fetch();
        $order['PRODUCT'] = ProductTable::getById($order['PRODUCT_ID'])->fetch();

        $cost = $withdraw ? $order['PAY_SYSTEM']['COST_OUT'] : $order['PAY_SYSTEM']['COST_IN'];

        $data = [
            'merchantId' => $withdraw ? $this->WITHDRAW_MERCHANT_ID : $this->MERCHANT_ID,
            'orderId' => (string) $order['ID'],
            'amount' => ((float) $order['SUM'] * $order['CURRENCY']['RATE'] + $cost) * 100,
            'description' => $withdraw ? 'Вывод средств' : $order['PRODUCT']['NAME'],
            'customerData' => ['phone' => $order['USER']['LOGIN'], 'email' => $order['USER']['EMAIL']],
            'demo' => false,
            'callbackUrl' => $this->getCallbackUrl((int) $order['ID']),
            'returnUrl' => $this->getSiteUrl(),
        ];

        if ($withdraw) {
            $data['type'] = 1;
            $data['acquiringId'] = $this->WITHDRAW_ACQUIRING_ID;
        }

        $httpClient = new HttpClient();
        $httpClient->setAuthorization(
            $withdraw ? $this->WITHDRAW_USER : $this->USER,
            $withdraw ? $this->WITHDRAW_PASSWORD : $this->PASSWORD
        );
        $httpClient->setHeader('Content-Type', 'application/json', true);
        $httpClient->query(HttpClient::HTTP_POST, self::API_URL, Json::encode($data, JSON_UNESCAPED_UNICODE));

        try {
            $response = Json::decode($httpClient->getResult());
        } catch (SystemException $e) {
            return self::error($httpClient->getStatus(), $e->getMessage());
        }

        if ($httpClient->getStatus() !== 201)
            return self::error($httpClient->getStatus(), $response['error']);

        return [
            'result' => [
                'url' => $response['url']
            ]
        ];
    }

    /**
     * @param int|string $status
     * @param string|null $message
     * @return array
     */
    private static function error($status, ?string $message): array
    {
        return [
            'ERROR' => [
                'service_message' => [
                    'status' => $status,
                    'message' => $message,
                ],
                'title' => 'Ошибка',
                'message' => 'Произошла ошибка, обратитесь в администрацию портала.',
            ]
        ];
    }
}
