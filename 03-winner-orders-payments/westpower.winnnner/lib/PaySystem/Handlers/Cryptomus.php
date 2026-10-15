<?
namespace Westpower\Winnnner\PaySystem\Handlers;

use Bitrix\Main\Context;
use Westpower\Winnnner\Orm\CurrencyTable;
use Westpower\Winnnner\Orm\OptionTable;
use Westpower\Winnnner\Orm\OrderTable;
use Westpower\Winnnner\Orm\PaySystemTable;
use Westpower\Winnnner\Orm\ProductTable;
use Westpower\Winnnner\Service\OrderService;
use Westpower\Winnnner\Service\PlatformService;
use Westpower\Winnnner\Service\ProductService;
use Bitrix\Main\Web\Json;
use Cryptomus\Api\Client as CryptomusClient;
use Cryptomus\Api\RequestBuilderException;
use Bitrix\UI\Barcode\Barcode;
use Bitrix\UI\Barcode\BarcodeDictionary;

class Cryptomus extends Base
{
    private string $MERCHANT_UUID;
    private string $PAYMENT_KEY;
    private string $PAYOUT_KEY;

    /**
     * Cryptomus constructor.
     */
    function __construct()
    {
        $className = strtoupper((new \ReflectionClass(__CLASS__))->getShortName());

        $options = OptionTable::getList(['filter' => ['MODULE_ID' => PlatformService::MODULE_ID, '%NAME' => $className]])->fetchAll();
        $options = array_column($options, 'VALUE', 'NAME');

        $this->MERCHANT_UUID = (string) $options[$className . '_MERCHANT_UUID'];
        $this->PAYMENT_KEY = (string) $options[$className . '_PAYMENT_KEY'];
        $this->PAYOUT_KEY = (string) $options[$className . '_PAYOUT_KEY'];
    }

    /**
     * @param $request
     * @param int $paySystemId
     * @return bool
     */
    public function isMyResponse($request, int $paySystemId): bool
    {
        $data = Json::decode($request->getInput());

        switch ($data['type']) {
            case 'payout':
                $apiKey = $this->PAYOUT_KEY;
                break;
            case 'payment':
                $apiKey = $this->PAYMENT_KEY;
                break;
            default:
                return false;
        }

        $sign = $data['sign'];
        unset($data['sign']);

        return isset($data['order_id'])
            && hash_equals(md5(base64_encode(Json::encode($data, JSON_UNESCAPED_UNICODE)) . $apiKey), $sign);
    }

