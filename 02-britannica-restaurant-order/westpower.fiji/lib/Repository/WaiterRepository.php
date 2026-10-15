<?
namespace Westpower\Fiji\Repository;

use Westpower\Fiji\Repository\Client\RKeeperClient;
use DOMDocument;

// GetFunctions

class WaiterRepository
{
    /**
     * @return array|null
     */
    public static function getList():? array
    {
        $waiterList = [];

        $result = RKeeperClient::call('GetWaiterList');
        $waiters = $result->getElementsByTagName('Waiters')->item(0)->getElementsByTagName('waiter');

        $body = new DOMDocument;
        $body->formatOutput = true;
        $waiterXml = $body->createElement('Waiter');

        foreach ($waiters as $waiter) {
            $waiterXml->setAttribute('code', $waiter->getAttribute('Code'));
            $body->appendChild($waiterXml);

            $waiterInfo = RKeeperClient::call('GetEmployeeInfo2', $body);

			// проверяем права, чтобы выделить официантов
            /*
            $rights = [];
			$itemRights = $waiterInfo->getElementsByTagName('ObjectRights')->item(0)->getElementsByTagName('Item');
			foreach ($itemRights as $item) {
				$rights[] = $item->getAttribute('id');
			}

			if (
				!in_array('273', $rights)
				|| !in_array('274', $rights)
				|| count($rights) !== 2
				)
				continue;

			$operations = [];
			$itemOperations = $waiterInfo->getElementsByTagName('Operations')->item(0)->getElementsByTagName('Item');
			foreach ($itemOperations as $item) {
				$operations[] = $item->getAttribute('id');
			}

			if (
				!in_array('204', $operations)
				|| !in_array('211', $operations)
				|| !in_array('212', $operations)
				|| !in_array('215', $operations)
				|| !in_array('227', $operations)
				|| !in_array('236', $operations)
				|| !in_array('246', $operations)
				|| !in_array('308', $operations)
				|| !in_array('320', $operations)
				|| !in_array('409', $operations)
				|| !in_array('411', $operations)
				|| !in_array('415', $operations)
				|| !in_array('416', $operations)
				|| !in_array('417', $operations)
				|| !in_array('424', $operations)
				|| !in_array('435', $operations)
				|| !in_array('438', $operations)
				|| !in_array('440', $operations)
				|| !in_array('441', $operations)
				|| !in_array('461', $operations)
				|| !in_array('462', $operations)
				|| !in_array('465', $operations)
				|| !in_array('475', $operations)
				|| !in_array('476', $operations)
				|| !in_array('802', $operations)
				|| !in_array('805', $operations)
				|| !in_array('831', $operations)
				|| !in_array('1129', $operations)
				|| !in_array('1132', $operations)
				|| count($operations) !== 29
				)
				continue;
			*/

            $waiterList[] = [
                'CODE' => $waiter->getAttribute('Code'),
                'NAME' => $waiterInfo->getElementsByTagName('Waiter')->item(0)->getAttribute('name'),
            ];

			/*$collator = new \Collator('ru_RU');
            usort($waiterList, function($a, $b) use ($collator) {
                return $collator->compare($a['NAME'], $b['NAME']);
			});*/

            setlocale(LC_COLLATE, 'ru_RU.UTF-8');
            usort($waiterList, function($a, $b) {
                return strcoll($a['NAME'], $b['NAME']);
            });
        }

        return $waiterList;
    }
}