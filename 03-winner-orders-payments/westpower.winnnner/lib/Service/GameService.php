<?
namespace Westpower\Winnnner\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use Westpower\Winnnner\Agent\Agent;
use Westpower\Winnnner\Orm\GameTable;
use Westpower\Winnnner\Orm\OptionTable;
use Westpower\Winnnner\Orm\SubscribeTable;
use Westpower\Winnnner\Orm\FundTransactionTable;
use Westpower\Winnnner\Orm\GameResultTable;
use CUser;

class GameService
{
    public const FUND_INCREASE = 1;
    public const FUND_DECREASE = 2;

    /**
     * @param int $id
     * @param false $format
     * @return array
     */
    public static function getFields(int $id = 0, $format = false): array
    {
        if (!$id)
            $id = PlatformService::getCalendarGameId();

        $fields = GameTable::getById($id)->fetch();

        $fields['CURRENT_GAME'] = [];
        $fields['PICTURE'] = $fields['PICTURE']['SRC'];
        $fields['SOUND_TRUE'] = $fields['SOUND_TRUE']['SRC'];
        $fields['SOUND_FALSE'] = $fields['SOUND_FALSE']['SRC'];

        $platformFields = PlatformService::getFields(false);
        $currentFund = $platformFields['GAME_FUND_' . $fields['ID']];
        $distributionMinutes = (int) $platformFields['INCREASE_FUND_TIME_AFTER_GAME'];

        $fields['CURRENT_GAME']['START_TIME'] = (new DateTime((new DateTime())->format('d.m.Y ' . $fields['START_TIME'] . ':00:00')));
        $fields['CURRENT_GAME']['STOP_TIME'] = (new DateTime((new DateTime())->format('d.m.Y ' . $fields['STOP_TIME'] . ':00:00')));
        $fields['CURRENT_GAME']['DISTRIBUTION_TIME'] = (
            new DateTime() >= $fields['CURRENT_GAME']['STOP_TIME']
            && new DateTime() <= (clone $fields['CURRENT_GAME']['STOP_TIME'])->add($distributionMinutes . ' minutes')
        );
        $fields['CURRENT_GAME']['FUND'] = (int) ($currentFund ?: $fields['FUND']);
        $fields['CURRENT_GAME']['USERS_ONLINE'] = (int) self::getCountUsersOnline()[$fields['ID']];

        $dateTime = (new DateTime());
        if ($dateTime->format('H') >= $fields['CURRENT_GAME']['START_TIME']->format('H') && $dateTime->format('H') < $fields['CURRENT_GAME']['STOP_TIME']->format('H')) {
            $fields['ACCESS_GAME_BY_TIME'] = true;
            $fields['ACCESS_LAST_TIME'] = -($fields['CURRENT_GAME']['STOP_TIME']->getTimestamp() - $dateTime->getTimestamp());
        } else {
            $fields['ACCESS_GAME_BY_TIME'] = false;
            $fields['ACCESS_LAST_TIME'] = $fields['CURRENT_GAME']['START_TIME']->add('1 day')->getTimestamp() - $dateTime->getTimestamp();
        }

        $subscribe = [];
        if ($GLOBALS['USER']->IsAuthorized())
            $subscribe = SubscribeTable::getList(['filter' => ['USER_ID' => $GLOBALS['USER']->GetID(), '>=DATE_TO' => new Date()], 'limit' => 1])->fetch();

        $fields['SUBSCRIBE'] = $fields['SUBSCRIBE'] == 'Y';
        $fields['SUBSCRIBE_TO_BUY_GAME'] = $fields['SUBSCRIBE_TO_BUY_GAME'] == 'Y';
        $fields['SUBSCRIBE_TO'] = !empty($subscribe) ? $subscribe['DATE_TO']->format('d.m.Y') : null;
        $fields['ACCESS_GAME_BY_SUBSCRIBE'] = !($fields['SUBSCRIBE'] && $fields['SUBSCRIBE_TO_BUY_GAME'] && $fields['SUBSCRIBE_TO'] === null);

        if ($format) {

            foreach ($fields as &$value) {
                if ($value instanceof DateTime)
                    $value = $value->toString();
            }
        }

        return $fields;
    }

    /**
     * @param int $gameId
     * @return mixed
     */
    private static function getSavedData(int $gameId)
    {
        return Application::getInstance()->getLocalSession('game-' . $gameId);
    }

