<?
namespace Westpower\Winnnner\PaySystem\Handlers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\UrlManager;
use Bitrix\Main\Web\Uri;
use Westpower\Winnnner\Controller\PaymentController;
use Westpower\Winnnner\Orm\CurrencyTable;
use Westpower\Winnnner\Orm\OrderTable;
use Westpower\Winnnner\Orm\PaySystemTable;
use Westpower\Winnnner\Orm\ProductTable;
use Westpower\Winnnner\Service\PlatformService;
use Westpower\Winnnner\Service\ProductService;

abstract class Base
{
    /**
     * @param $request
     * @param int $paySystemId
     * @return bool
     */
    abstract function isMyResponse($request, int $paySystemId): bool;

    /**
     * @param $request
     * @return array
     */
    abstract function processRequest($request): array;

    /**
     * @param int $orderId
     * @return array
     */
    abstract function initiatePay(int $orderId): array;

    /**
     * @return string
     */
    protected function getSiteUrl(): string
    {
        $request = Context::getCurrent()->getRequest();

        return ($request->isHttps() ? 'https://' : 'http://') . Context::getCurrent()->getServer()->getServerName();
    }

    /**
     * @param int $orderId
     * @return string
     */
    protected function getCallbackUrl(int $orderId): string
    {
        $uri = new Uri($this->getSiteUrl() . UrlManager::getInstance()->createByController(new PaymentController, 'callback'));
        $uri->addParams([
            'order' => $orderId,
            'sign' => $this->signOrder($orderId),
        ]);

        return $uri->getUri();
    }

    /**
     * @param $request
     * @param int $orderId
     * @return bool
     */
    protected function isValidCallback($request, int $orderId): bool
    {
        return $orderId > 0
            && (int) $request->get('order') === $orderId
            && hash_equals($this->signOrder($orderId), (string) $request->get('sign'));
    }

    /**
     * @param int $orderId
     * @return string
     */
    private function signOrder(int $orderId): string
    {
        $secret = Option::get(PlatformService::MODULE_ID, 'PAYMENT_CALLBACK_SECRET');
        if (!$secret) {
            $secret = bin2hex(random_bytes(32));
            Option::set(PlatformService::MODULE_ID, 'PAYMENT_CALLBACK_SECRET', $secret);
        }

        return hash_hmac('sha256', (string) $orderId, $secret);
    }

    /**
     * @param int $orderId
     * @param $className
     */
    public function showTemplate(int $orderId, $className): void
    {
        $request = Context::getCurrent()->getRequest();
        $templateName = '';
        $fields = [];

        $order = OrderTable::getList(['filter' => ['ID' => $orderId]])->fetch();

        if (!$order || (int) $order['USER_ID'] !== (int) $GLOBALS['USER']->GetID()) {
            \CHTTP::setStatus('404 Not Found');
            return;
        }

        $order['PRODUCT'] = ProductTable::getList(['filter' => ['ID' => $order['PRODUCT_ID']]])->fetch();
        $order['PAY_SYSTEM'] = PaySystemTable::getList(['filter' => ['ID' => $order['PAY_SYSTEM_ID']]])->fetch();
        $order['CURRENCY'] = CurrencyTable::getList(['filter' => ['ID' => $order['PAY_SYSTEM']['CURRENCY_ID']]])->fetch();

        if ($request->isPost() && check_bitrix_sessid()) {
            switch ($order['PRODUCT']['TYPE']) {
                case ProductService::TYPE_WITHDRAW:
                    $result = $this->initiateWithdraw($orderId);
                    break;
                default:
                    $result = $this->initiatePay($orderId);
            }

            if (isset($result['ERROR'])) {
                $templateName = 'Error';
                $fields = [
                    'TITLE' => $result['ERROR']['TITLE'],
                    'MESSAGE' => $result['ERROR']['MESSAGE'],
                    'SERVICE_MESSAGE' => $result['ERROR']['SERVICE_MESSAGE']['MESSAGE']
                ];
            } elseif ($result['RESULT']['TITLE'] && $result['RESULT']['MESSAGE']) {
                $templateName = 'Success';
                $fields = [
                    'TITLE' => $result['RESULT']['TITLE'],
                    'MESSAGE' => $result['RESULT']['MESSAGE']
                ];
            }
        }

        if (!$templateName) {
            switch ($order['PRODUCT']['TYPE']) {
                case ProductService::TYPE_WITHDRAW:
                    $templateName = 'Withdraw';
                    break;
                case ProductService::TYPE_BUY_JETON:
                case ProductService::TYPE_BUY_SUBSCRIBE:
                    $templateName = 'Payment';
                    break;
            }
        }

        if (!$templateName)
            $templateName = '../Error';

        $localPath = __DIR__ . '/Templates/' . $className . '/';
        $template = file_get_contents($localPath . $templateName. '.php');

        if (!$template) {
            $templateName = '../Error';

            $localPath = __DIR__ . '/Templates/' . $className . '/';
            $template = file_get_contents($localPath . $templateName. '.php');
        }

        $template = str_replace('#SESSID#', bitrix_sessid_post(), $template);
        $template = str_replace('#PATH#', str_replace($_SERVER['DOCUMENT_ROOT'], '', $localPath), $template);
        $template = str_replace('#ORDER_ID#', $order['ID'], $template);
        $template = str_replace('#ORDER_SUM#', ((int) $order['SUM'] * $order['CURRENCY']['RATE'] + $order['PAY_SYSTEM']['COST_IN']) . $order['CURRENCY']['SHORT_NAME'], $template);

        foreach ($fields as $code => $value)
            $template = str_replace('#' . $code . '#', htmlspecialcharsbx((string) $value), $template);

        echo $template;
    }
}