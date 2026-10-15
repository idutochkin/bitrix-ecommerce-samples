<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

    use Bitrix\Sale;
    use Bitrix\Sale\Order;
    use Bitrix\Sale\Basket;
    use Bitrix\Sale\BasketItemBase;
    use Bitrix\Sale\DiscountCouponsManager;
    use Bitrix\Sale\PaySystem;
    use Bitrix\Sale\Delivery;
    use Bitrix\Sale\Location\LocationTable;
    use Bitrix\Sale\Fuser;

    class CCacharelOrder extends CBitrixComponent
    {
        // физ. лицо
        private const PERSON_TYPE_ID = 3;

        // для первичного расчета Shiptor
        private const DEFAULT_PAY_SYSTEM_ID = 6;
        private const DEFAULT_DELIVERY_ID = 9;

        private const DELIVERY_STORE_PICKUP = 2;

        private const DELIVERY_OZON_PICKUP = 14;
        private const DELIVERY_OZON_POSTAMAT = 15;

        // переключатель на витрине
        private const FRONT_PICKUP = 1;
        private const FRONT_COURIER = 2;

        private const PROP_LOCATION = 24;
        private const PROP_PVZ_ID = 53;

        // не подставляем из прошлого заказа
        private const PROPS_NOT_PREFILLED = [48, 53, 54, 55, 56];

        // сбрасываем после оформления
        private const PROPS_RESET_AFTER_ORDER = [22, 53];

        /** @var Order|null */
        protected $order;
        protected $fUserId;
        protected $arUserResult = [];
        protected $arDeliveryServiceAll = [];
        protected $arPaySystemServiceAll = [];
        protected $deliveryPrices = [];
        protected $deliveryAdditionalFields = [];
        private $basketProductIds;

        public function prepareResult()
        {
            $this->arResult['TOTAL'] = [];
            $this->arResult['SESS_DELIVERY'] = $this->getDelivery();

            $this->genOrder();
        }

        protected function getFuserId()
        {
            if ($this->fUserId === null) {
                $this->fUserId = Fuser::getId();
            }

            return $this->fUserId;
        }

        public function prepareOrder()
        {
            global $USER;

            return $this->createOrder($USER->GetID() ?: CSaleUser::GetAnonymousUserID());
        }

        private function createOrder($userId)
        {
            DiscountCouponsManager::init(DiscountCouponsManager::MODE_CLIENT, ['userId' => $userId]);

            $registry = Sale\Registry::getInstance(Sale\Registry::REGISTRY_TYPE_ORDER);
            /** @var Order $orderClassName */
            $orderClassName = $registry->getOrderClassName();

            $order = $orderClassName::create($this->getSiteId(), $userId);
            $order->isStartField();
            $order->setField('STATUS_ID', Sale\OrderStatus::getInitialStatus());

            $this->initPersonType($order);
            $this->initProperties($order);
            $this->initBasket($order);

            $shipment = $this->initShipment($order);
            $order->doFinalAction(true);

            $this->initDelivery($shipment);
            $this->initPayment($order);

            return $order;
        }

        private function initBasket($order)
        {
            $basket = $this->loadBasket();
            $availableBasket = $basket->getOrderableItems();
            $order->appendBasket($availableBasket);
        }

        private function loadBasket()
        {
            $registry = Sale\Registry::getInstance(Sale\Registry::REGISTRY_TYPE_ORDER);

            /** @var Sale\Basket $basketClassName */
            $basketClassName = $registry->getBasketClassName();

            return $basketClassName::loadItemsForFUser(Sale\Fuser::getId(), $this->getSiteId());
        }

        private function initProperties($order)
        {
            $this->setOrderProperties($order);
        }

        public function getLocationIdByCode($code)
        {
            $result = null;

            if ($item = \Bitrix\Sale\Location\LocationTable::getByCode($code)->fetch()) {
                $result = $item['ID'];
            }

            return $result;
        }

        public function getLocationCodeById($id)
        {
            $result = null;

            if ($item = \Bitrix\Sale\Location\LocationTable::getById($id)->fetch()) {
                $result = $item['CODE'];
            }

            return $result;
        }

        public function getLocationText($code)
        {
            $location = \Bitrix\Sale\Location\LocationTable::getList([
                'select' => ['ID', 'NAME_RU' => 'NAME.NAME'],
                'filter' => ['=CODE' => $code, '=NAME.LANGUAGE_ID' => 'ru'],
            ])->fetch();

            return $location ? (string) $location['NAME_RU'] : '';
        }

        protected function setOrderProperties(Order $order)
        {
            global $USER;

            if (!empty($USER->GetID())) {
                $Query = \Bitrix\Sale\Order::getList([
                    'order' => ['ID' => 'DESC'],
                    'filter' => ['USER_ID' => $USER->GetID()],
                    'select' => ['ID']
                ]);

                if ($Answer = $Query->Fetch()) {
                    $tmpOrder = \Bitrix\Sale\Order::load($Answer['ID']);
                    $tmpProps = $tmpOrder->getPropertyCollection();

                    foreach ($tmpProps as $tmpProp) {
                        $id = $tmpProp->getField('ORDER_PROPS_ID');
                        $val = $tmpProp->getValue();

                        if (!empty($val)) {
                            if (!in_array($id, self::PROPS_NOT_PREFILLED))
                                if (empty($_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . $id])) {
                                    $_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . $id] = $val;
                                }
                        }
                    }
                }

                unset($result, $VAnswer, $VQuery, $Answer, $Query);
                $Query = \CSaleOrderUserProps::GetList([], ["USER_ID" => $USER->GetID()]);
                $result = [];

                while ($Answer = $Query->Fetch()) {
                    $VQuery = \CSaleOrderUserPropsValue::GetList([], ["USER_PROPS_ID" => $Answer["ID"]], false, false);
                    $result[$Answer['ID']] = [];

                    while ($VAnswer = $VQuery->Fetch()) {
                        if ($VAnswer['PROP_IS_LOCATION'] == 'Y') {
                            $VAnswer['VALUE_TEXT'] = $this->getLocationText($VAnswer['VALUE']);
                        }

                        $result[$Answer['ID']][$VAnswer['PROP_ID']] = $VAnswer;
                    }
                }

                $this->arResult['PROFILES'] = $result;

                if (!empty($this->getProfile())) {
                    $this->arResult['SELECTED_PROFILE'] = $this->getProfile();
                    $currentProfile = $this->arResult['PROFILES'][$this->arResult['SELECTED_PROFILE']];

                    foreach ($currentProfile as $id => $vals) {
                        if ($vals['PROP_TYPE'] != "LOCATION") {
                            $_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . $vals['ORDER_PROPS_ID']] = $vals['VALUE'];
                        } else {
                            $_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . $vals['ORDER_PROPS_ID']] = $this->getLocationCodeById($vals['VALUE']);
                        }
                    }

                    $this->saveProfile("");
                }

                unset($result, $VAnswer, $VQuery, $Answer, $Query);
            }
            
            $valuesFromPost = $this->arParams['VAL_FROM_POST'] ?? [];

            foreach ($_SESSION['SOA_PROP_VALS_B'] as $name => $val) {
                if (!empty($valuesFromPost[$name])) {
                    $val = $valuesFromPost[$name];
                }

                $id = (int) str_replace('ORDER_PROP_', '', $name);
                $property = $order->getPropertyCollection()->getItemByOrderPropertyId($id);

                if ($property) {
                    $property->setField('VALUE', $val);
                }
            }
        }

        private function initPersonType($order)
        {
            $order->setPersonTypeId(self::PERSON_TYPE_ID);
        }

        public function genOrder()
        {
            $this->order = $this->prepareOrder();
            $propertyCollection = $this->order->getPropertyCollection();
            $personTypeId = self::PERSON_TYPE_ID;
            $this->arResult['PROPERTIES'] = [];
            $this->arResult['ALL_PROPERTIES'] = [];

            foreach ($propertyCollection as $prop) {
                $props = $prop->getProperty();

                if ($props['PERSON_TYPE_ID'] == $personTypeId) {
                    if ($props['UTIL'] != 'Y') {
                        $this->arResult['PROPERTIES'][$props['ID']] = $props;
                        $this->arResult['PROPERTIES'][$props['ID']]['VALUE'] = $prop->getField('VALUE');
                    }

                    $this->arResult['ALL_PROPERTIES'][$props['ID']] = $props;
                    $this->arResult['ALL_PROPERTIES'][$props['ID']]['VALUE'] = $prop->getField('VALUE');
                }
            }

            $this->arResult['SESS'] = $_SESSION['SOA_PROP_VALS_B'];
            DiscountCouponsManager::init(DiscountCouponsManager::MODE_EXTERNAL, ['userId' => $this->order->getUserId()]);
            $discounts = $this->order->getDiscount();
            $showPrices = $discounts->getShowPrices();

            foreach ($showPrices['BASKET'] ?? [] as $basketCode => $data) {
                $basketItem = $this->order->getBasket()->getItemByBasketCode($basketCode);

                if ($basketItem instanceof BasketItemBase) {
                    $basketItem->setFieldNoDemand('BASE_PRICE', $data['SHOW_BASE_PRICE']);
                    $basketItem->setFieldNoDemand('PRICE', $data['SHOW_PRICE']);
                    $basketItem->setFieldNoDemand('DISCOUNT_PRICE', $data['SHOW_DISCOUNT']);
                }
            }

            $this->arResult['ITEMS'] = [];
            $basketItems = $this->order->getBasket()->getOrderableItems();

            foreach ($basketItems as $item) {
                $props = $item->getPropertyCollection();
                $this->arResult['ITEMS'][$item->getId()] = [
                    'ID' => $item->getId(),
                    'NAME' => $item->getField('NAME'),
                    'QUANTITY' => $item->getQuantity(),
                    'PRICE' => $item->getPrice(),
                    'BASE_PRICE' => $item->getField('BASE_PRICE'),
                    'SUM' => $item->getFinalPrice(),
                ];

                foreach ($props as $p) {
                    $this->arResult['ITEMS'][$item->getId()]['PROPS'][] = [
                        'CODE' => $p->getField('CODE'),
                        'NAME' => $p->getField('NAME'),
                        'VALUE' => $p->getField('VALUE'),
                        'SORT' => $p->getField('SORT')
                    ];
                }
            }

            $this->arResult['location'] = $this->getLocationResult();
            $this->arResult['DELIVERY_LIST'] = [
                [
                    'ID' => self::FRONT_COURIER,
                    'NAME' => 'Доставка'
                ],
                [
                    'ID' => self::FRONT_PICKUP,
                    'NAME' => 'Пункт самовывоза'
                ]
            ];

            $this->arUserResult = [
                'PAY_SYSTEM_ID' => self::DEFAULT_PAY_SYSTEM_ID,
                'DELIVERY_ID' => self::DEFAULT_DELIVERY_ID,
                'PERSON_TYPE_ID' => $personTypeId,
                'ORDER_PROP' => $this->arResult['PROPERTIES']
            ];
            CShiptorDeliveryHandler::showCreateAnswer($this->order, $this->arUserResult, $this->request,
                $this->arParams, $this->arResult, $this->arDeliveryServiceAll, $this->arPaySystemServiceAll);
            $this->arUserResult = [
                'PAY_SYSTEM_ID' => $this->getPayment(),
                'DELIVERY_ID' => $this->getDelivery(),
                'PERSON_TYPE_ID' => $personTypeId,
                'ORDER_PROP' => $this->arResult['PROPERTIES'],
            ];
            $this->arUserResult['ORDER_PROP'] = [];

            foreach ($this->arResult['PROPERTIES'] as $p) {
                $this->arUserResult['ORDER_PROP'][$p['ID']] = $p['VALUE'];
            }

            $this->arUserResult['DELIVERY_LOCATION'] = $this->getLocationIdByCode($this->arUserResult['ORDER_PROP'][self::PROP_LOCATION]);

            $this->arResult['RAW_DELIVERY_LIST'] = $this->getDeliveryList($this->arDeliveryServiceAll);
            $this->arResult['RAW_DELIVERY_ID'] = $this->getDelivery();
            $this->arResult['DELIVERY_ID'] = $this->getDelivery() == self::DELIVERY_STORE_PICKUP
                ? self::FRONT_PICKUP
                : self::FRONT_COURIER;
            $totalDelivery = $this->arResult['RAW_DELIVERY_LIST'][$this->arResult['RAW_DELIVERY_ID']];
            $basketPrice = $this->order->getBasket()->getPrice();
            $basketBasePrice = $this->order->getBasket()->getBasePrice();

            $totalDelivery['PRICE'] = CCurrencyLang::CurrencyFormat($totalDelivery['PRICE'], 'RUB', true);

            $this->arResult['TOTAL'] = [
                'PRICE_FORMATTED' => CCurrencyLang::CurrencyFormat($basketPrice, 'RUB', true),
                'PRICE' => $basketPrice,
                'BASE_PRICE_FORMATTED' => CCurrencyLang::CurrencyFormat($basketBasePrice, 'RUB', true),
                'BASE_PRICE' => $basketBasePrice,
                'DISCOUNT_PRICE' => $basketBasePrice - $basketPrice,
                'DISCOUNT_PRICE_FORMATTED' => CCurrencyLang::CurrencyFormat($basketBasePrice - $basketPrice, 'RUB', true),
                'COUPON_INFO' => $this->getCouponInfo(),
                'DELIVERY' => $totalDelivery,
                'TOTAL' => [
                    'PRICE' => $this->order->getPrice(),
                    'PRICE_FORMATTED' => CCurrencyLang::CurrencyFormat($this->order->getPrice(), 'RUB', true)
                ]
            ];
            $this->arResult['DELIVERY_STREET_ID'] = $this->arParams['DELIVERY_STREET_ID'];
            $this->arResult['PAYMENTS'] = $this->arPaySystemServiceAll;
            $this->arResult['PAYMENT_ID'] = $this->getPayment();
        }

        function initPayment($order)
        {
            $innerPaySystemId = PaySystem\Manager::getInnerPaySystemId();
            $paymentCollection = $order->getPaymentCollection();
            $extPayment = $paymentCollection->createItem();
            $extPayment->setField('SUM', $order->getPrice());
            $extPaySystemList = PaySystem\Manager::getListWithRestrictions($extPayment);
            $this->arPaySystemServiceAll = $extPaySystemList;

            foreach ($this->arPaySystemServiceAll as &$pa) {
                if (!empty($pa['LOGOTIP'])) {
                    $pa['LOGOTIP'] = CFIle::GetPath($pa['LOGOTIP']);
                }
            }

            unset($pa);
            $paymentId = $this->getPayment();

            if ($paymentId == 0 || empty($extPaySystemList[$paymentId])) {
                $this->savePayment($extPaySystemList[current($extPaySystemList)['ID']]['ID']);
                $paymentId = $this->getPayment();
            }

            $extPayment->setFields([
                'PAY_SYSTEM_ID' => $extPaySystemList[$paymentId]['ID'],
                'PAY_SYSTEM_NAME' => $extPaySystemList[$paymentId]['NAME']
            ]);

            return $extPayment;
        }

        function getPropValuesFromSession()
        {
            foreach ($_SESSION['SOA_PROP_VALS_B'] as $name => $val) {
                $id = str_replace('ORDER_PROP_', '', $name);
                $this->order->getPropertyCollection()->getItemByOrderPropertyId($id)->setField('VALUE', $val);
            }
        }

        function getPropValFromSession()
        {
            foreach ($_SESSION['SOA_PROP_VALS_B'] as $name => $val) {
                $id = str_replace('ORDER_PROP_', '', $name);
                $this->arResult['PROPERTIES'][$id]['VALUE'] = $val;
            }
        }

        public function getDeliveryList($deliveryList)
        {
            $result = [];

            foreach ($deliveryList as $deliveryId => $deliveryObj) {
                $result[$deliveryObj->getId()] = [
                    'NAME' => $deliveryObj->getName(),
                    'ID' => $deliveryObj->getId(),
                    'SORT' => $deliveryObj->getSort(),
                    'PRICE' => $this->deliveryPrices[$deliveryId],
                    'DESCRIPTION' => $this->deliveryAdditionalFields[$deliveryId]->getDescription(),
                    'PERIOD' => $this->deliveryAdditionalFields[$deliveryId]->getPeriodDescription()
                ];
                $pvz = $_SESSION['SOA_IPOL_PVZ'] ?? [];
                $pvzTypeByDelivery = [self::DELIVERY_OZON_PICKUP => 'pickup', self::DELIVERY_OZON_POSTAMAT => 'postamat'];
                $pvzType = $pvzTypeByDelivery[$deliveryObj->getId()] ?? null;

                if ($pvzType && ($pvz['type'] ?? '') === $pvzType) {
                    $result[$deliveryObj->getId()]['DESCRIPTION'] .=
                        '<div style="font-size:15px">' . htmlspecialcharsbx($pvz['address']) . '</div>';
                }
            }

            return $result;
        }

        public function getDeliveryPrice($deliveryId)
        {
            return $this->deliveryPrices[$deliveryId];
        }

        public function getDeliveryPeriod($deliveryId)
        {
            return $this->deliveryAdditionalFields[$deliveryId]->getPeriodDescription();
        }

        public function initDelivery($shipment)
        {
            $deliveryId = $this->getDelivery();

            if ($this->getFrontDelivery() == self::FRONT_PICKUP) {
                $deliveryId = self::DELIVERY_STORE_PICKUP;
            } elseif ($deliveryId == self::DELIVERY_STORE_PICKUP) {
                $deliveryId = 0;
            }

            $this->initDeliveryServices($shipment);

            $shipmentCollection = $shipment->getCollection();
            $order = $shipmentCollection->getOrder();

            if (!empty($this->arDeliveryServiceAll)) {
                if (isset($this->arDeliveryServiceAll[$deliveryId])) {
                    $deliveryObj = $this->arDeliveryServiceAll[$deliveryId];
                } else {
                    $deliveryObj = reset($this->arDeliveryServiceAll);
                    $deliveryId = $deliveryObj->getId();
                }

                if ($deliveryObj->isProfile()) {
                    $name = $deliveryObj->getNameWithParent();
                } else {
                    $name = $deliveryObj->getName();
                }

                $order->isStartField();
                $shipment->setFields([
                    'DELIVERY_ID' => $deliveryId,
                    'DELIVERY_NAME' => $name,
                    'CURRENCY' => $order->getCurrency(),
                ]);
                $this->saveDelivery($deliveryId);
                $deliveryStoreList = Delivery\ExtraServices\Manager::getStoresList($deliveryId);

                if (!empty($deliveryStoreList)) {
                    if ($this->getStore() <= 0 || !in_array($this->getStore(), $deliveryStoreList)) {
                        $this->saveStore(current($deliveryStoreList));
                    }

                    $this->arResult['STORE_ID'] = $this->getStore();
                    $this->arResult['STORE_LIST_ID'] = $deliveryStoreList;
                    \Bitrix\Main\Loader::includeModule('catalog');
                    $filter = [
                        'ID' => $deliveryStoreList,
                        'ACTIVE' => 'Y',
                    ];
                    $cityName = $this->getLocationText($_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . self::PROP_LOCATION]);

                    if (!empty($cityName)) {
                        $filter['UF_CODE'] = $cityName;
                    }

                    $res = CCatalogStore::GetList([], $filter, false, false, [
                        "ID",
                        "ACTIVE",
                        "TITLE",
                        "PHONE",
                        "SCHEDULE",
                        "ADDRESS",
                        "DESCRIPTION",
                        "GPS_N",
                        "GPS_S",
                        "IMAGE_ID",
                        "DATE_CREATE",
                        "DATE_MODIFY",
                        "USER_ID",
                        "XML_ID",
                        "SORT",
                        "EMAIL",
                        "ISSUING_CENTER",
                        "SHIPPING_CENTER",
                        "SITE_ID",
                        "CODE",
                        'UF_CITY'
                    ]);
                    $this->arResult['STORE_LIST'] = [];

                    while ($store = $res->Fetch()) {
                        if ($store['UF_CITY'] == $cityName && $this->checkStoreToAmount((int) $store['ID'])) {
                            $this->arResult['STORE_LIST'][$store['ID']] = $store;
                        }
                    }

                    $shipment->setStoreId($this->getStore());
                }

                $shipmentCollection->calculateDelivery();
                $order->doFinalAction(true);
            } else {
                $service = Delivery\Services\Manager::getById(
                    Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId()
                );
                $shipment->setFields([
                    'DELIVERY_ID' => $service['ID'],
                    'DELIVERY_NAME' => $service['NAME'],
                    'CURRENCY' => $order->getCurrency(),
                ]);
            }
        }

        /**
         * @param int $storeId
         * @return bool
         */
        public function checkStoreToAmount(int $storeId): bool
        {
            $productIds = $this->getBasketProductIds();

            if (!$productIds) {
                return false;
            }

            $inStock = \Bitrix\Catalog\StoreProductTable::getCount([
                '=STORE_ID' => $storeId,
                '@PRODUCT_ID' => $productIds,
                '>AMOUNT' => 0,
            ]);

            return $inStock === count($productIds);
        }

        /**
         * @return array
         */
        private function getBasketProductIds(): array
        {
            if ($this->basketProductIds === null) {
                $this->basketProductIds = [];

                foreach ($this->loadBasket()->getOrderableItems() as $basketItem) {
                    $this->basketProductIds[] = (int) $basketItem->getProductId();
                }
            }

            return $this->basketProductIds;
        }

        public function initDeliveryServices($shipment)
        {
            $services = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
            $result = [];
            $prevDeliveryId = $shipment->getDeliveryId();

            foreach ($services as $deliveryId => $deliveryObj) {
                $shipment->setField('DELIVERY_ID', $deliveryId);
                $calcResult = $deliveryObj->calculate($shipment);
                $this->deliveryAdditionalFields[$deliveryObj->getId()] = $calcResult;
                $this->deliveryPrices[$deliveryObj->getId()] = $calcResult->getDeliveryPrice();
                $result[$deliveryId] = $deliveryObj;
            }

            $shipment->setField('DELIVERY_ID', $prevDeliveryId);
            $this->arDeliveryServiceAll = $result;
        }

        /**
         * Initialization of shipment object. Filling with basket items.
         *
         * @param Order $order
         * @return Shipment
         * @throws Main\ArgumentTypeException
         * @throws Main\NotSupportedException
         */
        public function initShipment(Order $order)
        {
            $shipmentCollection = $order->getShipmentCollection();
            $shipment = $shipmentCollection->createItem();
            $shipmentItemCollection = $shipment->getShipmentItemCollection();
            $shipment->setField('CURRENCY', $order->getCurrency());

            /** @var Sale\BasketItem $item */
            foreach ($order->getBasket() as $item) {
                /** @var Sale\ShipmentItem $shipmentItem */
                $shipmentItem = $shipmentItemCollection->createItem($item);
                $shipmentItem->setQuantity($item->getQuantity());
            }

            return $shipment;
        }

        public function getLocationResult()
        {
            $locations = [];
            $propertyCollection = $this->order->getPropertyCollection();
            $properties = $propertyCollection->getArray();

            foreach ($properties['properties'] as $property) {
                if ($property['UTIL'] == 'Y')
                    continue;

                if ($property['TYPE'] == 'LOCATION') {
                    $locationTemplateP = 'search';
                    $locationTemplate = $locationTemplateP;

                    $locations[$property['ID']]['template'] = $locationTemplate;
                    $locations[$property['ID']]['output'] = $this->getLocationHtml($property, $locationTemplate);
                    $locations[$property['ID']]['lastValue'] = reset($property['VALUE']);

                    if ($property['IS_LOCATION'] === 'Y') {
                        $locations[$property['ID']]['coordinates'] = LocationTable::getRow([
                            'select' => ['LONGITUDE', 'LATITUDE'],
                            'filter' => ['=CODE' => reset($property['VALUE'])],
                        ]);
                    }
                }
            }

            return $locations;
        }

        protected function getLocationHtml($property, $locationTemplate)
        {
            global $APPLICATION;

            $locationOutput = [];
            $showDefault = true;

            $propertyId = (int)$property['ID'];
            $isMultiple = $property['MULTIPLE'] == 'Y' && $property['IS_LOCATION'] != 'Y';

            $locationAltPropDisplayManual = $this->request->get('LOCATION_ALT_PROP_DISPLAY_MANUAL');
            $altPropManual = isset($locationAltPropDisplayManual[$propertyId]) && (bool)$locationAltPropDisplayManual[$propertyId];

            $location = $this->order->getPropertyCollection()->getItemByOrderPropertyId($propertyId);
            $actualValues = $location->getValue();

            if (!is_array($actualValues)) {
                $actualValues = [$actualValues];
            }

            if (!empty($actualValues) && is_array($actualValues)) {
                foreach ($actualValues as $key => $value) {
                    $parameters = [
                        'CODE' => $value,
                        'INPUT_NAME' => 'ORDER_PROP_' . $propertyId . ($isMultiple ? '[' . $key . ']' : ''),
                        'CACHE_TYPE' => 'A',
                        'CACHE_TIME' => '36000000',
                        'SEARCH_BY_PRIMARY' => 'N',
                        'SHOW_DEFAULT_LOCATIONS' => $showDefault ? 'Y' : 'N',
                        'PROVIDE_LINK_BY' => 'code',
                        'JS_CALLBACK' => 'submitFormProxy',
                        'JS_CONTROL_DEFERRED_INIT' => $propertyId . ($isMultiple ? '_' . $key : ''),
                        'JS_CONTROL_GLOBAL_ID' => $propertyId . ($isMultiple ? '_' . $key : ''),
                        'DISABLE_KEYBOARD_INPUT' => 'Y',
                        'PRECACHE_LAST_LEVEL' => 'N',
                        'PRESELECT_TREE_TRUNK' => 'Y',
                        'SUPPRESS_ERRORS' => 'Y',
                        'FILTER_BY_SITE' => 'Y',
                        'FILTER_SITE_ID' => $this->getSiteId(),
                    ];

                    ob_start();

                    if ($locationTemplate == 'steps') {
                        echo '<input type="hidden" id="LOCATION_ALT_PROP_DISPLAY_MANUAL[' . $propertyId
                            . ']" name="LOCATION_ALT_PROP_DISPLAY_MANUAL[' . $propertyId . ']" value="'
                            . ($altPropManual ? '1' : '0') . '" />';
                    }

                    $APPLICATION->IncludeComponent(
                        'bitrix:sale.location.selector.' . $locationTemplate,
                        '',
                        $parameters,
                        null,
                        ['HIDE_ICONS' => 'Y']
                    );

                    $locationOutput[] = ob_get_contents();
                    ob_end_clean();

                    $showDefault = false;
                }
            }

            if ($isMultiple) {
                $parameters = [
                    'CODE' => '',
                    'INPUT_NAME' => 'ORDER_PROP_' . $propertyId . '[#key#]',
                    'CACHE_TYPE' => 'A',
                    'CACHE_TIME' => '36000000',
                    'SEARCH_BY_PRIMARY' => 'N',
                    'SHOW_DEFAULT_LOCATIONS' => 'N',
                    'PROVIDE_LINK_BY' => 'code',
                    'JS_CALLBACK' => 'submitFormProxy',
                    'JS_CONTROL_DEFERRED_INIT' => $propertyId . '_key__',
                    'JS_CONTROL_GLOBAL_ID' => $propertyId . '_key__',
                    'DISABLE_KEYBOARD_INPUT' => 'Y',
                    'PRECACHE_LAST_LEVEL' => 'N',
                    'PRESELECT_TREE_TRUNK' => 'Y',
                    'SUPPRESS_ERRORS' => 'Y',
                    'FILTER_BY_SITE' => 'Y',
                    'FILTER_SITE_ID' => $this->getSiteId(),
                ];

                ob_start();

                $APPLICATION->IncludeComponent(
                    'bitrix:sale.location.selector.' . $locationTemplate,
                    '',
                    $parameters,
                    null,
                    ['HIDE_ICONS' => 'Y']
                );

                $locationOutput['clean'] = ob_get_contents();
                ob_end_clean();
            }

            return $locationOutput;
        }

        public function getCouponInfo()
        {
            $result = [
                'COUPON' => '',
                'COUPON_LIST' => [],
            ];
            $coupons = DiscountCouponsManager::get(true, [], true, true);

            if (!empty($coupons)) {
                foreach ($coupons as &$coupon) {
                    if ($result['COUPON'] == '') {
                        $result['COUPON'] = $coupon['COUPON'];
                    }

                    if (in_array($coupon['STATUS'], [DiscountCouponsManager::STATUS_NOT_FOUND, DiscountCouponsManager::STATUS_FREEZE])) {
                        $coupon['JS_STATUS'] = 'BAD';
                    } elseif (in_array($coupon['STATUS'], [DiscountCouponsManager::STATUS_NOT_APPLYED, DiscountCouponsManager::STATUS_ENTERED])) {
                        $coupon['JS_STATUS'] = 'ENTERED';

                        if ($coupon['STATUS'] == DiscountCouponsManager::STATUS_NOT_APPLYED) {
                            $coupon['STATUS_TEXT'] = DiscountCouponsManager::getCheckCodeMessage(DiscountCouponsManager::COUPON_CHECK_OK);
                            $coupon['CHECK_CODE_TEXT'] = [$coupon['STATUS_TEXT']];
                        }
                    } else {
                        $coupon['JS_STATUS'] = 'APPLYED';
                    }

                    $coupon['JS_CHECK_CODE'] = '';

                    if (isset($coupon['CHECK_CODE_TEXT'])) {
                        $coupon['JS_CHECK_CODE'] = is_array($coupon['CHECK_CODE_TEXT'])
                            ? implode(', ', $coupon['CHECK_CODE_TEXT'])
                            : $coupon['CHECK_CODE_TEXT'];
                    }

                    $result['COUPON_LIST'][] = $coupon;
                }

                unset($coupon);
            }

            unset($coupons);

            return $result;
        }

        function savePropVal()
        {
            $name = $_POST['data']['name'];
            $val = $_POST['data']['value'];
            $this->arParams['VAL_FROM_POST'][$name] = $val;
            $_SESSION['SOA_PROP_VALS_B'][$name] = $val;
        }

        function saveDelivery($val)
        {
            $_SESSION['SOA_DELIVERY'] = $val;
        }

        function saveDeliveryByPost()
        {
            $this->saveDelivery($_POST['data']['DELIVERY']);
        }

        function getStore()
        {
            $storeId = (!empty($_SESSION['SOA_STORE']) ? $_SESSION['SOA_STORE'] : 0);

            return $storeId;
        }

        function saveStore($val)
        {
            $_SESSION['SOA_STORE'] = $val;
        }

        function saveStoreByPost()
        {
            $this->saveStore($_POST['data']['STORE']);
        }

        function getDelivery()
        {
            $deliveryId = $_SESSION['SOA_DELIVERY'];

            if (empty($deliveryId)) {
                $deliveryId = 0;
            }

            return $deliveryId;
        }

        function getFrontDelivery()
        {
            $id = $_SESSION['SOA_FRONT_DELIVERY'];

            if (empty($id)) {
                $id = self::FRONT_COURIER;
            }

            return $id;
        }

        function saveFrontDeliveryByPost()
        {
            $d = $_POST['data']['delivery'];
            $_SESSION['SOA_FRONT_DELIVERY'] = $d;
        }

        function savePaymentByPost()
        {
            $this->savePayment($_POST['data']['PAYMENT']);
        }

        function savePayment($val)
        {
            $_SESSION['SOA_PAYMENT'] = $val;
        }

        function getPayment()
        {
            $p = 0;

            if (!empty($_SESSION['SOA_PAYMENT'])) {
                $p = $_SESSION['SOA_PAYMENT'];
            }

            return $p;
        }

        function saveProfileByPost()
        {
            $this->saveProfile($_POST['data']['value']);
        }

        function saveProfile($id)
        {
            $_SESSION['SOA_PROFILE'] = $id;
        }

        function getProfile()
        {
            $result = null;

            if (!empty($_SESSION['SOA_PROFILE'])) {
                $result = $_SESSION['SOA_PROFILE'];
            }

            return $result;
        }

        function savePvzFromPost()
        {
            $this->savePvz($_POST['data']);
        }

        function savePvz($data)
        {
            $_SESSION['SOA_IPOL_PVZ'] = $data;
            $_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . self::PROP_PVZ_ID] = $data['id'];
            $this->arParams['VAL_FROM_POST']['ORDER_PROP_' . self::PROP_PVZ_ID] = $data['id'];
        }

        function getPVZInfo()
        {
            $result = [];

            if (!empty($_SESSION['SOA_IPOL_PVZ'])) {
                $result = $_SESSION['SOA_IPOL_PVZ'];
            }

            return $result;
        }

        function ajaxHandler()
        {
            if (!empty($_POST['sess'])) {
                if (!empty($_SESSION['SOA_SESS']) && hash_equals((string) $_SESSION['SOA_SESS'], (string) $_POST['sess'])) {
                    switch ($_POST['method']) {
                        case "savePropVal":
                            $this->savePropVal();
                            break;
                        case "saveDelivery":
                            $this->saveDeliveryByPost();
                            break;
                        case "chooseFrontDelivery":
                            $this->saveFrontDeliveryByPost();
                            break;
                        case "saveStore":
                            $this->saveStoreByPost();
                            break;
                        case "savePayment":
                            $this->savePaymentByPost();
                            break;
                        case "selectProfile":
                            $this->saveProfileByPost();
                            break;
                        case "savePropPvz":
                            $this->savePvzFromPost();
                            break;
                    }

                    global $APPLICATION;

                    if ($_POST['method'] != "saveOrderB") {
                        $this->prepareResult();
                        $this->arResult['IPOL'] = $this->getSettingsToIpol();
                    } else {
                        $this->prepareResult();

                        if ($this->order->getPrice() > 0) {
                            if (!empty($_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . self::PROP_PVZ_ID])) {
                                $_REQUEST['POINT_GUID'] = $_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . self::PROP_PVZ_ID];
                                $shipments = $this->order->loadShipmentCollection();

                                while ($shipment = $shipments->next()) {
                                    $deliveryObject = $shipment->getDelivery();

                                    if ($deliveryProfileCode = \Ipol\Ozon\Bitrix\Handler\Deliveries::defineDelivery($shipment->getDeliveryId())) {
                                        $calculator = $deliveryObject->getCalculator();

                                        if ($calculator instanceof \Ipol\Ozon\Bitrix\Controller\Calculator) {
                                            $DELIVERY_VARIANT_ID = $calculator->getSelectedVariantGuid();
                                        }
                                    }
                                }

                                if (!empty($DELIVERY_VARIANT_ID)) {
                                    $propertyCollection = $this->order->getPropertyCollection();
                                    $prop = $propertyCollection->getItemByOrderPropertyCode('IPOL_OZON_DELIVERY_VARIANT');
                                    $prop->setField('VALUE', $DELIVERY_VARIANT_ID);
                                }
                            }

                            $res = $this->order->save();

                            $fUserId = \Bitrix\Sale\Fuser::getId();
                            $siteId = $this->getSiteId();
                            $this->arResult['orderId'] = $res->getId();

                            if ($orderId = $res->getId()) {
                                $_SESSION['SOA_LAST_ORDER_ID'] = $orderId;

                                foreach (self::PROPS_RESET_AFTER_ORDER as $propId) {
                                    unset($_SESSION['SOA_PROP_VALS_B']['ORDER_PROP_' . $propId]);
                                }

                                unset($_SESSION['SOA_IPOL_PVZ']);
                                \Bitrix\Sale\BasketComponentHelper::clearFUserBasketPrice($fUserId, $siteId);
                                \Bitrix\Sale\BasketComponentHelper::clearFUserBasketQuantity($fUserId, $siteId);
                                $this->arResult['location'] = $APPLICATION->GetCurDir() . '?ORDER_ID=' . $res->getId();
                            } else {
                                $this->arResult['error'] = $res->getErrorMessages();
                                $this->arResult['IPOL'] = $this->getSettingsToIpol();
                            }
                        }
                    }

                    $this->arResult['SOA_SESS'] = $_SESSION['SOA_SESS'];
                    $this->arResult['noBasketText'] = $this->arParams['TEXT_NO_BASKET'];
                    $this->arResult['TEXT_BOTTOM'] = $this->arParams['~TEXT_BOTTOM'];
                    global $APPLICATION;
                    $APPLICATION->RestartBuffer();
                    echo json_encode($this->arResult);
                    die();
                }
            }
        }

        public function getSettingsToIpol()
        {
            $GOODS = \Ipol\Ozon\Bitrix\Handler\GoodsPicker::fromBasket();
            $obDefGabs = new \Ipol\Ozon\Bitrix\Entity\DefaultGabarites();
            $obCargo = new \Ipol\Ozon\Bitrix\Adapter\Cargo($obDefGabs);
            $cargos = $obCargo->set($GOODS)->getCargo();
            $dims = $cargos->getDimensions();
            $city = $this->arUserResult['DELIVERY_LOCATION'];
            $profileLink = \Ipol\Ozon\PvzWidgetHandler::getProfileLink();
            $paymentCorresponds = \Ipol\Ozon\Bitrix\Adapter::getPaymentCorresponds();
            $savLink = \Ipol\Ozon\PvzWidgetHandler::getSavingLink();
            $postField = \Ipol\Ozon\PvzWidgetHandler::getPostField();
            $addressInput = \Ipol\Ozon\PvzWidgetHandler::getAddressInput();
            $widgetOptions = \Ipol\Ozon\PvzWidgetHandler::$options;
            $pickPost = [
                'pickup' => $widgetOptions->fetchPvzLabel()
                    ?: \Ipol\Ozon\Bitrix\Tools::getMessage('SIGN_CHOOSEPVZDEF'),
                'postamat' => $widgetOptions->fetchPostamatLabel()
                    ?: \Ipol\Ozon\Bitrix\Tools::getMessage('SIGN_CHOOSEPPOINTDEF'),
            ];
            $module = "IPOL_OZON_";
            $messages = [
                'WRONG_PAY' => \Ipol\Ozon\Bitrix\Tools::getMessage('WIDJET_ERROR_WRONGPAY'),
                'PVZTYPE_postamat' => \Ipol\Ozon\Bitrix\Tools::getMessage('WIDJET_PVZTYPE_postamat'),
                'PVZTYPE_pickup' => \Ipol\Ozon\Bitrix\Tools::getMessage('WIDJET_PVZTYPE_pickup'),
            ];
            $gabarits = [
                'length' => $dims['L'],
                'width' => $dims['W'],
                'height' => $dims['H'],
                'price' => $cargos->getTotalPrice()->getAmount(),
                'weight' => $cargos->getWeight()
            ];
            $ipolSettings = [
                'goods' => $GOODS,
                'city' => $city,
                'cargo' => $gabarits,
                'pickpost' => $pickPost,
                'profileLink' => $profileLink
            ];

            return $ipolSettings;
        }

        public function executeComponent()
        {
            \Bitrix\Main\Loader::includeModule('sale');
            \Bitrix\Main\Loader::includeModule('iblock');
            \Bitrix\Main\Loader::includeModule('ipol.ozon');

            if (!empty($_GET['ORDER_ID'])) {
                unset($_SESSION['SOA_SESS']);
                $this->arResult = [];
                $orderId = (int) $_GET['ORDER_ID'];
                $order = $orderId > 0 ? \Bitrix\Sale\Order::load($orderId) : null;

                global $USER;
                $isOwner = $order && (
                    ($USER->IsAuthorized() && (int) $order->getUserId() === (int) $USER->GetID())
                    || (int) ($_SESSION['SOA_LAST_ORDER_ID'] ?? 0) === $orderId
                );

                if (!$isOwner) {
                    \Bitrix\Iblock\Component\Tools::process404('', true, true, true);

                    return;
                }

                $this->arResult['ORDER'] = $order;
                $this->includeComponentTemplate('confirm');
            } else {
                \CJSCore::Init(['jquery']);
                $pathToController = \Ipol\Ozon\Bitrix\Tools::getJSPath() . 'pvzWidjet.js';
                $pathToWidjet = \Ipol\Ozon\Bitrix\Tools::getJSPath() . 'widjet/widjet.js';

                if (
                    file_exists($_SERVER['DOCUMENT_ROOT'] . $pathToWidjet) &&
                    file_exists($_SERVER['DOCUMENT_ROOT'] . $pathToController)
                ) {
                    $GLOBALS['APPLICATION']->AddHeadScript($pathToWidjet);
                    $GLOBALS['APPLICATION']->AddHeadScript($pathToController);
                    \Ipol\Ozon\Bitrix\Tools::getCommonCss();
                }

                $this->ajaxHandler();
                $this->prepareResult();

                if (empty($_SESSION['SOA_SESS']))
                    $_SESSION['SOA_SESS'] = bin2hex(random_bytes(16));

                if (count($this->order->getBasket()->getOrderableItems()->getQuantityList()) > 0) {
                    $this->arResult['IPOL'] = $this->getSettingsToIpol();
                }

                $this->arResult['SOA_SESS'] = $_SESSION['SOA_SESS'];
                $this->arResult['noBasketText'] = $this->arParams['TEXT_NO_BASKET'];
                $this->arResult['TEXT_BOTTOM'] = $this->arParams['TEXT_BOTTOM'];
                $this->includeComponentTemplate();
            }
        }
    }