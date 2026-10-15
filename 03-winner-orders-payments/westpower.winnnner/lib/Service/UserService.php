<?
namespace Westpower\Winnnner\Service;

use CUser;
use CFile;
use CEvent;
use Westpower\Winnnner\Orm\AuthListTable;
use Westpower\Winnnner\Orm\DealerTable;
use Westpower\Winnnner\Orm\OptionTable;

class UserService
{
    const AUTH_SERVICE_TELEGRAM = 'telegram';

    const ACCESS_FIELDS = [
        'IS_AUTHORIZED',
        'ID',
        'BLOCKED',
        'ACTIVE',
        'LOGIN',
        'EMAIL',
        'NAME',
        'LAST_NAME',
        'SECOND_NAME',
        'DATE_CREATE',
        'PERSONAL_GENDER',
        'PERSONAL_BIRTHDAY',
        'PERSONAL_PHOTO',
        'BALANCE_JETON',
        'DEPOSIT',
        'REFERRAL_LINK',
        'REFERRER',
        'REFERRER_PERCENT',
        'TELEGRAM_ID',
        'REFERRAL_ACCRUED',
        'DEALER',
        'CURRENT_GAME',
    ];

    /**
     * @return array
     */
    public static function getAccessFieldsCurrentUser(): array
    {
        $result['IS_AUTHORIZED'] = $GLOBALS['USER']->IsAuthorized();

        return array_merge($result, $result['IS_AUTHORIZED'] ? self::getFields($GLOBALS['USER']->GetID(), true, true) : []);
    }

