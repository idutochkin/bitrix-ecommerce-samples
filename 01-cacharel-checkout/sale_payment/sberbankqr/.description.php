<?php

$data = [
    'NAME' => 'Сбербанк: оплата по QR-коду (СБП)',
    'SORT' => 500,
    'CODES' => [
        'SBERQR_CLIENT_ID' => [
            'NAME' => 'Client ID',
            'SORT' => 100,
            'GROUP' => 'SBERQR_CONNECT',
        ],
        'SBERQR_CLIENT_SECRET' => [
            'NAME' => 'Client Secret',
            'SORT' => 200,
            'GROUP' => 'SBERQR_CONNECT',
        ],
        'SBERQR_MEMBER_ID' => [
            'NAME' => 'Идентификатор клиента (member_id)',
            'SORT' => 300,
            'GROUP' => 'SBERQR_CONNECT',
        ],
        'SBERQR_TERMINAL_ID' => [
            'NAME' => 'Идентификатор терминала (id_qr)',
            'SORT' => 400,
            'GROUP' => 'SBERQR_CONNECT',
        ],
        'SBERQR_CERT_PATH' => [
            'NAME' => 'Путь к сертификату клиента',
            'SORT' => 500,
            'GROUP' => 'SBERQR_CONNECT',
        ],
        'SBERQR_KEY_PATH' => [
            'NAME' => 'Путь к закрытому ключу',
            'SORT' => 600,
            'GROUP' => 'SBERQR_CONNECT',
        ],
        'SBERQR_CA_PATH' => [
            'NAME' => 'Путь к корневому сертификату Сбербанка',
            'SORT' => 700,
            'GROUP' => 'SBERQR_CONNECT',
        ],
    ],
];
