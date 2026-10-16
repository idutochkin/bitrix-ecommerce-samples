<?php

namespace Sale\Handlers\Delivery;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;
use Bitrix\Sale\Order;
use Bitrix\Sale\Shipment;

class KonturtermHandler extends Base
{
    protected static $isCalculatePriceImmediately = true;
    protected static $whetherAdminExtraServicesShow = true;

    private const CACHE_TTL = 3600;
    private const CACHE_DIR = '/konturterm/delivery';

    public static function getClassTitle()
    {
        return 'Konturterm';
    }

    public static function getClassDescription()
    {
        return 'Доставка по тарифам: город, за городом с доплатой за километр, подъём на этаж';
    }

    protected function calculateConcrete(Shipment $shipment)
    {
        $result = new CalculationResult();

        if (!Loader::includeModule('iblock')) {
            $result->addError(new Error('Модуль iblock не установлен'));
            return $result;
        }

        $order = $shipment->getOrder();

        $props = [];
        foreach ($order->getPropertyCollection() as $property) {
            $props[$property->getField('CODE')] = $property->getValue();
        }

        $lat = (float)($props['LOCATION_LAT_DELIVERY'] ?? 0);
        $lon = (float)($props['LOCATION_LONG_DELIVERY'] ?? 0);
        $type = (string)($props['TYPE_DELIVERY'] ?? '');

        if (!$lat || !$lon || $type === '') {
            $result->addError(new Error('Укажите адрес и способ доставки'));
            return $result;
        }

        $cityZone = $this->getCityZone();
        if (empty($cityZone)) {
            $result->addError(new Error('Не настроена зона доставки по городу'));
            return $result;
        }

        $isCity = $this->isPointInPolygon($lat, $lon, $cityZone);

        $tariff = $this->getTariff($isCity, $type);
        if ($tariff === null) {
            $result->addError(new Error('Доставка выбранным способом по этому адресу недоступна'));
            return $result;
        }

        $distance = 0;
        if (!$isCity) {
            $routeLength = (float)($props['LOCATION_LENGTH_DELIVERY'] ?? 0);
            $distance = max($routeLength, floor($this->getDistanceToPolygon($lat, $lon, $cityZone)));
        }

        $price = $this->getDeliveryPrice($order, $tariff, $distance);

        if (($props['IS_FLOOR'] ?? '') === 'Y') {
            $price += $this->getRiseFloorPrice($order, $props);
        }

        $result->setDeliveryPrice(roundEx($price, SALE_VALUE_PRECISION));

        return $result;
    }

    private function getDeliveryPrice(Order $order, array $tariff, float $distance): float
    {
        $basketPrice = $order->getBasket()->getPrice();

        if (in_array((int)$order->getPersonTypeId(), $tariff['FREE_PERSON_TYPE'], true)) {
            return 0;
        }

        if ($tariff['SUMM_LARGER'] > 0 && $basketPrice >= $tariff['SUMM_LARGER']) {
            return 0;
        }

        $price = $basketPrice > $tariff['SUMM'] ? $tariff['PRICE_MAX'] : $tariff['PRICE_MIN'];

        return $price + $distance * $tariff['PRICE_KM'];
    }

    private function getRiseFloorPrice(Order $order, array $props): float
    {
        $type = (string)($props['TYPE_FLOOR'] ?? '');
        $floor = (int)($props['NUM_FLOOR'] ?? 0);
        $elevator = ($props['IS_ELEVATOR'] ?? '') === 'Y';

        if ($type === 'ELEVATOR') {
            $floor = 1;
            $elevator = true;
        }

        if ($type === '' || $floor <= 0) {
            return 0;
        }

        $weight = $order->getBasket()->getWeight() / 1000;
        $largeSized = $this->hasLargeSized($order);

        foreach ($this->getRiseFloorTariffs() as $tariff) {
            if ($tariff['TARIF'] !== $type || !$this->isRiseFloorTariffMatch($tariff, $weight, $largeSized)) {
                continue;
            }

            if ($elevator || $tariff['ELEVATOR']) {
                return $tariff['PRICE_ELEVATOR_YES'];
            }

            return $floor * $tariff['PRICE_ELEVATOR_NO'];
        }

        return 0;
    }

