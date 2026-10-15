<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Westpower\Winnnner\PaySystem;

class PaymentController extends Controller
{
    public function configureActions()
    {
        return [
            'callback' => [
                'prefilters' => []
            ]
        ];
    }

    public function callbackAction()
    {
        $request = Context::getCurrent()->getRequest();
        $paySystem = PaySystem\Manager::searchByRequest($request);
        
        if ($paySystem)
            (new $paySystem())->processRequest($request);
        else {
            \CHTTP::setStatus('404 Not Found');
            $this->addError(new Error('Could not working.', 404));
        }
    }
}