    /**
     * @param $request
     * @return array
     */
    public function processRequest($request): array
    {
        $data = Json::decode($request->getInput());
        $order = OrderTable::getList(['filter' => ['ID' => $data['order_id']]])->fetch();

        if ($order['ID'] > 0 && $order['STATUS'] !== OrderService::STATUS_COMPLETE) {
            switch ($data['status']) {
                case 'paid':
                case 'paid_over':
                    $status = OrderService::STATUS_COMPLETE;
                    break;
                case 'wrong_amount_waiting':
                    $status = OrderService::STATUS_PART_COMPLETE;
                    break;
                case 'wrong_amount':
                case 'fail':
                    $status = OrderService::STATUS_WRONG;
                    break;
                default:
                    $status = null;
            }

            if ($status)
                OrderTable::update($order['ID'], ['STATUS' => $status]);

            if ($status === OrderService::STATUS_COMPLETE && $data['is_final'] === true) {
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
        }

        return ['accepted' => true];
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function initiatePay(int $orderId): array
    {
        $to_currency = 'USDT';
        $network = 'TRON';

        if (!$to_currency || !$network)
            return [
                'RESULT' => [
                    'URL' => '/payment/?orderId=' . $orderId
                ]
            ];

        $result = [];

        $order = OrderTable::getList(['filter' => ['ID' => $orderId]])->fetch();
        $order['PAY_SYSTEM'] = PaySystemTable::getList(["filter" => ["ID" => $order['PAY_SYSTEM_ID']]])->fetch();
        $order['CURRENCY'] = CurrencyTable::getList(["filter" => ["ID" => $order['PAY_SYSTEM']['CURRENCY_ID']]])->fetch();

        if(!empty($order)) {
            $order['USER'] = $GLOBALS['USER']->GetByID($order['USER_ID'])->Fetch();
            $order['PRODUCT'] = ProductTable::getById($order['PRODUCT_ID'])->fetch();

            $payment = CryptomusClient::payment($this->PAYMENT_KEY, $this->MERCHANT_UUID);

            $params = [
                'order_id' => (string) $order['ID'],
                'amount' => (string) ((int) $order['SUM'] * $order['CURRENCY']['RATE'] + $order['PAY_SYSTEM']['COST_IN']),
                'currency' => $order['CURRENCY']['CODE'],
                'to_currency' => $to_currency,
                'network' => $network,
                'url_return' => $this->getSiteUrl(),
                'url_callback' => $this->getCallbackUrl((int) $order['ID'])
            ];

            try {
                $arResponse = $payment->create($params);

                if ($arResponse['address'])
                    $result = [
                        "RESULT" => [
                            'TITLE' => 'Оплата',
                            'MESSAGE' => 'Для оплаты совершите перевод <b class="copy-text js_copy-text" data-success-text="Сумма скопирована">' . $arResponse['payer_amount']
                                . '</b> <b>' . $arResponse['payer_currency'] . '</b> на адрес - <b class="copy-text js_copy-text" data-success-text="Адрес скопирован">' . $arResponse['address']
                                . '</b><br>После оплаты платеж поступит в течении нескольких минут.<br>Отслеживайте статус выплаты в личном кабинете.<br>'
                                . '<div style="text-align:center">'
                                . (new Barcode())
                                    ->options(['w' => 150, 'h' => 150])
                                    ->format(BarcodeDictionary::FORMAT_SVG)
                                    ->render($arResponse['address'])
                                . '</div>'
                        ]
                    ];
                elseif ($arResponse['url'])
                    $result = [
                        "RESULT" => [
                            'URL' => $arResponse['url']
                        ]
                    ];
                else
                    $result = [
                        "ERROR" => [
                            "SERVICE_MESSAGE" => [
                                'STATUS' => 400,
                                'MESSAGE' => $arResponse,
                            ],
                            "TITLE" => 'Ошибка',
                            "MESSAGE" => "Произошла ошибка, обратитесь в администрацию портала.",
                        ]
                    ];
            } catch (RequestBuilderException $e) {
                $result = [
                    "ERROR" => [
                        "SERVICE_MESSAGE" => [
                            'STATUS' => 400,
                            'MESSAGE' => $e->getMessage(),
                        ],
                        "TITLE" => 'Ошибка',
                        "MESSAGE" => "Произошла ошибка, обратитесь в администрацию портала.",
                    ]
                ];
            }
        }

        return $result;
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function initiateWithdraw(int $orderId): array
    {
        $request = Context::getCurrent()->getRequest();

        $to_currency = 'USDT'; //$request->get('to_currency');
        $network = 'TRON'; //$request->get('network');
        $address = $request->get('address');

        if (!$to_currency || !$network || !$address)
            return [
                'RESULT' => [
                    'URL' => '/payment/?orderId=' . $orderId
                ]
            ];

        $result = [];

        $order = OrderTable::getList(['filter' => ['ID' => $orderId]])->fetch();
        $order['PAY_SYSTEM'] = PaySystemTable::getList(["filter" => ["ID" => $order['PAY_SYSTEM_ID']]])->fetch();
        $order['CURRENCY'] = CurrencyTable::getList(["filter" => ["ID" => $order['PAY_SYSTEM']['CURRENCY_ID']]])->fetch();

        if(!empty($order)) {
            $order['USER'] = $GLOBALS['USER']->GetByID($order['USER_ID'])->Fetch();
            $payout = CryptomusClient::payout($this->PAYOUT_KEY, $this->MERCHANT_UUID);

            $params = [
                'order_id' => (string) $order['ID'],
                'amount' => (string) ((float) $order['SUM'] * $order['CURRENCY']['RATE'] + $order['PAY_SYSTEM']['COST_OUT']),
                'currency' => $order['CURRENCY']['CODE'],
                'to_currency' => $to_currency,
                'network' => $network,
                'address' => $address,
                'is_subtract' => '0',
                'url_callback' => $this->getCallbackUrl((int) $order['ID'])
            ];

            try {
                $arResponse = $payout->create($params);

                switch ($arResponse['status']) {
                    case 'process':
                        $result = [
                            'RESULT' => [
                                'TITLE' => 'Выплата',
                                'MESSAGE' => 'Заявка на выплату принята.<br>Выплата поступит в течении нескольких минут.<br>Отслеживайте статус выплаты в личном кабинете.'
                            ]
                        ];
                        break;
                    default:
                        $result = [
                            "ERROR" => [
                                "SERVICE_MESSAGE" => [
                                    'STATUS' => 400,
                                    'MESSAGE' => !is_array($arResponse) ? $arResponse : $arResponse['status'],
                                ],
                                "TITLE" => 'Ошибка',
                                "MESSAGE" => "Произошла ошибка, обратитесь в администрацию портала.",
                            ]
                        ];
                }
            } catch (RequestBuilderException $e) {
                $result = [
                    "ERROR" => [
                        "SERVICE_MESSAGE" => [
                            'STATUS' => 400,
                            'MESSAGE' => $e->getMessage(),
                        ],
                        "TITLE" => 'Ошибка',
                        "MESSAGE" => "Произошла ошибка, обратитесь в администрацию портала.",
                    ]
                ];
            }
        }

        return $result;
    }
}