    /**
     * @param int $gameId
     * @param int $level
     * @return string[]
     */
    public static function getData(int $gameId, int $level): array
    {
        $game = self::getFields($gameId);

        $gameFields = self::getGameFields($game['SETTINGS'], $level);

        $savedData = self::getSavedData($gameId);
        $savedData->set('gameFields', ['level-'.$level => $gameFields]);

        if ($level == 1)
            $savedData->set('startTime', floor(microtime(true) * 1000));

        return $gameFields;
    }

    /**
     * @param array $settings
     * @param int $level
     * @return array
     */
    private static function getGameFields(array $settings, int $level): array
    {
        $leftValue = '';
        $rightValue = '';
        $level = $level - 1;
        $action = $settings['ACTIONS']['VALUE'][$level][0];
        $objectsLengthLeft = $settings['OBJECTS_LENGTH']['VALUE'][$level][0];
        $objectsLengthRight = $settings['OBJECTS_LENGTH']['VALUE'][$level][1];
        $maxNumberLeft = rand(0, $settings['MAX_NUMBER']['VALUE'][$level][0]);
        $maxNumberRight = rand(0, $settings['MAX_NUMBER']['VALUE'][$level][1]);

        switch ($action) {
            case '+':
                $accrued = $objectsLengthLeft;
                for ($i = $objectsLengthLeft; $i > 1; $i--) {
                    $value = rand(0, $maxNumberLeft - $accrued);
                    $accrued += $value;
                    $leftValue .= $value . ' ' . $action . ' ';
                }
                $leftValue .= $maxNumberLeft - $accrued + $objectsLengthLeft;

                $accrued = $objectsLengthRight;
                for ($i = $objectsLengthRight; $i > 1; $i--) {
                    $value = rand(0, $maxNumberRight - $accrued);
                    $accrued += $value;
                    $rightValue .= $value . ' ' . $action . ' ';
                }
                $rightValue .= $maxNumberRight - $accrued + $objectsLengthRight;

                break;
            case '-':
                $value = rand($maxNumberLeft + $objectsLengthLeft, $maxNumberLeft * $objectsLengthLeft);
                $leftValue .= $value . ' ' . $action . ' ';

                $accrued = $value - $maxNumberLeft;
                for ($i = $objectsLengthLeft; $i > 2; $i--) {
                    $value = rand(0, $accrued - $objectsLengthLeft);
                    $accrued -= $value;
                    $leftValue .= $value . ' ' . $action . ' ';
                }
                $leftValue .= $accrued;

                $value = rand($maxNumberRight + $objectsLengthRight, $maxNumberRight * $objectsLengthRight);
                $rightValue .= $value . ' ' . $action . ' ';

                $accrued = $value - $maxNumberRight;
                for ($i = $objectsLengthRight; $i > 2; $i--) {
                    $value = rand(0, $accrued - $objectsLengthRight);
                    $accrued -= $value;
                    $rightValue .= $value . ' ' . $action . ' ';
                }
                $rightValue .= $accrued;

                break;
        }

        return [$leftValue, $rightValue];
    }

    /**
     * @param int $gameId
     * @param int $level
     * @param int $answer
     * @return bool
     */
    public static function checkAnswer(int $gameId, int $level, int $answer): bool
    {
        $result = false;

        $savedData = self::getSavedData($gameId);
        $gameFields = $savedData->get('gameFields')['level-' . $level];

        if (!empty($gameFields)) {
            $game = self::getFields($gameId);

            $greaterThan = $game['SETTINGS']['TYPE']['VALUE'][0][0] == 'Большее';
            $varLeft = self::getVarValue($gameFields[0]);
            $varRight = self::getVarValue($gameFields[1]);

            switch ($answer) {
                case -1:
                    $result = $varLeft < $varRight;
                    if ($greaterThan)
                        $result = !$result;
                    break;
                case 0:
                    $result = $varLeft === $varRight;
                    break;
                case 1:
                    $result = $varLeft > $varRight;
                    if ($greaterThan)
                        $result = !$result;
                    break;
            }
        }

        if ($result) {
            $savedData->set('gameFields', ['level-'.$level => []]);
            $savedData->set('levelFinished', $level);
        }

        return $result;
    }

    /**
     * @param string $var
     * @return int
     */
    private static function getVarValue(string $var): int
    {
        $var = explode(' ', $var);

        if (count($var) == 3) {
            switch ($var[1]) {
                case '+':
                    $var = $var[0] + $var[2];
                    break;
                case '-':
                    $var = $var[0] - $var[2];
                    break;
            }
        } else
            $var = $var[0];

        return $var;
    }

