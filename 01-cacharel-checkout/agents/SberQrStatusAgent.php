<?php

namespace PhpInterface\Agent;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Order;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem\Manager;

class SberQrStatusAgent
{
    public static function run(int $paySystemId): string
    {
        $agent = __METHOD__ . '(' . $paySystemId . ');';

        if (!Loader::includeModule('sale')) {
            return $agent;
        }

        $service = Manager::getObjectById($paySystemId);
        if ($service === null || !$service->isCheckable()) {
            return $agent;
        }

        $res = Payment::getList([
            'select' => ['ID', 'ORDER_ID'],
            'filter' => [
                '=PAY_SYSTEM_ID' => $paySystemId,
                '=PAID' => 'N',
                '!=PS_INVOICE_ID' => '',
                '>=DATE_BILL' => (new DateTime())->add('-1 day'),
            ],
        ]);

        while ($row = $res->fetch()) {
            $order = Order::load($row['ORDER_ID']);
            if ($order === null) {
                continue;
            }

            $payment = $order->getPaymentCollection()->getItemById($row['ID']);
            if ($payment !== null) {
                $service->check($payment);
            }
        }

        return $agent;
    }
}