    private function isRiseFloorTariffMatch(array $tariff, float $weight, bool $largeSized): bool
    {
        if ($largeSized) {
            return $tariff['LARGE_SIZED'];
        }

        $min = $tariff['WEIGHT_MIN'];
        $max = $tariff['WEIGHT_MAX'];

        return ($min > 0 || $max > 0)
            && ($min <= 0 || $weight >= $min)
            && ($max <= 0 || $weight <= $max);
    }

    private function hasLargeSized(Order $order): bool
    {
        $productIds = [];
        foreach ($order->getBasket() as $basketItem) {
            $productIds[] = $basketItem->getProductId();
        }

        if (empty($productIds)) {
            return false;
        }

        $res = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => (int)$this->config['MAIN']['CATALOG_IBLOCK_ID'],
                'ID' => $productIds,
                'PROPERTY_KRUPNOGABARITNYY_VALUE' => 'Да',
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        );

        return (bool)$res->Fetch();
    }

    private function getTariff(bool $isCity, string $type): ?array
    {
        $iblockId = (int)$this->config['MAIN']['TARIFF_IBLOCK_ID'];
        $cacheKey = 'tariff_' . $iblockId . '_' . (int)$isCity . '_' . md5($type);

        return $this->getCached($cacheKey, $iblockId, function () use ($iblockId, $isCity, $type) {
            $res = \CIBlockElement::GetList(
                ['SORT' => 'ASC'],
                [
                    'IBLOCK_ID' => $iblockId,
                    'ACTIVE' => 'Y',
                    'PROPERTY_IS_CITY_VALUE' => $isCity ? 'Y' : false,
                    'PROPERTY_RATE' => $type,
                ],
                false,
                ['nTopCount' => 1],
                ['ID', 'IBLOCK_ID']
            );

            if (!$element = $res->GetNextElement()) {
                return null;
            }

            $properties = $element->GetProperties();

            return [
                'FREE_PERSON_TYPE' => array_map('intval', (array)$properties['FREE_PERSON_TYPE']['VALUE']),
                'SUMM_LARGER' => (float)$properties['SUMM_LARGER']['VALUE'],
                'SUMM' => (float)$properties['SUMM']['VALUE'],
                'PRICE_MIN' => (float)$properties['PRICE_MIN']['VALUE'],
                'PRICE_MAX' => (float)$properties['PRICE_MAX']['VALUE'],
                'PRICE_KM' => (float)$properties['PRICE_KM']['VALUE'],
            ];
        });
    }

    private function getRiseFloorTariffs(): array
    {
        $iblockId = (int)$this->config['MAIN']['RISE_FLOOR_IBLOCK_ID'];

        return $this->getCached('rise_floor_' . $iblockId, $iblockId, function () use ($iblockId) {
            $tariffs = [];

            $res = \CIBlockElement::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
                false,
                false,
                ['ID', 'IBLOCK_ID']
            );
            while ($element = $res->GetNextElement()) {
                $properties = $element->GetProperties();

                $tariffs[] = [
                    'TARIF' => (string)$properties['TARIF']['VALUE'],
                    'WEIGHT_MIN' => (float)$properties['WEIGHT_MIN']['VALUE'],
                    'WEIGHT_MAX' => (float)$properties['WEIGHT_MAX']['VALUE'],
                    'PRICE_ELEVATOR_NO' => (float)$properties['PRICE_ELEVATOR_NO']['VALUE'],
                    'PRICE_ELEVATOR_YES' => (float)$properties['PRICE_ELEVATOR_YES']['VALUE'],
                    'LARGE_SIZED' => $properties['LARGE_SIZED']['VALUE'] === 'Y',
                    'ELEVATOR' => $properties['ELEVATOR']['VALUE'] === 'Y',
                ];
            }

            return $tariffs;
        }) ?? [];
    }

    private function getCityZone(): array
    {
        $iblockId = (int)$this->config['MAIN']['ZONE_IBLOCK_ID'];

        return $this->getCached('zone_' . $iblockId, $iblockId, function () use ($iblockId) {
            $res = \CIBlockElement::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
                false,
                ['nTopCount' => 1],
                ['ID', 'IBLOCK_ID']
            );

            if (!$element = $res->GetNextElement()) {
                return [];
            }

            $properties = $element->GetProperties();

            return json_decode($properties['ZONE']['VALUE']['TEXT'] ?? '', true) ?: [];
        }) ?? [];
    }

    private function getCached(string $key, int $iblockId, callable $callback)
    {
        $cache = Cache::createInstance();
        if ($cache->initCache(self::CACHE_TTL, $key, self::CACHE_DIR)) {
            return $cache->getVars()['DATA'];
        }

        $data = null;
        if ($cache->startDataCache()) {
            $taggedCache = Application::getInstance()->getTaggedCache();
            $taggedCache->startTagCache(self::CACHE_DIR);
            $taggedCache->registerTag('iblock_id_' . $iblockId);

            $data = $callback();

            $taggedCache->endTagCache();
            $cache->endDataCache(['DATA' => $data]);
        }

        return $data;
    }

    private function isPointInPolygon(float $lat, float $lon, array $rings): bool
    {
        $inside = false;

        foreach ($rings as $ring) {
            $count = count($ring);
            for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
                [$lonI, $latI] = $ring[$i];
                [$lonJ, $latJ] = $ring[$j];

                if (($latI > $lat) !== ($latJ > $lat)
                    && $lon < ($lonJ - $lonI) * ($lat - $latI) / ($latJ - $latI) + $lonI
                ) {
                    $inside = !$inside;
                }
            }
        }

        return $inside;
    }

    private function getDistanceToPolygon(float $lat, float $lon, array $rings): float
    {
        $kmPerLat = 111.13;
        $kmPerLon = 111.32 * cos(deg2rad($lat));
        $min = INF;

        foreach ($rings as $ring) {
            $count = count($ring);
            for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
                $ax = ($ring[$j][0] - $lon) * $kmPerLon;
                $ay = ($ring[$j][1] - $lat) * $kmPerLat;
                $bx = ($ring[$i][0] - $lon) * $kmPerLon;
                $by = ($ring[$i][1] - $lat) * $kmPerLat;

                $dx = $bx - $ax;
                $dy = $by - $ay;
                $lengthSq = $dx * $dx + $dy * $dy;
                $t = $lengthSq > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $lengthSq)) : 0;

                $min = min($min, hypot($ax + $t * $dx, $ay + $t * $dy));
            }
        }

        return is_finite($min) ? $min : 0;
    }

    protected function getConfigStructure()
    {
        return [
            'MAIN' => [
                'TITLE' => 'Настройки',
                'DESCRIPTION' => 'Инфоблоки тарифов и зон',
                'ITEMS' => [
                    'TARIFF_IBLOCK_ID' => [
                        'TYPE' => 'NUMBER',
                        'NAME' => 'Тарифы доставки',
                        'DEFAULT' => 20,
                    ],
                    'ZONE_IBLOCK_ID' => [
                        'TYPE' => 'NUMBER',
                        'NAME' => 'Зона доставки по городу',
                        'DEFAULT' => 18,
                    ],
                    'RISE_FLOOR_IBLOCK_ID' => [
                        'TYPE' => 'NUMBER',
                        'NAME' => 'Тарифы подъёма на этаж',
                        'DEFAULT' => 21,
                    ],
                    'CATALOG_IBLOCK_ID' => [
                        'TYPE' => 'NUMBER',
                        'NAME' => 'Каталог',
                        'DEFAULT' => 17,
                    ],
                ],
            ],
        ];
    }

    public function isCalculatePriceImmediately()
    {
        return self::$isCalculatePriceImmediately;
    }

    public static function whetherAdminExtraServicesShow()
    {
        return self::$whetherAdminExtraServicesShow;
    }
}