    /**
     * @param int $gameId
     * @param int $userId
     * @param bool $training
     * @return int
     */
    public static function saveResult(int $gameId, int $userId, bool $training = false): int
    {
        $game = self::getFields($gameId);

        $savedData = self::getSavedData($gameId);
        $finishTime = floor(microtime(true) * 1000) - $savedData->get('startTime');

        if (
            !$training
            && $savedData->get('levelFinished') > 0
            && $savedData->get('levelFinished') == count($game['SETTINGS']['OBJECTS_LENGTH']['VALUE'])
        ) {
            $savedData->set('levelFinished', 0);

            if(($game["FRAUD"]["MIN_GAME_TIME"]["VALUE"] * 1000) > $finishTime) {
                UserService::blockUser($userId);
                return 0;
            }

            GameResultTable::add([
                'GAME_ID' => $gameId,
                'USER_ID' => $userId,
                'TIME' => $finishTime,
                'TIMESTAMP' => new DateTime
            ]);
        }

        return $finishTime;
    }

    /**
     * @param int $userId
     * @return bool
     */
    public static function start(int $userId): bool
    {
        $user = UserService::getFields($userId);
        if($user["BLOCKED"] == "Y" && $user["ACTIVE"] == "N")
            return false;

        (new CUser)->Update($user['ID'], ['UF_BALANCE_JETON' => $user['BALANCE_JETON'] - 1]);

        return true;
    }

    /**
     * @param int $gameId
     * @param int $userId
     * @param $value
     */
    public static function increaseFund(int $gameId, int $userId, $value): void
    {
        $transaction = FundTransactionTable::add([
            'USER_ID' => $userId,
            'GAME_ID' => $gameId,
            'SUM' => $value,
            'TYPE' => self::FUND_INCREASE,
        ]);

        if ($transaction->isSuccess()) {
            OptionTable::update(
                ['MODULE_ID' => PlatformService::MODULE_ID, 'NAME' => 'GAME_FUND_' . $gameId],
                ['VALUE' => self::getFields($gameId)['CURRENT_GAME']['FUND'] + $value],
            );

            NotificationService::send('fundGame', [
                'value' => (int) $value
            ]);
        }
    }

    /**
     * @param int $gameId
     * @param int $userId
     * @param $value
     * @param int $rate
     */
    public static function increaseGain(int $gameId, int $userId, $value, int $rate): void
    {
        $user = UserService::getFields($userId);

        FundTransactionTable::add([
            'USER_ID' => $userId,
            'GAME_ID' => $gameId,
            'SUM' => $value,
            'TYPE' => GameService::FUND_DECREASE,
        ]);

        (new CUser)->Update($userId, ['UF_DEPOSIT' => $user['DEPOSIT'] + $value]);

        TelegramService::sendMessage(
            $user['TELEGRAM_ID'],
            'Уважаемый участник, турнир был закончен, поздравляем Вас, Вы победили в турнире, заняли ' . $rate . ' место, Вам начислено ' . $value . ' виннеров.'
        );
    }

