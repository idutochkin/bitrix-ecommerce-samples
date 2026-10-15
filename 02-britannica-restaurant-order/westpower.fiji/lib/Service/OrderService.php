<?
namespace Westpower\Fiji\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Currency\CurrencyManager;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Order;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\PaySystem\Manager;
use Westpower\Fiji\Repository\OrderRepository;
use Bitrix\Pull\Event as PullEvent;
use Bitrix\Sale\Internals\PersonTypeTable;
use Bitrix\Main\Event;
use Bitrix\Main\UserTable;
use Westpower\Fiji\Service\PagerService;
use Westpower\Fiji\Service\WaiterService;

Loader::includeModule('sale');
Loader::includeModule('pull');
Loader::includeModule('iblock');

class OrderService
{
    const PRODUCT_PROVIDER_CLASS = 'CCatalogProductProvider';
    const DEFAULT_PRODUCT_NAME = 'Undefined';

    /**
     * @param int $userId
     * @return Order|null
     */
    public static function getCurrentOrder(int $userId): ?Order
    {
        $orderId = (int) Order::getList(['select' => ['ID'], 'filter' => [
            'USER_ID' => $userId,
            '!STATUS_ID' => 'F',
            'CANCELED' => 'N',
        ], 'order' => ['ID' => 'ASC']])->fetch()['ID'];

        if ($orderId > 0) {
            $order = Order::load($orderId);

            $propertyCollection = $order->getPropertyCollection();
            $visitId = $propertyCollection->getItemByOrderPropertyCode('visitId')->getValue();
            $orderId = $propertyCollection->getItemByOrderPropertyCode('orderId')->getValue();

            $remoteOrder = OrderRepository::getById($visitId, $orderId);

            if ($remoteOrder)
                return $order;
            else
                self::canceled($order->getId());
        } else {
            $tableId = (int) str_replace('table_', '', $GLOBALS['USER']->GetByID($userId)->Fetch()['LOGIN']);

            if ($remoteOrder = OrderRepository::getOrderByTableId($tableId)) {
                if (!$remoteOrder['finished']) {
                    $arOrder = self::getOrderByVisitId($remoteOrder['visitId'], $remoteOrder['orderId'], $tableId, true);

                    return Order::load($arOrder['ID']);
                }
            }
            

        }

        return null;
    }

    /**
     * @param int $visitId
     * @param int $orderId
     * @param int $tableId
     * @param bool $autoCreate
     * @return array|string
     */
    public static function getOrderByVisitId(int $visitId, int $orderId, int $tableId = 0, bool $autoCreate = false): array
    {
        $arOrder = Order::getList([
            'filter' => [
                '=PROPERTY_1.CODE' => 'visitId',
                '=PROPERTY_1.VALUE' => $visitId,
                '=PROPERTY_2.CODE' => 'orderId',
                '=PROPERTY_2.VALUE' => $orderId
            ],
            'runtime' => [
                'PROPERTY_1' => ['data_type' => OrderPropsValueTable::class, 'reference' => ['=this.ID' => 'ref.ORDER_ID'], 'join_type' => 'LEFT'],
                'PROPERTY_2' => ['data_type' => OrderPropsValueTable::class, 'reference' => ['=this.ID' => 'ref.ORDER_ID'], 'join_type' => 'LEFT']
            ]
        ])->fetch();

        if (!$arOrder && $tableId > 0 && $autoCreate) {
            $userId = UserTable::getList(['select' => ['ID'], 'filter' => ['=LOGIN' => 'table_' . $tableId]])->fetch()['ID'];
            $siteId = 's1';

            $order = self::create($userId, $siteId, [], false, false);

            $order = Order::load($order['RESULT']);
            $propertyCollection = $order->getPropertyCollection();
            $propertyCollection->getItemByOrderPropertyCode('visitId')->setValue($visitId);
            $propertyCollection->getItemByOrderPropertyCode('orderId')->setValue($orderId);
            $order->save();

            if ($order->isSuccess())
                $arOrder = Order::getList(['filter' => ['=ID' => $order->getId()]]);
        }

        return $arOrder ?: [];
    }

