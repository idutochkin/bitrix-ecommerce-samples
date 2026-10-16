<?php

use Bitrix\Main\Web\Json;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $params */
?>
<div class="sberqr">
    <div class="sberqr__code" id="sberqr-code"></div>
    <a class="sberqr__link" href="<?= htmlspecialcharsbx($params['URL']) ?>" target="_blank" rel="noopener">
        Оплатить в приложении банка
    </a>
</div>
<script src="/local/js/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('sberqr-code'), <?= Json::encode($params['URL']) ?>);
</script>
