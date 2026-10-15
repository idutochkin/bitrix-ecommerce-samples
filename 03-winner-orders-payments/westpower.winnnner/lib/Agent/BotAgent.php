<?
namespace Westpower\Winnnner\Agent;

use Bitrix\Main\Type\DateTime;
use Westpower\Winnnner\Orm\FundTransactionTable;
use Westpower\Winnnner\Orm\GameResultTable;
use Westpower\Winnnner\Orm\OptionTable;
use Westpower\Winnnner\Service\GameService;
use Westpower\Winnnner\Service\NotificationService;
use Westpower\Winnnner\Service\PlatformService;

class BotAgent
{
    /**
     * @param bool $reactive
     * @return string
     */
    public static function execute(bool $reactive = false): string
    {
        $gameId = PlatformService::getCalendarGameId();

        if ($reactive)
            self::runReactive($gameId);
        else
            self::run($gameId);

        return __METHOD__ . '(' . $reactive . ');';
    }

    /**
     * @param int $gameId
     */
    private static function run(int $gameId): void
    {
        $arBots = [];
        $arGame = GameService::getFields($gameId);

        if ($arGame["ID"] > 0) {
            $dbUsers = $GLOBALS['USER']->GetList($by = 'ID', $order = 'ASC', ['UF_BOT' => 1, 'ACTIVE' => 'Y']);
            while ($arUser = $dbUsers->Fetch())
                $arBots[] = $arUser;

            if (date('H') >= $arGame['START_TIME'] && date('H') < $arGame['STOP_TIME']) {
                shuffle($arBots);
                $arBots = array_slice($arBots, 0, rand(0, $arGame['BOTS']), true); // $arBots = array_slice($arBots, 0, round($arGame['BOTS'] / 3), true);

                if (date('H') == $arGame['START_TIME'] || date('H') == $arGame['STOP_TIME'] - 1)
                    $arBots = array_slice($arBots, 0, round(count($arBots) / 100 * 30), true);

                $countBots = count($arBots);
                if (!$countBots)
                    return;

                $sleep = round(30000000 / $countBots); // $sleep = round(10000000 / $countBots);

                $i = 0;
                foreach ($arBots as $bot) {
                    $i++;

                    self::playBot($gameId, $bot['ID'], rand(15000, 40000));

                    if ($i < $countBots) {
                        if ($sleep >= 1000000)
                            sleep($sleep / 1000000);
                        else
                            usleep($sleep);
                    }
                }

                self::increaseFundBot($gameId, count($arBots));
            }
        }
    }

    /** раз в 10 минут
     * @param int $gameId
     */
    private static function runReactive(int $gameId): void
    {
        $arBots = [];
        $arGame = GameService::getFields($gameId);

        if ($arGame['ID'] > 0) {
            $dbUsers = $GLOBALS['USER']->GetList($by = 'ID', $order = 'ASC', ['UF_BOT' => 1]);
            while ($arUser = $dbUsers->Fetch())
                $arBots[] = $arUser;

            if ($arGame['START_TIME'] - date('H') == 1) {
                $rating = GameService::getRating($gameId);
                $transactions = FundTransactionTable::getList(['filter' => [
                    'TYPE_ID' => GameService::FUND_INCREASE,
                    'USER_ID' => array_column($arBots, 'ID'),
                    'GAME_ID' => $gameId,
                    '>=TIMESTAMP' => (new DateTime)->setTime(0, 0, 0),
                    '<=TIMESTAMP' => (new DateTime)->setTime(23, 59, 59)
                ]])->fetchAll();

                $virtualFund = array_sum(array_column($transactions, 'SUM'));
                $realFund = $arGame['FUND'] - $virtualFund;

                if ($realFund - array_sum(array_column($rating, 'PRIZE')) <= 0) {
                    shuffle($arBots);
                    $arBots = array_slice($arBots, 0, 3, true);

                    foreach ($arBots as $bot)
                        self::playBot($gameId, $bot['ID'], rand(10000, 15000));

                    self::increaseFundBot($gameId, count($arBots));
                }
            }
        }
    }

    /**
     * @param int $gameId
     * @param int $botId
     * @param int $time
     */
    private static function playBot(int $gameId, int $botId, int $time): void
    {
        $transaction = FundTransactionTable::add([
            'USER_ID' => $botId,
            'GAME_ID' => $gameId,
            'SUM' => 1,
            'TYPE' => GameService::FUND_INCREASE,
        ]);

        if ($transaction->isSuccess()) {
            GameResultTable::add([
                'GAME_ID' => $gameId,
                'USER_ID' => $botId,
                'TIME' => $time,
                'TIMESTAMP' => new DateTime
            ]);
        }
    }

    /**
     * @param int $gameId
     * @param int $value
     */
    private static function increaseFundBot(int $gameId, int $value): void
    {
        OptionTable::update(
            ['MODULE_ID' => PlatformService::MODULE_ID, 'NAME' => 'GAME_FUND_' . $gameId],
            ['VALUE' => GameService::getFields($gameId)['CURRENT_GAME']['FUND'] + $value],
        );

        NotificationService::send('fundGame', ['value' => (int) $value]);
    }
}