    /**
     * @param int $productId
     * @param int $modifierId
     * @param int $quantity
     * @param int $userId
     * @return int[]
     */
    public static function add2Basket(int $productId, int $modifierId, int $quantity, int $userId = 0): array
    {
        $userId = $userId ?: $GLOBALS['USER']->GetID();
        $siteId = Context::getCurrent()->getSite();
        $currency = CurrencyManager::getBaseCurrency();
        $productItem = null;

        $basket = Basket::loadItemsForFUser(Fuser::getIdByUserId($userId), $siteId);

        foreach ($basket as $basketItem) {
            if ((int)$basketItem->getProductId() === $productId)
                $productItem = $basketItem;
        }

        if ($productItem) {
            if ($quantity === 0 || (int)($productItem->getQuantity() + $quantity) === 0)
                $productItem->delete();
            else
                $productItem->setField('QUANTITY', $productItem->getQuantity() + $quantity);
        } elseif ($quantity > 0) {
            $arProduct = \CIBlockElement::GetList([], ['=ID' => $productId], false, false, ['ID', 'XML_ID', 'NAME'])->Fetch();

            $productItem = $basket->createItem('catalog', $productId);
            $productItem->setFields([
                'NAME' => $arProduct['NAME'] ?: self::DEFAULT_PRODUCT_NAME,
                'QUANTITY' => $quantity,
                'CURRENCY' => $currency,
                'LID' => $siteId,
                'PRODUCT_PROVIDER_CLASS' => self::PRODUCT_PROVIDER_CLASS,
            ]);

            if ($modifierId) {
                $arModifier = \CIBlockElement::GetList([], ['=XML_ID' => $modifierId], false, false, ['ID', 'XML_ID', 'NAME', 'CATALOG_GROUP_1'])->Fetch();
                $modifierPrice = (int) $arModifier['CATALOG_PRICE_1'];

                $itemPropertyCollection = $productItem->getPropertyCollection();
                $property = $itemPropertyCollection->createItem();
                $property->setFields([
                    'NAME' => $arModifier['NAME'] ?: self::DEFAULT_PRODUCT_NAME,
                    'CODE' => 'MODIFIER_ID',
                    'VALUE' => $modifierId,
                ]);

                $productItem->setFields([
                    'CUSTOM_PRICE' => 'Y',
                    'PRICE' => $productItem->getPrice() + $modifierPrice,
                ]);
            }
        }

        $basket->save();

        PullEvent::add([$userId], ['module_id' => 'main', 'command' => 'orderChanged']);

        return [
            'STATUS' => true,
            'PRICE_TOTAL' => (int) $productItem->getFinalPrice(),
            'QUANTITY' => (int) $productItem->getQuantity(),
            'BASKET_PRICE' => (int) $basket->getPrice(),
            'BASKET_QUANTITY' => (int) count($basket->getQuantityList()),
        ];
    }

    /**
     * @param int $productId
     * @param int $modifierId
     * @param int $userId
     * @return int[]
     */
    public static function checkModifierInBasket(int $productId, int $modifierId, int $userId = 0): array
    {
        $userId = $userId ?: $GLOBALS['USER']->GetID();
        $siteId = Context::getCurrent()->getSite();
        $result = true;
        $message = '';
        $haveProduct = false;
        $haveCurrentModifier = false;
        $oldModifierId = 0;

        $basket = Basket::loadItemsForFUser(Fuser::getIdByUserId($userId), $siteId);

        foreach ($basket as $basketItem) {
            if ((int)$basketItem->getProductId() === $productId) {
                $haveProduct = true;

                foreach ($basketItem->getPropertyCollection() as $property) {
                    if ($property->getField('CODE') === 'MODIFIER_ID')
                        $oldModifierId = (int) $property->getField('VALUE');
                }

                if ($oldModifierId === $modifierId)
                    $haveCurrentModifier = true;
            }
        }

        $arProduct = \CIBlockElement::GetList([], ['=ID' => $productId], false, false, ['ID', 'ACTIVE'])->Fetch();
        $arModifier = \CIBlockElement::GetList([], ['=XML_ID' => $oldModifierId ?: $modifierId], false, false, ['ID', 'ACTIVE', 'XML_ID', 'NAME', 'CATALOG_GROUP_1'])->Fetch();
        $modifierPrice = (int) $arModifier['CATALOG_PRICE_1'];

        if ($arProduct['ACTIVE'] === 'N' || $arModifier['ACTIVE'] === 'N') {
            $result = false;
            $message = 'Данный товар более не доступен для заказа.';
        }
        if (!(!$haveProduct || ($haveProduct && $haveCurrentModifier))) {
            $result = false;
            $message = 'Данный товар уже выбран с другим модификатором.';
        }

        return [
            'STATUS' => $result,
            'MESSAGE' => $message,
            'MODIFIER' => [
                'ID' => $oldModifierId ?: $modifierId,
                'NAME' => $modifierPrice > 0 ? '(+' . \FormatCurrency($modifierPrice, $arModifier['CATALOG_CURRENCY_1']) . ' ' . $arModifier['NAME'] . ')' : '(' . $arModifier['NAME'] . ')',
                'PRODUCT_QUANTITY' => $basketItem ? $basketItem->getQuantity() : 1,
            ]
        ];
    }

