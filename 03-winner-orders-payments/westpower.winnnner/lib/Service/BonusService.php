<?
namespace Westpower\Winnnner\Service;
use Westpower\Winnnner\Orm\BonusTransactionTable;

class BonusService
{
    public const TYPE_JETON = 1;
    public const TYPE_WINNNNER = 2;

    /**
     * @param int $userId
     * @param int $value
     * @param int $type
     */
    public static function increase(int $userId, int $value, int $type): void
    {
        $message = '';
        $fields = [];
        $user = $GLOBALS['USER']->GetByID($userId)->Fetch();

        switch ($type) {
            case self::TYPE_JETON:
                $message = 'Администратор площадки начислил вам: ' . $value . ' жетонов';
                $fields = ["UF_BALANCE_JETON" => $user["UF_BALANCE_JETON"] + $value];
                break;
            case self::TYPE_WINNNNER:
                $message = 'Администратор площадки начислил вам: ' . $value . ' виннеров';
                $fields = ["UF_DEPOSIT" => $user["UF_DEPOSIT"] + $value];
                break;
        }

        $GLOBALS['USER']->Update($user['ID'], $fields);

        BonusTransactionTable::add([
            "TYPE" => $type,
            "USER_ID" => $user['ID'],
            "VALUE" => $value,
        ]);

        MessageService::send($user['ID'], $message);
    }
}