    /**
     * @param int $id
     * @param bool $accessFields
     * @param false $format
     * @return array
     */
    public static function getFields(int $id, bool $accessFields = false, $format = false): array
    {
        $result = [];

        $fields = $GLOBALS['USER']->GetByID($id)->Fetch();
        foreach ($fields as $key => $value)
            $result[str_replace('UF_', '', $key)] = $value;

        if ($result && $format) {
            $result['PERSONAL_PHOTO'] = CFile::GetPath($result['PERSONAL_PHOTO']);
            $result['CURRENT_GAME'] = [
                'GAIN' => '-',
                'RESULT' => '-',
                'RANK' => '-',
            ];
        }

        return !$accessFields ? $result : array_filter($result, fn($value, $key) => in_array($key, self::ACCESS_FIELDS), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return bool
     */
    public static function checkAuth(): bool
    {
        return $GLOBALS['USER']->IsAuthorized();
    }

    /**
     * @param int $id
     * @param string $ip
     * @return bool
     */
    public static function checkBlocked(int $id, string $ip = ''): bool
    {
        $arFields = self::getFields($id);

        return $arFields['BLOCKED'] == 'Y'
            || $arFields['ACTIVE'] == 'N'
            || ($ip ? self::checkBlockedIp($ip) : false);
    }

    /**
     * @param string $ip
     * @return bool
     */
    public static function checkBlockedIp(string $ip): bool
    {
        $blockedIp = OptionTable::getList(['filter' => ['MODULE_ID' => PlatformService::MODULE_ID, 'NAME' => 'BLOCKED_IP']])->fetch()['VALUE'];
        $blockedIp = unserialize($blockedIp);

        foreach ($blockedIp as $blockIp) {
            if (
                ip2long($ip) >= ip2long($blockIp[0])
                && ip2long($ip) <= ip2long($blockIp[1])
            )
                return true;
        }

        return false;
    }

    /**
     * @param int $userId
     */
    public static function blockUser(int $userId): void
    {
        $GLOBALS['USER']->Update($userId, ['BLOCKED' => 'Y']);

        NotificationService::send('isBlocked', [], [$userId]);
    }

    /**
     * @param int $userId
     */
    public static function unBlockUser(int $userId): void
    {
        $GLOBALS['USER']->Update($userId, ['BLOCKED' => 'N']);
    }

    /**
     *
     */
    public static function logout(): void
    {
        $GLOBALS['USER']->Logout();
    }

    /**
     * @param int $userId
     * @return array
     */
    public static function getGifts(int $userId): array
    {
        /*$dbBonusTypes = BonusTypesTable::getList(["select" => ["*"]]);
        $dbBonusTransactions = BonusTransactionsTable::getList([
            "select" => ["*"],
            "order" => ["ID" => "DESC"],
            "filter" => ["UF_USER_ID" => $userId]
        ]);

        $arTypes = [];
        $arBonusTransactions = [];

        while($arItem = $dbBonusTypes->fetch()) {
            $arTypes[$arItem["ID"]] = $arItem["UF_NAME"];
        }
        while($arItem = $dbBonusTransactions->fetch()) {
            $arBonusTransactions[] = [
                "name" => $arTypes[$arItem["UF_TYPE"]],
                "value" => $arItem["UF_VALUE"],
                "date" => $arItem["UF_TIMESTAMP"]->format("d.m.Y H:i:s")
            ];
        }

        return $arBonusTransactions;*/
        return [];
    }

    /**
     * @param int $userId
     * @param array $fields
     * @return array|bool[]
     */
    public static function saveProfile(int $userId, array $fields): array
    {
        $user = new \CUser;

        if ($user->Update($userId, [
                "NAME" => $fields['name'],
                "PERSONAL_GENDER" => $fields['sex'],
                "PERSONAL_BIRTHDAY" => date("d.m.Y", strtotime($fields['birthday'])),
                "EMAIL" => $fields['email'],
        ]))
            return ["result" => true];
        else
            return ["error" => $user->LAST_ERROR];
    }

    /**
     * @param int $userId
     * @return array
     */
    public static function getReferral(int $userId): array
    {
        $arUser = $GLOBALS['USER']->GetByID($userId)->Fetch();

        $arResult = ["referral_link" => $arUser["UF_REFERRAL_LINK"]];

        $rsUsers = $GLOBALS['USER']->GetList(($by="ID"), ($order="ASC"), ['UF_REFERRER' => $userId]);
        while($arUser = $rsUsers->Fetch()) {
            $date_last_income = "-";
            $income = 0;

            /*$dbRes = WinnerFromReferralTable::getList(["select" => ["*"], "filter" => ["UF_USER_ID_FROM" => $arUser["ID"], "UF_USER_ID_TO" => $userId], "order" => ["UF_TIMESTAMP" => "ASC"]]);
            while($el = $dbRes->Fetch()) {
                $date_last_income = $el["UF_TIMESTAMP"]->toString();
                $income += $el["UF_SUM"];
            }*/

            $arResult["referrals"][] = [
                "referral" => $arUser["LOGIN"],
                "date_register" => $arUser["DATE_REGISTER"],
                "date_last_income" => $date_last_income,
                "income" => $income
            ];
        }

        return $arResult;
    }

    /**
     * @param string|null $service
     */
    public static function auth(string $service = null): void
    {
        $userId = 0;

        switch ($service) {
            case self::AUTH_SERVICE_TELEGRAM:
                $userId = TelegramService::auth();
                break;
        }

        if ($userId) {
            if(UserService::checkBlocked($userId, $_SERVER["REMOTE_ADDR"]))
                return;

            $GLOBALS['USER']->Authorize($userId, true);

            AuthListTable::add([
                'USER_ID' => $userId,
                'HTTP_REFERER' => $_SERVER['HTTP_REFERER'],
                'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
                'IP' => $_SERVER['REMOTE_ADDR']
            ]);
        }
    }

    /**
     * @param array $fields
     * @return int
     * @throws \Exception
     */
    public static function add(array $fields): int
    {
        $dealerId = 0;
        $referrerId = 0;

        $dealers = DealerTable::getList()->fetchAll();
        foreach ($dealers as $dealer) {
            if (in_array($_SERVER['SERVER_NAME'], $dealer['DOMAINS']))
                $dealerId = $dealer['ID'];
        }

        $arReferrers = [];
        if(isset($_COOKIE["referralLink"]) && !empty($_COOKIE["referralLink"])) {
            $rsUsers = $GLOBALS['USER']->GetList(($by="ID"), ($order="ASC"), ["UF_REFERRAL_LINK" => $_COOKIE["referralLink"]], ["SELECT" => ["UF_*"]]);
            while($arUser = $rsUsers->Fetch())
                $arReferrers[] = $arUser["ID"];
        }
        if(count($arReferrers) > 1)
            CEvent::Send("TOO_MATCH_REFERRER", "s1", ["referralLink" => $_COOKIE["referralLink"]]);
        if(isset($arReferrers[0]) && $arReferrers[0] > 0)
            $referrerId = $arReferrers[0];

        $userId = (new CUser)->Add([
            "ACTIVE" => "Y",
            "LOGIN" => $fields['PHONE'],
            "PASSWORD" => $fields['PASSWORD'],
            "CONFIRM_PASSWORD" => $fields['PASSWORD'],
            "NAME" => $fields['NAME'],
            "LAST_NAME" => $fields['LAST_NAME'],
            "UF_TELEGRAM_ID" => $fields['TELEGRAM_ID'],
            "UF_REFERRAL_LINK" => bin2hex(random_bytes(10)),
            "UF_REFERRER" => $referrerId,
            "UF_REFERRER_PERCENT" => 5,
            "UF_DEPOSIT" => 0,
            "UF_BALANCE_JETON" => 0,
            "UF_DEALER" => $dealerId
        ]);

        return (int) $userId;
    }
}