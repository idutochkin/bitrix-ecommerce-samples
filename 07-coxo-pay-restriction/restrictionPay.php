<?php

use Bitrix\Catalog\CatalogIblockTable;
use Bitrix\Iblock\SectionElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\Entity;
use Bitrix\Sale\Payment;
use Bitrix\Sale\Services\Base\Restriction;

class RestrictionPay extends Restriction
{
    public static function getClassTitle()
    {
        return 'по разделу';
    }

    public static function getClassDescription()
    {
        return 'будет ограничена для выбранных разделов';
    }

    public static function check($params, array $restrictionParams, $serviceId = 0)
    {
        $restricted = array_map('intval', (array)($restrictionParams['SECTIONS'] ?? []));

        return !array_intersect($params['SECTIONS'], $restricted);
    }

    protected static function extractParams(Entity $entity)
    {
        if (!$entity instanceof Payment
            || !Loader::includeModule('iblock')
            || !Loader::includeModule('catalog')
        ) {
            return ['SECTIONS' => []];
        }

        $productIds = [];
        foreach ($entity->getCollection()->getOrder()->getBasket() as $basketItem) {
            $productIds[] = (int)$basketItem->getProductId();
        }

        return ['SECTIONS' => self::getSectionsWithParents(self::getParentProductIds($productIds))];
    }

    private static function getParentProductIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $parents = \CCatalogSku::getProductList($productIds) ?: [];

        $result = [];
        foreach ($productIds as $productId) {
            $result[] = isset($parents[$productId]) ? (int)$parents[$productId]['ID'] : $productId;
        }

        return array_unique($result);
    }

    private static function getSectionsWithParents(array $elementIds): array
    {
        if (empty($elementIds)) {
            return [];
        }

        $sectionIds = array_column(SectionElementTable::getList([
            'select' => ['IBLOCK_SECTION_ID'],
            'filter' => [
                '@IBLOCK_ELEMENT_ID' => $elementIds,
                '=ADDITIONAL_PROPERTY_ID' => null,
            ],
        ])->fetchAll(), 'IBLOCK_SECTION_ID');

        if (empty($sectionIds)) {
            return [];
        }

        $chainFilter = ['LOGIC' => 'OR'];

        $res = SectionTable::getList([
            'select' => ['IBLOCK_ID', 'LEFT_MARGIN', 'RIGHT_MARGIN'],
            'filter' => ['@ID' => array_unique($sectionIds)],
        ]);
        while ($section = $res->fetch()) {
            $chainFilter[] = [
                '=IBLOCK_ID' => $section['IBLOCK_ID'],
                '<=LEFT_MARGIN' => $section['LEFT_MARGIN'],
                '>=RIGHT_MARGIN' => $section['RIGHT_MARGIN'],
            ];
        }

        if (count($chainFilter) === 1) {
            return [];
        }

        return array_map('intval', array_column(SectionTable::getList([
            'select' => ['ID'],
            'filter' => [$chainFilter],
        ])->fetchAll(), 'ID'));
    }

    public static function getParamsStructure($entityId = 0)
    {
        $options = [];

        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $iblockIds = array_column(CatalogIblockTable::getList([
                'select' => ['IBLOCK_ID'],
                'filter' => ['=PRODUCT_IBLOCK_ID' => 0],
            ])->fetchAll(), 'IBLOCK_ID');

            if ($iblockIds) {
                $res = SectionTable::getList([
                    'select' => ['ID', 'NAME', 'DEPTH_LEVEL'],
                    'filter' => ['@IBLOCK_ID' => $iblockIds],
                    'order' => ['IBLOCK_ID' => 'ASC', 'LEFT_MARGIN' => 'ASC'],
                ]);
                while ($section = $res->fetch()) {
                    $options[$section['ID']] = str_repeat('. ', $section['DEPTH_LEVEL'] - 1) . $section['NAME'];
                }
            }
        }

        return [
            'SECTIONS' => [
                'TYPE' => 'ENUM',
                'MULTIPLE' => 'Y',
                'LABEL' => 'Разделы каталога',
                'OPTIONS' => $options,
            ],
        ];
    }
}
