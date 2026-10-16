<?php

namespace Sale\Handlers\Delivery;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;
use Bitrix\Sale\Shipment;

class AvokitchenHandler extends Base
{
    protected static $isCalculatePriceImmediately = true;
    protected static $whetherAdminExtraServicesShow = true;

    private const CACHE_TTL = 3600;
    private const CACHE_DIR = '/avokitchen/delivery_zones';

    public static function getClassTitle()
    {
        return 'Avokitchen';
    }

    public static function getClassDescription()
    {
        return 'Доставка по зонам на карте';
    }

    protected function calculateConcrete(Shipment $shipment)
    {
        $result = new CalculationResult();
        $order = $shipment->getOrder();

        $props = [];
        foreach ($order->getPropertyCollection() as $property) {
            $props[$property->getField('CODE')] = $property->getValue();
        }

        $zone = $this->getZone((int)($props['DELIVERY_POLYGON_ID'] ?? 0));
        $lat = (float)($props['DELIVERY_LATITUDE'] ?? 0);
        $lon = (float)($props['DELIVERY_LONGITUDE'] ?? 0);

        if (!$lat || !$lon) {
            $result->addError(new Error('Выберите адрес доставки из подсказок'));
            return $result;
        }

        if ($zone === null || !$this->isPointInPolygon($lat, $lon, $zone['COORDINATES'])) {
            $result->addError(new Error('По указанному адресу доставка не осуществляется'));
            return $result;
        }

        $basketPrice = $order->getBasket()->getPrice();
        $freeFrom = $zone['MIN_ORDER_FREE_DELIVERY'];

        $price = ($freeFrom > 0 && $basketPrice >= $freeFrom) ? 0 : $zone['DELIVERY_COST'];

        $result->setDeliveryPrice(roundEx($price, SALE_VALUE_PRECISION));

        return $result;
    }

    private function getZone(int $zoneId): ?array
    {
        if ($zoneId <= 0) {
            return null;
        }

        foreach ($this->getZones() as $zone) {
            if ($zone['ID'] === $zoneId) {
                return $zone;
            }
        }

        return null;
    }

    private function getZones(): array
    {
        $iblockId = (int)$this->config['MAIN']['IBLOCK_ID'];
        if ($iblockId <= 0 || !Loader::includeModule('iblock')) {
            return [];
        }

        $cache = Cache::createInstance();
        if ($cache->initCache(self::CACHE_TTL, 'zones_' . $iblockId, self::CACHE_DIR)) {
            return $cache->getVars();
        }

        $zones = [];
        if ($cache->startDataCache()) {
            $taggedCache = Application::getInstance()->getTaggedCache();
            $taggedCache->startTagCache(self::CACHE_DIR);
            $taggedCache->registerTag('iblock_id_' . $iblockId);

            $res = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
                false,
                false,
                ['ID', 'IBLOCK_ID']
            );
            while ($element = $res->GetNextElement()) {
                $fields = $element->GetFields();
                $properties = $element->GetProperties();

                $zones[] = [
                    'ID' => (int)$fields['ID'],
                    'COORDINATES' => json_decode($properties['COORDINATES']['VALUE']['TEXT'] ?? '', true) ?: [],
                    'DELIVERY_COST' => (float)$properties['DELIVERY_COST']['VALUE'],
                    'MIN_ORDER_FREE_DELIVERY' => (float)$properties['MIN_ORDER_FREE_DELIVERY']['VALUE'],
                ];
            }

            $taggedCache->endTagCache();
            $cache->endDataCache($zones);
        }

        return $zones;
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

    protected function getConfigStructure()
    {
        return [
            'MAIN' => [
                'TITLE' => 'Настройки',
                'DESCRIPTION' => 'Зоны доставки',
                'ITEMS' => [
                    'IBLOCK_ID' => [
                        'TYPE' => 'NUMBER',
                        'NAME' => 'Инфоблок зон доставки',
                        'DEFAULT' => 15,
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
