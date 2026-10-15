<?
namespace Westpower\Winnnner\Service;

use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Westpower\Winnnner\Helpers\Helper;
use Westpower\Winnnner\Orm\CurrencyTable;
use Westpower\Winnnner\Orm\OrderTable;
use Westpower\Winnnner\Orm\PaySystemTable;
use Westpower\Winnnner\Orm\ProductTable;
use Westpower\Winnnner\Orm\SubscribeTable;

class OrderService
{
    public const STATUS_NEW = 1;
    public const STATUS_COMPLETE = 2;
    public const STATUS_PART_COMPLETE = 3;
    public const STATUS_WRONG = 4;

    /**
     * @param array $filter
     * @return array
     */
    public static function getList(array $filter): array
    {
        $orderFilter = [];
        $products = array_column(ProductTable::getList()->fetchAll(), null, 'ID');
        $paySystems = array_column(PaySystemTable::getList()->fetchAll(), null, 'ID');
        $currencies = array_column(CurrencyTable::getList()->fetchAll(), null, 'ID');

        if ($filter['PRODUCT.TYPE']) {
            $orderFilter['PRODUCT_ID'] = array_column(
                array_filter($products, fn($item) => $item['TYPE'] == $filter['PRODUCT.TYPE']),
                'ID'
            );
        }

        foreach ($filter as $code => $value) {
            if (stripos($code, 'ORDER.') !== false)
                $orderFilter[str_replace('ORDER.', '', $code)] = $value;
        }

        $orders = OrderTable::getList(['filter' => $orderFilter, 'order' => ['ID' => 'DESC']])->fetchAll();
        foreach ($orders as &$order) {
            $order['PRODUCT'] = $products[$order['PRODUCT_ID']];
            $order['PAY_SYSTEM'] = $paySystems[$order['PAY_SYSTEM_ID']];
            $order['PAY_SYSTEM']['CURRENCY'] = $currencies[$order['PAY_SYSTEM']['CURRENCY_ID']];
        }

        return $orders;
    }

    /**
     * @param array $fields
     * @return array
     */
    public static function create(array $fields): array
    {
        $user = $GLOBALS['USER']->GetByID($fields['USER_ID'])->Fetch();
        if (!$user)
            return ['ERROR' => 'Не найден пользователь.'];

        $product = ProductTable::getById($fields['PRODUCT_ID'])->fetch();
        if (!$product)
            return ['ERROR' => 'Не найден продукт.'];

        $paySystem = PaySystemTable::getById($fields['PAY_SYSTEM_ID'])->fetch();
        if (!$paySystem)
            return ['ERROR' => 'Не найдена платежная система.'];

        $arOrder = [
            'USER_ID' => $fields['USER_ID'],
            'PRODUCT_ID' => $product['ID'],
            'PAY_SYSTEM_ID' => $paySystem['ID'],
            'SUM' => $product['TYPE'] == ProductService::TYPE_WITHDRAW ? $fields['SUM'] : $product['PRICE'],
            'STATUS' => self::STATUS_NEW,
            'DATE_CREATE' => new DateTime(),
        ];

        if ($paySystem['HANDLER'] === 'inner') {
            if ($arOrder['SUM'] > $user['UF_DEPOSIT']) {
                return [
                    "ERROR" => [
                        'title' => 'Ошибка',
                        'message' => 'На вашем кошельке недостаточно средств.',
                        'service_message' => []
                    ]
                ];
            }
        }

        switch ($product['TYPE']) {
            case ProductService::TYPE_WITHDRAW:
                $sum = filter_var($fields['SUM'], FILTER_VALIDATE_FLOAT);
                if ($sum === false || $sum <= 0 || $sum > (float) $user['UF_DEPOSIT']) {
                    return [
                        "ERROR" => [
                            'title' => 'Ошибка',
                            'message' => 'Некорректная сумма вывода.',
                            'service_message' => []
                        ]
                    ];
                }
                $arOrder['SUM'] = $sum;
                break;
            case ProductService::TYPE_BUY_SUBSCRIBE:
                break;
            case ProductService::TYPE_BUY_JETON:
                if(self::checkJetonLimit($user['ID'])) {
                    return [
                        "ERROR" => [
                            'title' => 'Ошибка',
                            'message' => "Покупка игр невозможна - превышен лимит покупок игр.\nНовый лимит будет установлен в 00:00 по МСК.",
                            'service_message' => []
                        ]
                    ];
                }
                break;
        }

        $result = OrderTable::add($arOrder);
        if (!$result->isSuccess()) {
            return [
                "ERROR" => [
                    'title' => 'Ошибка',
                    'message' => "Произошла ошибка. Обратитесь в поддержку.",
                    "serviceMessage" => $result->getErrors()
                ]
            ];
        }

        return self::initPay($result->getId(), $product['TYPE'], $paySystem['HANDLER']);
    }

