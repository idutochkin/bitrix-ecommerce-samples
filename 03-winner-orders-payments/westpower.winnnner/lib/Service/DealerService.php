<?
namespace Westpower\Winnnner\Service;

use Westpower\Winnnner\Orm\DealerTable;
use Westpower\Winnnner\Orm\DealerTransactionTable;
use CUser;

class DealerService
{
    /**
     * @param array $user
     * @param array $product
     */
    public static function increase(array $user, array $product): void
    {
        if($user["UF_DEALER"] > 0) {
            $dealer = DealerTable::getById($user["UF_DEALER"])->fetch();

            if($dealer) {
                $dealer = $GLOBALS['USER']->GetByID($dealer["USER_ID"])->Fetch();

                if($dealer['ID'] > 0) {
                    $sum = round($product['PRICE'] / 100 * $dealer["UF_PERCENT"]);

                    DealerTransactionTable::add([
                        'FROM' => $user['ID'],
                        'TO' => $dealer['ID'],
                        'SUM' => $sum,
                    ]);

                    (new CUser)->Update($dealer['ID'], ["UF_DEPOSIT" => $dealer["UF_DEPOSIT"] + $sum]);
                }
            }
        }
    }

    /**
     * @param array $userIds
     * @param int $dealerId
     */
    public static function setDealer(array $userIds, int $dealerId): void
    {
        foreach($userIds as $id)
            (new \CUser)->Update($id, ['UF_DEALER' => $dealerId]);
    }
}