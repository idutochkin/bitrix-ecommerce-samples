<?php

class SberQrClient
{
    private const API_URL = 'https://api.sberbank.ru:8443/prod/';
    private const CURRENCY_RUB = '643';
    private const SBP_MEMBER_ID = '100000000111';

    private string $clientId;
    private string $clientSecret;
    private string $memberId;
    private string $terminalId;
    private string $certPath;
    private string $keyPath;
    private string $caPath;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $memberId,
        string $terminalId,
        string $certPath,
        string $keyPath,
        string $caPath
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->memberId = $memberId;
        $this->terminalId = $terminalId;
        $this->certPath = $certPath;
        $this->keyPath = $keyPath;
        $this->caPath = $caPath;
    }

    public function createOrder(string $orderNumber, int $sum, array $positions): array
    {
        $rqUid = $this->getRqUid();

        $body = [
            'rq_uid' => $rqUid,
            'rq_tm' => gmdate('Y-m-d\TH:i:s\Z'),
            'member_id' => $this->memberId,
            'order_number' => $orderNumber,
            'order_create_date' => gmdate('Y-m-d\TH:i:s\Z'),
            'order_params_type' => [],
            'id_qr' => $this->terminalId,
            'order_sum' => $sum,
            'currency' => self::CURRENCY_RUB,
            'description' => 'Заказ с сайта',
            'sbp_member_id' => self::SBP_MEMBER_ID,
        ];

        foreach ($positions as $position) {
            $body['order_params_type'][] = [
                'position_name' => $position['NAME'],
                'position_count' => (int)$position['COUNT'],
                'position_sum' => (int)$position['SUM'],
                'position_description' => $position['DESC'],
            ];
        }

        $response = $this->send('qr/order/v3/creation', $body, 'https://api.sberbank.ru/qr/order.create', $rqUid);

        if (empty($response['order_id']) || empty($response['order_form_url'])) {
            throw new \RuntimeException('Сбербанк: заказ не создан. ' . ($response['error_description'] ?? ''));
        }

        return $response;
    }

    public function getStatus(string $orderId, string $orderNumber): array
    {
        $rqUid = $this->getRqUid();

        $body = [
            'rq_uid' => $rqUid,
            'rq_tm' => gmdate('Y-m-d\TH:i:s\Z'),
            'order_id' => $orderId,
            'tid' => $this->terminalId,
            'partner_order_number' => $orderNumber,
        ];

        return $this->send('qr/order/v3/status', $body, 'https://api.sberbank.ru/qr/order.status', $rqUid);
    }

    private function getToken(string $scope): string
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type: application/x-www-form-urlencoded',
            'RqUID: ' . $this->getRqUid(),
            'X-IBM-Client-ID: ' . $this->clientId,
        ];

        $response = $this->request('tokens/v2/oauth', $headers, http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => $scope,
        ]));

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Сбербанк: не удалось получить токен');
        }

        return $response['access_token'];
    }

    private function send(string $method, array $body, string $scope, string $rqUid): array
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getToken($scope),
            'Content-Type: application/json',
            'RqUID: ' . $rqUid,
        ];

        return $this->request($method, $headers, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    private function request(string $method, array $headers, string $body): array
    {
        $ch = curl_init(self::API_URL . $method);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSLCERT => $this->certPath,
            CURLOPT_SSLKEY => $this->keyPath,
            CURLOPT_CAINFO => $this->caPath,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \RuntimeException('Сбербанк: ошибка соединения. ' . $error);
        }

        return json_decode($result, true) ?: [];
    }

    private function getRqUid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
