<?
namespace Westpower\Fiji\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use Westpower\Fiji\Repository\MenuRepository;
use Bitrix\Currency\CurrencyManager;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

class MenuService
{
    const MENU_IBLOCK_CODE = 'menu';

    /**
     * @param string $xmlId
     * @param int $price
     * @param bool $autoCreate
     * @return array
     */
    public static function getProductByXmlId(string $xmlId, int $price = 0, bool $autoCreate = true): array
    {
        $arProduct = \CIBlockElement::GetList([], ['IBLOCK_CODE' => self::MENU_IBLOCK_CODE, '=XML_ID' => $xmlId], false, false, [
            'ID',
            'XML_ID',
            'NAME'
        ])->Fetch();

        if (!$arProduct && $autoCreate)
            $arProduct = self::createProduct($xmlId, $price);

        return $arProduct ?: [];
    }

    /**
     * @param string $xmlId
     * @param int $price
     * @return array
     */
    public static function createProduct(string $xmlId, int $price = 0): array
    {
        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            $product = MenuRepository::getProductById($xmlId);

            if ($product) {
                $iblock = IblockTable::getList(['filter' => ['CODE' => self::MENU_IBLOCK_CODE], 'cache' => ['ttl' => 86400]])->fetch();

                // Запрос к клиенту выполняется долго, поэтому перед добавлением нужна проверка на уже добавленный товар
                if ($arProduct = self::getProductByXmlId($xmlId, 0, false))
                    return $arProduct;

                $id = (new \CIBlockElement)->Add([
                    'IBLOCK_ID' => $iblock['ID'],
                    'XML_ID' => $xmlId,
                    'NAME' => $product['NAME'],
                    'PROPERTY_VALUES' => ['PRICE' => $price],
                ]);
                if (!$id)
                    throw new \Exception('Что-то пошло не по плану. Обратитесь к официанту.');

                \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0]);
                $priceId = \CPrice::Add([
                    'PRODUCT_ID' => $id,
                    'CATALOG_GROUP_ID' => 1,
                    'PRICE' => $price,
                    'CURRENCY' => CurrencyManager::getBaseCurrency(),
                ]);
                if (!$priceId)
                    throw new \Exception('Что-то пошло не по плану. Обратитесь к официанту.');
            } else
                throw new \Exception('Что-то пошло не по плану. Обратитесь к официанту.');

            $connection->commitTransaction();

            return self::getProductByXmlId($xmlId);
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            throw $e;
        }
    }
}