<?
namespace Westpower\Fiji\Repository;

use Westpower\Fiji\Repository\Client\RKeeperClient;
use DOMDocument;

class OrderRepository
{
    /**
     * @param int $tableId
     * @return array
     */
    public static function getOrderByTableId(int $tableId): array
    {
        $visits = RKeeperClient::call('GetOrderList')->getElementsByTagName('Visit');

        foreach ($visits as $visit) {
            $order = $visit->getElementsByTagName('Order')->item(0);

            if ($tableId === (int) $order->getAttribute('TableCode')) {
                return [
                    'visitId' => $visit->getAttribute('VisitID'),
                    'orderId' => $order->getAttribute('OrderID'),
                    'finished' => $order->getAttribute('Finished') || $order->getAttribute('Bill')
                ];
            }
        }

        return [];
    }

    /**
     * @param int $visitId
     * @param int $orderId
     * @param bool $full
     * @return array|null
     */
    public static function getById(int $visitId, int $orderId, bool $full = false): ?array
    {
        $body = new DOMDocument;
        $body->formatOutput = true;

        $order = $body->createElement('Order');
        $order->setAttribute('visit', $visitId);
        $order->setAttribute('orderIdent', $orderId);
        $body->appendChild($order);

        $body->appendChild($order);

        $result = null;

        try {
            $order = RKeeperClient::call('GetOrder', $body)->getElementsByTagName('Order')->item(0);
            $deleted = (int) $order->getAttribute('deleted');
            $finished = (int) $order->getAttribute('finished');

            if (!$deleted && !$finished) {
                $result = [
                    'TABLE_ID' => $order->getAttribute('orderName'),
                ];

                if ($full)
                    $result['OBJECT_XML'] = $order;
            }
        } catch (\Exception $e) {
            \Bitrix\Main\Application::getInstance()->getExceptionHandler()->writeToLog($e);
        }

        return $result;
    }

    /**
     * @param int $tableId
     * @param int|null $waiterId
     * @param string $comment
     * @return array
     */
    public static function create(int $tableId, int $waiterId = null, string $comment = ''): array
    {
        $body = new DOMDocument;
        $body->formatOutput = true;

        $order = $body->createElement('Order');
        $order->setAttribute('persistentComment', $comment);

        $table = $body->createElement('Table');
        $table->setAttribute('code', $tableId);
        $order->appendChild($table);

        if ($waiterId) {
            $waiter = $body->createElement('Waiter');
            $waiter->setAttribute('code', $waiterId);
            $order->appendChild($waiter);
        }

        $guests = $body->createElement('Guests');
        $guests->setAttribute('count', 1); 
        $order->appendChild($guests);

        $body->appendChild($order);

        $result = RKeeperClient::call('CreateOrder', $body);

        return [
            'visitId' => $result->getAttribute('VisitID'),
            'orderId' => $result->getAttribute('OrderID'),
        ];
    }

    /**
     * @param int $visitId
     * @param int $orderId
     * @param array $products
     * @return array
     */
    public static function save(int $visitId, int $orderId, array $products): array
    {
        $body = new DOMDocument;
        $body->formatOutput = true;

        $order = $body->createElement('Order');
        $order->setAttribute('visit', $visitId);
        $order->setAttribute('orderIdent', $orderId);
        $body->appendChild($order);

        $session = $body->createElement('Session');

        foreach ($products as $product) {
            $dish = $body->createElement('Dish');
            $dish->setAttribute('id', $product['XML_ID']);
            $dish->setAttribute('quantity', $product['QUANTITY'] * 1000);

            if ($product['MODIFIER_ID']) {
                $modifier = $body->createElement('Modi');
                $modifier->setAttribute('id', $product['MODIFIER_ID']);
                $modifier->setAttribute('count', 1);

                $dish->appendChild($modifier);
            }

            $session->appendChild($dish);
        }
        $body->appendChild($session);
		
        $result = RKeeperClient::call('SaveOrder', $body);
        
        return [
            'visitId' => $result->getAttribute('VisitID'),
            'orderId' => $result->getAttribute('OrderID'),
        ];
    }

    /**
     * @param int $visitId
     * @param int $orderId
     * @return bool
     */
    public static function checkPaid(int $visitId, int $orderId): bool
    {
        $body = new DOMDocument;
        $body->formatOutput = true;

        $order = $body->createElement('Order');
        $order->setAttribute('visit', $visitId);
        $order->setAttribute('orderIdent', $orderId);

        $body->appendChild($order);

        $result = RKeeperClient::call('GetOrder', $body);
        $order = $result->getElementsByTagName('Order')->item(0);

        return $order->getAttribute('finished') || $order->getAttribute('paid');
    }

    /**
     * @param int $visitId
     * @param int $orderId
     */
    public static function printBill(int $visitId, int $orderId): void
    {
        $body = new DOMDocument;
        $body->formatOutput = true;

        $order = $body->createElement('Order');
        $order->setAttribute('visit', $visitId);
        $order->setAttribute('orderIdent', $orderId);

        $body->appendChild($order);

        $station = $body->createElement('Station');
        $station->setAttribute('code', OptionRepository::get('rkeeper_station_code'));

        $body->appendChild($station);

        $cashier = $body->createElement('Cashier');
        $cashier->setAttribute('code', OptionRepository::get('rkeeper_cashier_code'));

        $body->appendChild($cashier);

        RKeeperClient::call('PrintBill', $body);
    }

    /**
     * @param int $visitId
     */
    public static function closeVisit(int $visitId): void
    {
        RKeeperClient::call('CloseVisit', null, ['VisitID' => $visitId]);
    }

    /**
     * @param int $visitId
     * @param int $orderId
     * @param int $sum
     */
    public static function payOrder(int $visitId, int $orderId, int $sum): void
    {
        $body = new DOMDocument;
        $body->formatOutput = true;

        $order = $body->createElement('Order');
        $order->setAttribute('visit', $visitId);
        $order->setAttribute('orderIdent', $orderId);

        $body->appendChild($order);

        $station = $body->createElement('Station');
        $station->setAttribute('code', OptionRepository::get('rkeeper_station_code'));

        $body->appendChild($station);

        $cashier = $body->createElement('Cashier');
        $cashier->setAttribute('code', OptionRepository::get('rkeeper_cashier_code'));

        $body->appendChild($cashier);

        $payment = $body->createElement('Payment');
        $payment->setAttribute('id', 1); //id валюты
        $payment->setAttribute('amount', $sum * 100);

        $body->appendChild($payment);

        RKeeperClient::call('PayOrder', $body);
    }
}