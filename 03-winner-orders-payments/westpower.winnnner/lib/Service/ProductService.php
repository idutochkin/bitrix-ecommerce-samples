<?
namespace Westpower\Winnnner\Service;

use Westpower\Winnnner\Orm\ProductTable;

class ProductService
{
    public const TYPE_WITHDRAW = 1;
    public const TYPE_BUY_JETON = 2;
    public const TYPE_BUY_SUBSCRIBE = 3;

    /**
     * @param int|null $type
     * @return array
     */
    public static function getList(int $type = null): array
    {
        $result = [];
        $filter = [];

        if ($type)
            $filter['TYPE'] = $type;

        $products = ProductTable::getList(['filter' => $filter])->fetchAll();
        foreach ($products as $product)
            $result[] = $product;

        return $result;
    }
}