    /**
     * @param int $gameId
     * @param bool $onlyReal
     * @return array
     */
    public static function getRating(int $gameId, bool $onlyReal = false): array
    {
        $game = self::getFields($gameId);
        $platformFields = PlatformService::getFields();

        $mp = (int) $game['WINNER_MP'];
        $a = (int) $game['WINNER_A'];
        $mpr = (int) $game['WINNER_MPR'];
        $mv = (int) $game['WINNER_MV'];

        $sumPsv = 0;
        $sumBv = 0;
        $sumV = 0;

        $arBots = [];
        $arUsers = [];
        $arRating = [];
        $bu = [];
        $arResult = [];

        if ($game) {
            $decreaseFund = FundTransactionTable::getList(['filter' => [
                'GAME_ID' => $game['ID'],
                'TYPE' => GameService::FUND_DECREASE,
                '>=TIMESTAMP' => new DateTime(date('d.m.Y ' . $game['START_TIME'] . ':00:00')),
                '<TIMESTAMP' => (new DateTime(date('d.m.Y ' . $game['STOP_TIME'] . ':00:00')))->add(((int) $platformFields['INCREASE_FUND_TIME_AFTER_GAME'] + Agent::DISTRIBUTION_FREQ) . ' minutes'),
            ], 'limit' => 1])->fetch();

            if (!$decreaseFund) {
                $rsData = Application::getConnection()->query("
                    SELECT `USER_ID`, MIN(`TIME`) AS `MIN_TIME` 
                    FROM `" . GameResultTable::getTableName() . "` 
                    WHERE `GAME_ID` = " . (int) $game["ID"] . " 
                    AND `TIMESTAMP` >= '" . (new DateTime())->format('Y-m-d ' . $game['START_TIME'] . ':00:00') . "' 
                    AND `TIMESTAMP` < '" . (new DateTime())->format('Y-m-d ' . $game['STOP_TIME'] . ':00:00') . "' 
                    GROUP BY `USER_ID` 
                    ORDER BY `MIN_TIME` ASC;
                ");
                while ($el = $rsData->Fetch()) {
                    $arUsers[] = [
                        "GAME_TIME" => (int)$el["MIN_TIME"],
                        "USER_ID" => $el["USER_ID"]
                    ];
                }
            }
        }

        if ($onlyReal) {
            $dbUsers = $GLOBALS['USER']->GetList($by = 'ID', $order = 'ASC', ['ID' => array_column($arUsers, 'USER_ID'), 'UF_BOT' => 1]);
            while ($arUser = $dbUsers->Fetch())
                $arBots[] = $arUser['ID'];
        }

        if (!empty($arUsers)) {
            $ku = count($arUsers);
            $kudpp = $ku <= $mpr ? $ku : floor($ku * $mpr / 100);

            $i = 0;
            foreach ($arUsers as $arUser) {
                $i++;

                if ($i > $kudpp)
                    break;

                $arRating[] = $arUser;

                if (isset($bu[(int) $arUser["GAME_TIME"]]))
                    $bu[(int) $arUser["GAME_TIME"]]["LENGTH"]++;
                else {
                    $bu[(int) $arUser["GAME_TIME"]]["LENGTH"] = 0;
                    $bu[(int) $arUser["GAME_TIME"]]["PLACE"] = $i;
                }
            }

            foreach ($arRating as $key => $arRate) {
                if (isset($bu[$arRate["GAME_TIME"]])) {
                    $arRating[$key]["bv"] = 1 / exp($a * ($bu[$arRate["GAME_TIME"]]["PLACE"] - 1));
                    $arRating[$key]["psv"] = $mp;

                    $sumPsv += $mp;
                    $sumBv += $arRating[$key]["bv"];
                } else {
                    $arRating[$key]["bv"] = 0;
                    $arRating[$key]["psv"] = 0;
                }
            }

            $opf = $game["CURRENT_GAME"]['FUND'] - $sumPsv;
            $sbv = $opf / $sumBv;

            if($winsUpper = $sbv > $mv)
                $sbv = $mv;

            foreach ($arRating as $key => $arRate) {
                $v = $arRate["psv"] + floor($sbv * round($arRate["bv"], 2));
                $arRating[$key]["v"] = $v > $mv ? $mv : $v;
                $sumV += $arRating[$key]["v"];
            }

            foreach ($arRating as $key => $arRate) {
                if ($onlyReal && in_array($arRate['user_id'], $arBots))
                    continue;

                $arResult[] = [
                    "RATE" => $key + 1,
                    "USER_ID" => $arRate["USER_ID"],
                    "USER_NAME" => " ",
                    "TIME" => $arRate["GAME_TIME"],
                    "PRIZE" => (int) $arRate["v"]
                ];
            }
        }

        if (!empty($arResult))
            return $arResult;
        else
            return ["ERROR" => true, "COUNT_RATING" => $ku ? $ku : 0];
    }

    /**
     * @param int $userId
     * @return bool[]
     */
    public static function getResult(int $userId): array
    {
        $arGames = [];
        $arResult["results"] = [];
        $arResult["prize_sum"] = 0;

        $dbGames = GameTable::getList();
        while ($arGame = $dbGames->fetch())
            $arGames[$arGame["ID"]] = $arGame;

        $dbRes = FundTransactionTable::getList([
            'filter' => ["USER_ID" => $userId, "TYPE" => self::FUND_DECREASE],
            'order' => ['TIMESTAMP' => 'DESC'],
            'limit' => 20,
        ]);
        while ($arItem = $dbRes->fetch()) {
            $ratingByGame = [];

            $arGameResult = Application::getConnection()->query("
                SELECT MIN(`TIME`) 
                FROM `" . GameResultTable::getTableName() . "` 
                WHERE `GAME_ID` = " . (int) $arItem["GAME_ID"] . " 
                AND `USER_ID` = " . (int) $userId . " 
                AND `TIMESTAMP` >= '" . $arItem["TIMESTAMP"]->format("Y-m-d 00:00:00") . "' 
                AND `TIMESTAMP` <= '" . $arItem["TIMESTAMP"]->format("Y-m-d 23:59:59") . "';
            ")->Fetch();

            $dbGameRating = Application::getConnection()->query("
                SELECT `GAME_ID`, `USER_ID`, MIN(`TIME`) AS `MIN_TIME`
                FROM `" . GameResultTable::getTableName() . "` 
                WHERE `GAME_ID` = " . (int) $arItem["GAME_ID"] . " 
                AND `TIMESTAMP` >= '" . $arItem["TIMESTAMP"]->format("Y-m-d 00:00:00") . "' 
                AND `TIMESTAMP` <= '" . $arItem["TIMESTAMP"]->format("Y-m-d 23:59:59") . "' 
                GROUP BY `USER_ID` 
                ORDER BY `MIN_TIME` ASC 
                LIMIT 100;
            ");
            while ($arGameRating = $dbGameRating->Fetch())
                $ratingByGame[$arGameRating["GAME_ID"]][] = $arGameRating["USER_ID"];

            $arResult["results"][] = [
                "game_name" => $arGames[$arItem["GAME_ID"]]["NAME"],
                "game_date" => $arItem["TIMESTAMP"]->toString(),
                "game_time" => (int)$arGameResult["MIN(`TIME`)"],
                "game_rank" => array_search($arItem["USER_ID"], (array)$ratingByGame[$arItem["GAME_ID"]]) === false ? "> 100" : array_search($arItem["USER_ID"], $ratingByGame[$arItem["GAME_ID"]]) + 1,
                "game_prize" => number_format($arItem["SUM"], 0, "", " ")
            ];

            $arResult["prize_sum"] += $arItem["SUM"];
        }

        $arResult["prize_sum"] = number_format($arResult["prize_sum"], 0, "", " ");

        if (!empty($arResult))
            return $arResult;
        else
            return ["error" => true];
    }

    /**
     * @param int $userId
     * @return array
     */
    public static function getHistory(int $userId): array
    {
        $arGames = [];
        $arResult["allresults"] = [];

        $dbGames = GameTable::getList();
        while ($arGame = $dbGames->fetch())
            $arGames[$arGame["ID"]] = $arGame;

        $dbRes = GameResultTable::getList([
            "filter" => ["USER_ID" => $userId],
            "order" => ["TIMESTAMP" => "DESC"],
            "limit" => 20
        ]);

        $ratingByGame = [];
        while($arItem = $dbRes->fetch()) {
            if(empty($ratingByGame[$arItem["GAME_ID"]][$arItem["TIMESTAMP"]->format("Y-m-d")])) {
                $dbGameRating = Application::getConnection()->query("
					SELECT `GAME_ID`, `USER_ID`, MIN(`TIME`) AS `MIN_TIME`
					FROM `" . GameResultTable::getTableName() . "`
					WHERE `GAME_ID` = " . (int) $arItem["GAME_ID"] . " 
					AND `TIMESTAMP` >= '".$arItem["TIMESTAMP"]->format("Y-m-d 00:00:00")."'
					AND `TIMESTAMP` <= '".$arItem["TIMESTAMP"]->format("Y-m-d 23:59:59")."'
					GROUP BY `USER_ID`
					ORDER BY `MIN_TIME` ASC
					LIMIT 100;
				");

                while($arGameRating = $dbGameRating->Fetch())
                    $ratingByGame[$arItem["GAME_ID"]][$arItem["TIMESTAMP"]->format("Y-m-d")][] = $arGameRating["USER_ID"];
            }

            $rank = array_search(
                $arItem["USER_ID"],
                $ratingByGame[$arItem["GAME_ID"]][$arItem["TIMESTAMP"]->format("Y-m-d")]
            );

            $arResult["allresults"][] = [
                "game_name" => $arGames[$arItem["GAME_ID"]]["NAME"],
                "game_date" => $arItem["TIMESTAMP"]->toString(),
                "game_time" => (int)$arItem["TIME"],
                "game_rank" => $rank === false ? "> 100" : $rank + 1
            ];
        }

        if(!empty($arResult))
            return $arResult;
        else
            return ["error" => true];
    }

    /**
     * @return array
     */
    public static function getCountUsersOnline(): array
    {
        $gamesCount = [];
        $timeAgo = (new DateTime())->add('-15 minutes')->format('Y-m-d H:i:s');

        $games = GameTable::getList()->fetchAll();
        foreach ($games as $game) {
            $gamesCount[$game['ID']] = (int)Application::getConnection()->query("
                SELECT COUNT(DISTINCT USER_ID) AS `COUNT` 
                FROM `" . GameResultTable::getTableName() . "` 
                WHERE `GAME_ID` = " . (int) $game['ID'] . " 
                AND `TIMESTAMP` >= '" . $timeAgo . "' 
                GROUP BY `GAME_ID`;
            ")->fetch()['COUNT'];
        }

        return $gamesCount;
    }
}