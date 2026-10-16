<?php

namespace Sale\Handlers\Delivery;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;
use Bitrix\Sale\Shipment;

class KruassanfamHandler extends Base
{
    private const CACHE_TTL = 3600;
    private const CACHE_DIR = '/kruassanfam/delivery_zones';

    public static function getClassTitle()
    {
        return 'Kruassanfam';
    }

    public static function getClassDescription()
    {
        return 'Доставка из ближайшего ресторана по зонам на карте';
    }

    protected function calculateConcrete(Shipment $shipment)
    {
        $result = new CalculationResult();
        $order = $shipment->getOrder();

        $props = [];
        foreach ($order->getPropertyCollection() as $property) {
            $props[$property->getField('CODE')] = $property->getValue();
        }

        $zone = $this->getZone((int)($props['POLYGON_ID'] ?? 0));
        $restaurantId = (int)($props['RESTAURANT_ID'] ?? 0);
        $lat = (float)($props['LATITUDE'] ?? 0);
        $lon = (float)($props['LONGITUDE'] ?? 0);

        if (!$lat || !$lon) {
            $result->addError(new Error('Выберите адрес доставки из подсказок'));
            return $result;
        }

        if ($zone === null
            || $zone['RESTAURANT_ID'] !== $restaurantId
            || !$this->isPointInPolygon($lat, $lon, $zone['COORDINATES'])
        ) {
            $result->addError(new Error('По указанному адресу доставка не осуществляется'));
            return $result;
        }

        $basketPrice = $order->getBasket()->getPrice();
        $freeFrom = $zone['MIN_PRICE'];

        $price = ($freeFrom > 0 && $basketPrice >= $freeFrom) ? 0 : $zone['DELIVERY_PRICE'];

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
                ['SORT' => 'ASC'],
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
                    'RESTAURANT_ID' => (int)$properties['TERMINAL']['VALUE'],
                    'COORDINATES' => json_decode($properties['COORDS']['VALUE']['TEXT'] ?? '', true) ?: [],
                    'MIN_PRICE' => (float)$properties['MIN_PRICE']['VALUE'],
                    'DELIVERY_PRICE' => (float)$properties['DELIVERY_PRICE']['VALUE'],
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
                        'DEFAULT' => 57,
                    ],
                ],
            ],
        ];
    }

    public function isCalculatePriceImmediately()
    {
        return true;
    }

    public static function whetherAdminExtraServicesShow()
    {
        return true;
    }
}
