<?
namespace Westpower\Fiji\Service;

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Westpower\Fiji\Repository\OptionRepository;
use Westpower\Fiji\Repository\Orm\PagerQueueTable;

class PagerService
{
    const PAGER_HIGHLOADBLOCK_CODE = 'Pager';

    /**
     * @return array
     */
    public static function getList(): array
    {
        $pagerList = [];

        Loader::includeModule('highloadblock');

        $hlblock = HighloadBlockTable::getList(['filter' => ['=NAME' => self::PAGER_HIGHLOADBLOCK_CODE]])->fetch();
        $entity = HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $entity->getDataClass();

        $rsData = $entityDataClass::getList(['order' => ['UF_PAGER' => 'ASC']]);

        while($arData = $rsData->fetch())
            $pagerList[] = $arData;

        return $pagerList;
    }

    /**
     * @param int $pagerId
     * @param int $waiterCode
     */
    public static function checkin(int $pagerId, int $waiterCode): void
    {
        Loader::includeModule('highloadblock');

        $hlblock = HighloadBlockTable::getList(['filter' => ['=NAME' => self::PAGER_HIGHLOADBLOCK_CODE]])->fetch();
        $entity = HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $entity->getDataClass();

        $checkinList = $entityDataClass::getList(['filter' => ['=UF_WAITER' => $waiterCode]])->fetchAll();
        foreach ($checkinList as $checkin)
            $entityDataClass::update($checkin['ID'], ['UF_WAITER' => null]);

        $checkinList = $entityDataClass::getList(['filter' => ['=UF_PAGER' => $pagerId]])->fetchAll();
        foreach ($checkinList as $checkin)
            $entityDataClass::update($checkin['ID'], ['UF_WAITER' => null]);

        $entityDataClass::update($pagerId, ['UF_WAITER' => $waiterCode]);
    }

    /**
     *
     */
    public static function resetCheckin(): void
    {
        if ((int) (new DateTime)->format('H') === (int) OptionRepository::get('cash_day_closing_time', 10)) {
            Loader::includeModule('highloadblock');

            $hlblock = HighloadBlockTable::getList(['filter' => ['=NAME' => self::PAGER_HIGHLOADBLOCK_CODE]])->fetch();
            $entity = HighloadBlockTable::compileEntity($hlblock);
            $entityDataClass = $entity->getDataClass();

            $rsData = $entityDataClass::getList();

            while ($arData = $rsData->fetch())
                $entityDataClass::update($arData['ID'], ['UF_WAITER' => null]);
        }
    }

    /**
     * @param int $tableId
     * @param array $pagerList
     * @param int $orderId
     */
	public static function call(int $tableId, array $pagerList = [],  int $orderId = 0): void
    {
        if (OptionRepository::get('pager_active', 'Y') !== 'Y')
            return;

        if (!$pagerList)
            $pagerList = \findAllUfPagerValues($tableId, 1);

        if (OptionRepository::get('pager_queue_active') === 'Y') {
            foreach ($pagerList as $pagerId) {
                PagerQueueTable::add([
                    'PAGER_ID' => $pagerId,
                    'TABLE_ID' => $tableId,
                    'ORDER_ID' => $orderId,
                    'STATUS' => 'N',
                    'COUNT_TRY' => 0
                ]);
            }
        } else {
            foreach ($pagerList as $pagerId)
                self::clientCall($tableId, $pagerId, $orderId);
        }
	}

    /**
     * @param int $tableId
     * @param int $pagerId
     * @param int $orderId
     * @return array
     */
	public static function clientCall(int $tableId, int $pagerId, int $orderId = 0): array
    {
		$command = '/usr/bin/python3 ' . $_SERVER['DOCUMENT_ROOT'] . '/local/kalyan.py' . ' ' . escapeshellarg($tableId);
		$command .= ' ' . escapeshellarg($pagerId);
		if ($orderId == 0)
			$tableId = 0;
		$command .= ' ' . escapeshellarg($tableId);
		$command .= ' ' . escapeshellarg(OptionRepository::get('pager_ip')) . '';
		$command .= ' ' . escapeshellarg(OptionRepository::get('pager_port'));
		$command = escapeshellcmd($command);
		$output = [];
		$return_var = null;

		exec($command . ' 2>&1', $output, $return_var);
		
		return [
		    'output' => $output,
            'return_var' => $return_var,
        ];
	}
}