<?
namespace Westpower\Fiji\Repository;

use Westpower\Fiji\Repository\Client\RKeeperClient;
use Westpower\Fiji\Repository\OptionRepository;
use DOMDocument;
use DOMNode;

class MenuRepository
{
    /**
     * @param bool $returnResult
     * @return DOMNode|null
     */
    public static function getPriceList(bool $returnResult = true):? DOMNode
    {
        $body = new DOMDocument;
        $body->formatOutput = true;

        $station = $body->createElement('Station');
        $station->setAttribute('code', OptionRepository::get('rkeeper_station_code'));

        $body->appendChild($station);

        return RKeeperClient::call('GetOrderMenu', $body, [], $returnResult);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function getProductById(int $id): array
    {
        $product = [];

        $result = RKeeperClient::call('GetRefData', null, ['RefName' => 'MenuItems', 'onlyActive' => '1']);
        $items = $result->getElementsByTagName('Items')->item(0)->getElementsByTagName('Item');

        foreach ($items as $item) {
            if ((int) $item->getAttribute('Ident') !== $id)
                continue;

            $product = [
                'NAME' => $item->getAttribute('Name'),
                'SECTION_XML_ID' => (int) $item->getAttribute('MainParentIdent'),
            ];
            
            break;
        }

        return $product;
    }

    /**
     * @param bool $returnResult
     * @return DOMNode|null
     */
    public static function getMenuList(bool $returnResult = true):? DOMNode
    {
        return RKeeperClient::call('GetRefData', null, ['RefName' => 'MenuItems', 'onlyActive' => '1'], $returnResult);
    }

    /**
     * @param bool $returnResult
     * @return DOMNode|null
     */
    public static function getMenuGroupList(bool $returnResult = true):? DOMNode
    {
        return RKeeperClient::call('GetRefData', null, ['RefName' => 'CategList'], $returnResult);
    }

    /**
     * @param bool $returnResult
     * @return DOMNode|null
     */
    public static function getModifierList(bool $returnResult = true):? DOMNode
    {
        return RKeeperClient::call('GetRefData', null, ['RefName' => 'MODIFIERS'], $returnResult);
    }

    /**
     * @param bool $returnResult
     * @return DOMNode|null
     */
    public static function getModifierGroupList(bool $returnResult = true):? DOMNode
    {
        return RKeeperClient::call('GetRefData', null, ['RefName' => 'MODISCHEMEDETAILS'], $returnResult);
    }
}