    /**
     * @param int $orderId
     * @param int $typeProduct
     * @param string $handler
     * @return array
     */
    private static function initPay(int $orderId, int $typeProduct, string $handler): array
    {
        $result = [];
        $className = '\Westpower\Winnnner\PaySystem\Handlers\\'.ucfirst($handler);

        switch ($typeProduct) {
            case ProductService::TYPE_WITHDRAW:
                $result = (new $className())->initiateWithdraw($orderId);
                break;
            case ProductService::TYPE_BUY_JETON:
            case ProductService::TYPE_BUY_SUBSCRIBE:
                $result = (new $className())->initiatePay($orderId);
                break;
        }

        return $result;
    }

    /**
     * @param int $userId
     * @return bool
     */
    private static function checkJetonLimit(int $userId): bool
    {
        $platform = PlatformService::getFields();

        $orders = OrderTable::getList(['filter' => [
            'USER_ID' => $userId,
            '!PRODUCT_ID' => 1,
            '>=DATE_CREATE' => (new DateTime())->format('d.m.Y 00:00:00'),
            '<=DATE_CREATE' => (new DateTime())->format('d.m.Y 23:59:59'),
        ]])->fetchAll();

        return array_sum(array_column($orders, 'SUM')) >= $platform['SALE_JETONS_LIMIT'];
    }

    /**
     * @param int $userId
     * @param array $product
     */
    public static function buyJeton(int $userId, array $product): void
    {
        $user = UserTable::getList(["filter" => ["ID" => $userId], "select" => ['ID', "UF_BALANCE_JETON", "UF_DEPOSIT", 'UF_REFERRER', 'UF_REFERRAL_ACCRUED', 'UF_DEALER']])->fetch();
        $balanceJeton = $user["UF_BALANCE_JETON"] + $product['VALUE'];

        (new \CUser)->Update($user['ID'], ["UF_BALANCE_JETON" => $balanceJeton]);
        GameService::increaseFund(PlatformService::getCalendarGameId(), $user['ID'], $product['VALUE']);

        ReferralService::increase($user, $product);
        DealerService::increase($user, $product);

        NotificationService::send('buyJeton', [
            'title' =>'Приобретение жетонов',
            'message' => 'Ваш баланс пополнен!',
            'result' => ['balanceJeton' => (int) $balanceJeton, 'deposit' => (int) $user['UF_DEPOSIT']],
        ], [$user['ID']]);
    }

    /**
     * @param int $userId
     * @param array $product
     */
    public static function buySubscribe(int $userId, array $product): void
    {
        $subscribe = SubscribeTable::getList(['filter' => ['USER_ID' => $userId]])->fetch();
        $user = UserTable::getList(["filter" => ["ID" => $userId], "select" => ["UF_DEPOSIT"]])->fetch();

        $dateTo = null;
        $today = new Date();

        if ($subscribe['ID'] > 0) {
            $dateTo = $subscribe['DATE_TO'] < $today ? $today : $subscribe['DATE_TO'];
            $dateTo->add($product['VALUE'] . ' days');

            SubscribeTable::update($subscribe['ID'], ["DATE_TO" => $dateTo]);
        } else {
            $dateTo = $today->add($product['VALUE'] . ' days');

            SubscribeTable::add([
                "USER_ID" => $userId,
                "DATE_TO" => $dateTo,
            ]);
        }

        NotificationService::send('buySubscribe', [
            'title' =>'Активация подписки',
            'message' => 'Вы активировали подписку до ' . $dateTo->toString(),
            'result' => ['subscribeTo' => $dateTo->toString(), 'deposit' => (int) $user["UF_DEPOSIT"]],
        ], [$userId]);
    }

    /**
     * @param int $userId
     * @param array $order
     */
    public static function withdraw(int $userId, array $order): void
    {
        $user = UserTable::getList(["filter" => ["ID" => $userId], "select" => ["UF_DEPOSIT"]])->fetch();
        $deposit = $user["UF_DEPOSIT"] - $order['SUM'];

        (new \CUser)->Update($userId, ["UF_DEPOSIT" => $deposit]);

        NotificationService::send('withdraw', [
            'title' =>'Выплата',
            'message' => 'Средства в размере "' . Helper::toIntFormat($order['SUM']) . '" выведены!',
            'result' => ['deposit' => (int) $deposit],
        ], [$userId]);
    }
}