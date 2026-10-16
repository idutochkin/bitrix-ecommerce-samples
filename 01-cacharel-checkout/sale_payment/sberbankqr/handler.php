<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem;

require_once __DIR__ . '/SberQrClient.php';

class SberbankqrHandler extends PaySystem\ServiceHandler implements PaySystem\ICheckable
{
    private const LINKS_HLBLOCK = 'SberQrLinks';
    private const STATUS_PAID = 'PAID';

    public function initiatePay(Payment $payment, Request $request = null)
    {
        $result = new PaySystem\ServiceResult();

        $url = $this->getPaymentLink($payment);

        if ($url === null) {
            try {
                $response = $this->getClient($payment)->createOrder(
                    (string)$payment->getId(),
                    $this->toKopecks($payment->getSum()),
                    $this->getPositions($payment)
                );
            } catch (\RuntimeException $e) {
                $result->addError(new Error($e->getMessage()));
                return $result;
            }

            $url = $response['order_form_url'];

            $payment->setFields([
                'PS_INVOICE_ID' => $response['order_id'],
                'PS_STATUS_CODE' => $response['order_state'],
                'PS_STATUS_DESCRIPTION' => $this->getStatusText($response['order_state']),
            ]);
            $payment->save();

            $this->getLinksTable()::add([
                'UF_PAYMENT_ID' => $payment->getId(),
                'UF_ORDER_ID' => $payment->getOrderId(),
                'UF_PS_INVOICE_ID' => $response['order_id'],
                'UF_PAYMENT_LINK' => $url,
            ]);
        }

        $this->setExtraParams(['URL' => $url]);

        $result = $this->showTemplate($payment, 'payment');
        if (method_exists($result, 'setPaymentUrl')) {
            $result->setPaymentUrl($url);
        }

        return $result;
    }

    public function check(Payment $payment)
    {
        $result = new PaySystem\ServiceResult();

        $invoiceId = (string)$payment->getField('PS_INVOICE_ID');
        if ($invoiceId === '') {
            $result->addError(new Error('Заказ в Сбербанке не создан'));
            return $result;
        }

        try {
            $response = $this->getClient($payment)->getStatus($invoiceId, (string)$payment->getId());
        } catch (\RuntimeException $e) {
            $result->addError(new Error($e->getMessage()));
            return $result;
        }

        $state = (string)($response['order_state'] ?? '');
        if ($state === '') {
            $result->addError(new Error('Сбербанк: статус не получен. ' . ($response['error_description'] ?? '')));
            return $result;
        }

        $result->setPsData([
            'PS_STATUS' => $state === self::STATUS_PAID ? 'Y' : 'N',
            'PS_STATUS_CODE' => $state,
            'PS_STATUS_DESCRIPTION' => $this->getStatusText($state),
            'PS_RESPONSE_DATE' => new DateTime(),
        ]);

        if ($state === self::STATUS_PAID && !$payment->isPaid()) {
            $result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
        }

        return $result;
    }

    public function processRequest(Payment $payment, Request $request)
    {
        return $this->check($payment);
    }

    public function getPaymentIdFromRequest(Request $request)
    {
        return (int)$request->get('PAYMENT_ID');
    }

    public function getCurrencyList()
    {
        return ['RUB'];
    }

    private function getPositions(Payment $payment): array
    {
        $order = $payment->getOrder();

        if ($this->toKopecks($payment->getSum()) !== $this->toKopecks($order->getPrice())) {
            return [[
                'NAME' => 'Оплата заказа №' . $order->getField('ACCOUNT_NUMBER'),
                'COUNT' => 1,
                'SUM' => $this->toKopecks($payment->getSum()),
                'DESC' => 'Частичная оплата',
            ]];
        }

        $positions = [];

        foreach ($order->getBasket() as $basketItem) {
            $positions[] = [
                'NAME' => $basketItem->getField('NAME'),
                'COUNT' => $basketItem->getQuantity(),
                'SUM' => $this->toKopecks($basketItem->getFinalPrice()),
                'DESC' => $basketItem->getField('NAME'),
            ];
        }

        foreach ($order->getShipmentCollection() as $shipment) {
            if ($shipment->isSystem() || $shipment->getPrice() <= 0) {
                continue;
            }

            $positions[] = [
                'NAME' => $shipment->getDeliveryName(),
                'COUNT' => 1,
                'SUM' => $this->toKopecks($shipment->getPrice()),
                'DESC' => 'Доставка',
            ];
        }

        return $positions;
    }

    private function getPaymentLink(Payment $payment): ?string
    {
        if ((string)$payment->getField('PS_INVOICE_ID') === '') {
            return null;
        }

        $row = $this->getLinksTable()::getList([
            'filter' => ['=UF_PAYMENT_ID' => $payment->getId()],
            'select' => ['UF_PAYMENT_LINK'],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return $row ? $row['UF_PAYMENT_LINK'] : null;
    }

    private function getLinksTable(): string
    {
        Loader::includeModule('highloadblock');

        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=NAME' => self::LINKS_HLBLOCK],
        ])->fetch();

        return HighloadBlockTable::compileEntity($hlblock)->getDataClass();
    }

    private function getClient(Payment $payment): \SberQrClient
    {
        return new \SberQrClient(
            (string)$this->getBusinessValue($payment, 'SBERQR_CLIENT_ID'),
            (string)$this->getBusinessValue($payment, 'SBERQR_CLIENT_SECRET'),
            (string)$this->getBusinessValue($payment, 'SBERQR_MEMBER_ID'),
            (string)$this->getBusinessValue($payment, 'SBERQR_TERMINAL_ID'),
            (string)$this->getBusinessValue($payment, 'SBERQR_CERT_PATH'),
            (string)$this->getBusinessValue($payment, 'SBERQR_KEY_PATH'),
            (string)$this->getBusinessValue($payment, 'SBERQR_CA_PATH')
        );
    }

    private function getStatusText(string $code): string
    {
        $statuses = [
            'PAID' => 'Оплачено',
            'CREATED' => 'Создана оплата',
            'REVERSED' => 'Отмена',
            'REFUNDED' => 'Возврат',
            'REVOKED' => 'Отменен до проведения фин. операции',
            'DECLINED' => 'Заказ отклонен',
            'EXPIRED' => 'Истек срок жизни заказа',
            'AUTHORIZED' => 'Авторизирован',
            'CONFIRMED' => 'Подтвержден',
            'ON_PAYMENT' => 'Ожидает подтверждения платежа от СБП',
        ];

        return $statuses[$code] ?? $code;
    }

    private function toKopecks(float $sum): int
    {
        return (int)round($sum * 100);
    }
}