    /**
     * @param int $userId
     * @return array
     */
    public static function toOrder(int $userId = 0): array
    {
        $userId = $userId ?: $GLOBALS['USER']->GetID();
        $siteId = Context::getCurrent()->getSite();

        $order = self::getCurrentOrder($userId);
        $basket = Basket::loadItemsForFUser(Fuser::getIdByUserId($userId), $siteId);

        $products = [];
        foreach ($basket as $basketItem) {
            $modifierId = 0;
            foreach ($basketItem->getPropertyCollection() as $property) {
                if ($property->getField('CODE') === 'MODIFIER_ID')
                    $modifierId = (int) $property->getField('VALUE');
            }

            $products[] = [
                'ID' => $basketItem->getProductId(),
                'XML_ID' => $basketItem->getField('PRODUCT_XML_ID'),
                'NAME' => $basketItem->getField('NAME'),
                'QUANTITY' => $basketItem->getQuantity(),
                'MODIFIER_ID' => $modifierId,
            ];
        }

        $result = !$order ? self::create($userId, $siteId, $products) : self::update($order, $products);

        if ($result['STATUS']) {
            foreach ($basket as $basketItem)
                $basketItem->delete();
            $basket->save();
        }

        return $result;
    }

    /**
     * @param int $userId
     * @param string $siteId
     * @param array $products
     * @param bool $updateToClient
     * @param bool $notify
     * @return array
     * @throws \Exception
     */
    public static function create(int $userId, string $siteId, array $products = [], bool $updateToClient = true, bool $notify = true): array
    {
        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            $personTypeId = (int) PersonTypeTable::getRow(['filter' => ['LID' => $siteId, '=ACTIVE' => 'Y']])['ID'];
            $paySystemId = (int) Manager::getList(['filter' => ['=ACTIVE' => 'Y', '=IS_CASH' => 'A']])->fetch()['ID'];
            $currency = CurrencyManager::getBaseCurrency();

            $order = Order::create($siteId, $userId, $currency);
            $order->setPersonTypeId($personTypeId);
            $basket = Basket::create($siteId);

            foreach ($products as $product) {
                $productItem = $basket->createItem('catalog', $product['ID']);
                $productItem->setFields([
                    'NAME' => $product['NAME'] ?: self::DEFAULT_PRODUCT_NAME,
                    'QUANTITY' => $product['QUANTITY'],
                    'CURRENCY' => $currency,
                    'LID' => $siteId,
                    'PRODUCT_PROVIDER_CLASS' => self::PRODUCT_PROVIDER_CLASS,
                ]);

                if ($product['MODIFIER_ID']) {
                    $arModifier = \CIBlockElement::GetList([], ['=XML_ID' => $product['MODIFIER_ID']], false, false, ['ID', 'XML_ID', 'NAME', 'CATALOG_GROUP_1'])->Fetch();
                    $modifierPrice = (int) $arModifier['CATALOG_PRICE_1'];

                    $itemPropertyCollection = $productItem->getPropertyCollection();
                    $property = $itemPropertyCollection->createItem();
                    $property->setFields([
                        'NAME' => $arModifier['NAME'] ?: self::DEFAULT_PRODUCT_NAME,
                        'CODE' => 'MODIFIER_ID',
                        'VALUE' => $product['MODIFIER_ID'],
                    ]);

                    $productItem->setFields([
                        'CUSTOM_PRICE' => 'Y',
                        'PRICE' => $productItem->getPrice() + $modifierPrice,
                    ]);
                }
            }

            $basket->save();
            $order->setBasket($basket);

            $paymentCollection = $order->getPaymentCollection();
            $payment = $paymentCollection->createItem(Manager::getObjectById($paySystemId));

            if ($updateToClient) {
                $tableId = (int)str_replace('table_', '', $GLOBALS['USER']->GetLogin());

                try {
                    $clientOrder = OrderRepository::create($tableId, WaiterService::getByTable($tableId));
                } catch (\Exception $e) {
                    $clientOrder = OrderRepository::create($tableId);
                }

                $visitId = $clientOrder['visitId'];
                $orderId = $clientOrder['orderId'];

                $propertyCollection = $order->getPropertyCollection();
                $propertyCollection->getItemByOrderPropertyCode('visitId')->setValue($visitId);
                $propertyCollection->getItemByOrderPropertyCode('orderId')->setValue($orderId);
            }

            $payment->setField('SUM', $order->getPrice());
            $order->doFinalAction(true);
            $result = $order->save();

            if ($result->isSuccess() && $updateToClient) {
                OrderRepository::save($visitId, $orderId, $products);
                PagerService::call($tableId, [], $orderId);
            }

            $connection->commitTransaction();
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            throw new \Exception($e->getMessage());
        }

