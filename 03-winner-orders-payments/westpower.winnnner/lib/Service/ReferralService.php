<?
namespace Westpower\Winnnner\Service;

use Westpower\Winnnner\Orm\ReferralTransactionTable;
use CUser;

class ReferralService
{
    /**
     * @param array $user
     * @param array $product
     */
    public static function increase(array $user, array $product): void
    {
        if($user["UF_REFERRER"] > 0) {
            $referrer = $GLOBALS['USER']->GetByID($user["UF_REFERRER"])->Fetch();
            $settings = PlatformService::getFields();

            if($settings['REFERRAL_ADDITIONAL'] == 0 || $settings['REFERRAL_ADDITIONAL'] > (int) $user['UF_REFERRAL_ACCRUED']) {
                if($referrer["UF_REFERRER_PERCENT"] > 0) {
                    $sum = round($product['PRICE'] / 100 * $referrer["UF_REFERRER_PERCENT"]);

                    ReferralTransactionTable::add([
                        'FROM' => $user['ID'],
                        'TO' => $referrer["ID"],
                        'SUM' => $sum,
                    ]);

                    (new CUser)->Update($referrer["ID"], ["UF_DEPOSIT" => $referrer["UF_DEPOSIT"] + $sum]);
                    (new CUser)->Update($user['ID'], ["UF_REFERRAL_ACCRUED" => $user["UF_REFERRAL_ACCRUED"] + 1]);
                }
            }
        }
    }
}