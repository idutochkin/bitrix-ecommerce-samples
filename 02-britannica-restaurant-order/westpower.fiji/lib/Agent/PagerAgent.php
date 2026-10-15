<?
namespace Westpower\Fiji\Agent;

use Bitrix\Main\Type\DateTime;
use Westpower\Fiji\Repository\Orm\PagerQueueTable;
use Westpower\Fiji\Service\PagerService;

class PagerAgent
{
    /**
     * @return string
     */
    public static function callFromQueue(): string
    {
        $queue = PagerQueueTable::getList(['filter' => ['STATUS' => 'N'], 'limit' => 15]);

        while ($item = $queue->fetch()) {
            $result = PagerService::clientCall($item['TABLE_ID'], $item['PAGER_ID'], $item['ORDER_ID']);

            $fields = [
                'COUNT_TRY' => $item['COUNT_TRY'] + 1,
                'RESPONSE' => implode(PHP_EOL, $result['output']),
                'LAST_TRY' => new DateTime,
            ];

            if ($result['return_var'] === 0)
                $fields['STATUS'] = 'S';
            elseif ($fields['COUNT_TRY'] >= 3)
                $fields['STATUS'] = 'F';

            PagerQueueTable::update($item['ID'], $fields);

            sleep(2);
        }

        return __METHOD__ . '();';
    }
}