        if ($notify)
            PullEvent::add([$userId], ['module_id' => 'main', 'command' => 'orderChanged']);

        return [
            'STATUS' => $result->isSuccess(),
            'ERROR' => $result->isSuccess() ? '' : $result->getError(),
            'RESULT' => $order->getId()
        ];
    }

    /**
     * @param Order $order
     * @param array $products
     * @param bool $updateToClient
     * @param bool $notify
     * @return array
     * @throws \Exception
     */
    public static function update(Order $order, array $products, bool $updateToClient = true, bool $notify = true): array
    {
        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            $userId = $order->getUserId();
            $siteId = $order->getSiteId();
            $currency = CurrencyManager::getBaseCurrency();

            $basket = $order->getBasket();

            foreach ($products as $product) {
                if (!$product['ID'])
                    continue;

                $productItem = null;

                foreach ($basket as $basketItem) {
                    if ((int) $product['ID'] === (int) $basketItem->getProductId()) {
                        $modifierId = 0;
                        foreach ($basketItem->getPropertyCollection() as $property) {
                            if ($property->getField('CODE') === 'MODIFIER_ID')
                                $modifierId = (int) $property->getField('VALUE');
                        }

                        if ((!$product['MODIFIER_ID'] && !$modifierId) || ((int) $product['MODIFIER_ID'] === $modifierId))
                            $productItem = $basketItem;
                    }
                }

                if ($productItem) {
                    $quantity = $productItem->getQuantity() + $product['QUANTITY'];

                    if ($quantity > 0) {
                        $productItem->setField('QUANTITY', $productItem->getQuantity() + $product['QUANTITY']);
                    } else
                        $productItem->delete();
                } else {
                    if ((int) $product['QUANTITY'] <= 0)
                        continue;

                    $productItem = $basket->createItem('catalog', $product['ID']);
                    $productItem->setFields([
                        'NAME' => $product['NAME'] ?: self::DEFAULT_PRODUCT_NAME,
                        'QUANTITY' => $product['QUANTITY'],
                        'CURRENCY' => $currency,
                        'LID' => $siteId,
                        'PRODUCT_PROVIDER_CLASS' => self::PRODUCT_PROVIDER_CLASS,
                    ]);

                    if ($product['MODIFIER_ID']) {
                        $arModifier = \CIBlockElement::GetList([], ['=XML_ID' => $product['MODIFIER_ID']], false, false, ['ID', 'XML_ID', 'NAME', 'CATALOG_GROUP_1'])->Fetch();
                        $modifierPrice = (int) $arModifier['CATALOG_PRICE_1'];

                        $itemPropertyCollection = $productItem->getPropertyCollection();
                        $property = $itemPropertyCollection->createItem();
                        $property->setFields([
                            'NAME' => $arModifier['NAME'] ?: self::DEFAULT_PRODUCT_NAME,
                            'CODE' => 'MODIFIER_ID',
                            'VALUE' => $product['MODIFIER_ID'],
                        ]);

                        $productItem->setFields([
                            'CUSTOM_PRICE' => 'Y',
                            'PRICE' => $productItem->getPrice() + $modifierPrice,
                        ]);
                    }
                }

                if ($product['PRICE']) {
                    $productItem->setFields([
                        'CUSTOM_PRICE' => 'Y',
                        'PRICE' => $product['PRICE'],
                    ]);
                }
            }

            /**
             * Без строки $basket->getFUserId() сохранение корзины методом $basket->save() прерывается ошибкой
             * ошибка возникает только при обращении RKeeper к данному методу
             * при ручном вызове данного метода с теми же данными RKeeper все проходит успешно
             */
            $basket->getFUserId();
            $basket->save();

            $paymentCollection = $order->getPaymentCollection();
            $payment = $paymentCollection[0];
            if (!$payment) {
                $paySystemId = (int) Manager::getList(['filter' => ['=ACTIVE' => 'Y', '=IS_CASH' => 'A']])->fetch()['ID'];
                $payment = $paymentCollection->createItem(Manager::getObjectById($paySystemId));
            }

            $payment->setField('SUM', $order->getPrice());
            $order->doFinalAction(true);

            $result = $order->save();

            $propertyCollection = $order->getPropertyCollection();
            $visitId = $propertyCollection->getItemByOrderPropertyCode('visitId')->getValue();
            $orderId = $propertyCollection->getItemByOrderPropertyCode('orderId')->getValue();

            if ($result->isSuccess() && $updateToClient) {
                OrderRepository::save($visitId, $orderId, $products);

                $tableId = (int)str_replace('table_', '', $GLOBALS['USER']->GetLogin());
                PagerService::call($tableId, [], $orderId);
            }

            $connection->commitTransaction();
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            throw new \Exception($e->getMessage());
        }

        if ($notify)
            PullEvent::add([$userId], ['module_id' => 'main', 'command' => 'orderChanged']);

        return [
            'STATUS' => $result->isSuccess(),
            'ERROR' => $result->isSuccess() ? '' : $result->getError(),
            'RESULT' => $order->getId()
        ];
    }

    /**
     * @param int $userId
     * @return array
     */
    public static function initPay(int $userId = 0): array
    {
        $userId = $userId ?: $GLOBALS['USER']->GetID();
        $order = self::getCurrentOrder($userId);

        if ($order) {
            $propertyCollection = $order->getPropertyCollection();
            $paymentCollection = $order->getPaymentCollection();
            $payment = $paymentCollection[0];
            $paySystemService = Manager::getObjectById($payment->getPaymentSystemId());
            $arPaySystemAction = $paySystemService->getFieldsValues();

            $isPaid = OrderRepository::checkPaid(
                $propertyCollection->getItemByOrderPropertyCode('visitId')->getValue(),
                $propertyCollection->getItemByOrderPropertyCode('orderId')->getValue()
            );

            if ($isPaid) {
                $payment->setPaid('Y');
                $order->save();

                return [
                    'STATUS' => false,
                    'ERROR' => 'Заказ уже оплачен.',
                    'RESULT' => '',
                ];
            }

            if ($paySystemService->getField('NEW_WINDOW') === 'N') {
                $initResult = $paySystemService->initiatePay($payment, null, PaySystem\BaseServiceHandler::STRING);

                if ($initResult->isSuccess())
                    $arPaySystemAction['BUFFERED_OUTPUT'] = $initResult->getTemplate();
                else
                    $arPaySystemAction['ERROR'] = $initResult->getErrorMessages();
            }

            PullEvent::add([$userId], ['module_id' => 'main', 'command' => 'orderChanged']);

            return [
                'STATUS' => empty($arPaySystemAction['ERROR']),
                'ERROR' => $arPaySystemAction['ERROR'],
                'RESULT' => $arPaySystemAction['BUFFERED_OUTPUT'],
            ];
        }

        return [
            'STATUS' => false,
            'ERROR' => 'Заказ не найден.',
            'RESULT' => '',
        ];
    }

    /**
     * @param int $orderId
     * @param int $sum
     */
    public static function payment(int $orderId, int $sum): void
    {
        $order = Order::load($orderId);
        $paymentCollection = $order->getPaymentCollection();
        $oldPayment = null;

        foreach ($paymentCollection as $payment) {
            if ($payment->isPaid())
                continue;
            $oldPayment = $payment;
        }

        if ($oldPayment) {
            $newSum = $oldPayment->getSum() - $sum;
            $oldPayment->setField('SUM', $sum);
            $oldPayment->setPaid('Y');

            if ($newSum > 0) {
                $newPayment = $paymentCollection->createItem(Manager::getObjectById($oldPayment->getPaymentSystemId()));
                $newPayment->setField('SUM', $newSum);
            }

            $order->save();

            PullEvent::add([$order->getUserId()], ['module_id' => 'main', 'command' => 'orderChanged']);
        }
    }

    /**
     * @param int $orderId
     */
    public static function finished(int $orderId): void
    {
        $order = Order::load($orderId);
        $order->setField('STATUS_ID', 'F');
        $order->save();

        Application::getConnection()->queryExecute("DELETE FROM b_sale_basket
			WHERE b_sale_basket.ORDER_ID IS NULL
			AND b_sale_basket.FUSER_ID 
			IN (SELECT ID FROM b_sale_fuser WHERE b_sale_fuser.USER_ID = " . $order->getUserId() . ")");

        PullEvent::add([$order->getUserId()], ['module_id' => 'main', 'command' => 'reload']);
    }

    /**
     * @param int $orderId
     */
    public static function canceled(int $orderId): void
    {
        $order = Order::load($orderId);
        $order->setField('STATUS_ID', 'F');
        $order->setField('CANCELED', 'Y');
        $order->save();
    }

    /**
     * @param Event $event
     */
    public static function eventPaid(Event $event): void
    {
        $order = $event->getParameter('ENTITY');

        if ($order->isPaid()) {
            self::finished($order->getId());

            $propertyCollection = $order->getPropertyCollection();
            OrderRepository::payOrder(
                $propertyCollection->getItemByOrderPropertyCode('visitId')->getValue(),
                $propertyCollection->getItemByOrderPropertyCode('orderId')->getValue(),
                $order->getPrice()
            );
        }
    }

    /**
     * @param Order $order
     * @return Order
     */
    public static function syncClient(Order $order): Order
    {
        $propertyCollection = $order->getPropertyCollection();
        $visitId = $propertyCollection->getItemByOrderPropertyCode('visitId')->getValue();
        $orderId = $propertyCollection->getItemByOrderPropertyCode('orderId')->getValue();
        $basket = $order->getBasket();

        $remoteOrder = OrderRepository::getById($visitId, $orderId, true);

        $products = [];
        $dishes = $remoteOrder['OBJECT_XML']->getElementsByTagName('Dish');

        foreach ($dishes as $dish) {
            $product = [];
            $uni = (int) $dish->getAttribute('uni');

            $product['UNI'] = $uni;
            $product['XML_ID'] = $dish->getAttribute('id');
            $product['QUANTITY'] = (int) ($dish->getAttribute('quantity') / 1000);
            $product['PRICE'] = (int) ($dish->getAttribute('price') / 100);
            $product['SYNC'] = true;

            $modifier = $dish->getElementsByTagName('Modi');
            if ($modifier->length > 0) {
                $modifier = $modifier->item(0);

                $product['MODIFIER_ID'] = $modifier->getAttribute('id');
                //$product['MODIFIER_PRICE'] = $modifier->getAttribute('price') / 100; // Нет такого поля
            }

            $key = $product['XML_ID'] . '_' . $product['MODIFIER_ID'];

            $product['QUANTITY'] += $products[$key] ? $products[$key]['QUANTITY'] : 0;
            $products[$key] = $product;
        }

        if ($products) {
            foreach ($products as &$product) {
                $arProduct = MenuService::getProductByXmlId($product['XML_ID'], $product['PRICE']);

                $product['ID'] = $arProduct['ID'];
                $product['NAME'] = $arProduct['NAME'];

                $productItem = null;

                foreach ($basket as $basketItem) {
                    if ((int) $product['ID'] === (int) $basketItem->getProductId()) {
                        $modifierId = 0;
                        foreach ($basketItem->getPropertyCollection() as $property) {
                            if ($property->getField('CODE') === 'MODIFIER_ID')
                                $modifierId = (int) $property->getField('VALUE');
                        }

                        if ((!$product['MODIFIER_ID'] && !$modifierId) || ((int) $product['MODIFIER_ID'] === $modifierId))
                            $productItem = $basketItem;
                    }
                }

                if ($productItem) {
                    if (
                        (int) $productItem->getQuantity() === $product['QUANTITY']
                        && (int) $productItem->getPrice() === $product['PRICE']
                    )
                        $product = [];
                    else
                        $product['QUANTITY'] -= $productItem->getQuantity();
                }
            }
        }

        if (array_filter($products)) {
            self::update($order, $products, false, false);
            $order = Order::load($order->getId());
        }

        return $order;
    }
}