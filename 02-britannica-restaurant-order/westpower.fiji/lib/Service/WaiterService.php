<?
namespace Westpower\Fiji\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Highloadblock\HighloadBlockTable;
use Westpower\Fiji\Repository\WaiterRepository;
use Westpower\Fiji\Service\PagerService;

class WaiterService
{
    /**
     * @return array
     */
    public static function getList(): array
    {
        $cache = Cache::createInstance();
        $taggedCache = Application::getInstance()->getTaggedCache();
        $cachePath = '/Westpower/Fiji/Waiter/';
        $cacheKey = 'westpower_fiji_waiter';

        if ($cache->initCache(60*60*24, $cacheKey, $cachePath)) {
            $waiterList = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $taggedCache->startTagCache($cachePath);
            $taggedCache->registerTag('westpower_fiji_waiter');

            $waiterList = WaiterRepository::getList();

            $taggedCache->endTagCache();
            $cache->endDataCache($waiterList);
        }

        return $waiterList;
    }

    /**
     * @param int $tableId
     * @return int
     */
    public static function getByTable(int $tableId): int
    {
        Loader::includeModule('highloadblock');

        $hlblock = HighloadBlockTable::getList(['filter' => ['=NAME' => PagerService::PAGER_HIGHLOADBLOCK_CODE]])->fetch();
        $entity = HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $entity->getDataClass();

        $params = [
            'filter' => [
                'LOGIC' => 'OR',
                ['UF_TABLE' => $tableId],
                ['UF_TABLE' => $tableId . ',%'],
                ['UF_TABLE' => '%,' . $tableId . ',%'],
                ['UF_TABLE' => '%,' . $tableId]
            ]
        ];

        $result = $entityDataClass::getList($params)->fetch();

        return (int) $result['UF_WAITER'];
    }
}