<?
namespace Westpower\Winnnner\Service;

use Bitrix\Main\Context;
use Westpower\Winnnner\Orm\CurrencyTable;
use Westpower\Winnnner\Orm\PaySystemTable;
use Westpower\Winnnner\Service\PlatformService;

class PaySystemService
{
    /**
     * @param bool $increase
     * @return array
     */
    public static function getList(bool $increase = true): array
    {
        $paySystems = [];
        $currencies = [];
        $filter = [];

        $filter['ACTIVE'] = 'Y';
        $filter['ID'] = array_keys(array_filter(PlatformService::getFields()['PAY_SYSTEM_ACCESS'], fn ($item) =>
            in_array(Context::getCurrent()->getRequest()->getHttpHost(), $item['DOMAIN'])));

        if (!$increase)
            $filter['!HANDLER'] = 'inner';

        $arCurrencies = CurrencyTable::getList()->fetchAll();
        foreach ($arCurrencies as $currency)
            $currencies[$currency['ID']] = $currency;

        $arPaySystems = PaySystemTable::getList(['filter' => $filter, 'order' => ['ID' => 'ASC']])->fetchAll();
        foreach ($arPaySystems as $paySystem) {
            $paySystem['CURRENCY'] = $currencies[$paySystem['CURRENCY_ID']];
            $paySystem['IS_INNER'] = $paySystem['HANDLER'] == 'inner';
            $paySystems[] = $paySystem;
        }

        return $paySystems;
    }
}