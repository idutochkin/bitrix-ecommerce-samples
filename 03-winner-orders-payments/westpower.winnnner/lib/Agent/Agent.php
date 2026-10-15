<?
namespace Westpower\Winnnner\Agent;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Engine\Response\Converter;
use Westpower\Winnnner\Orm\FundTransactionTable;
use Westpower\Winnnner\Orm\GameTable;
use Westpower\Winnnner\Orm\OptionTable;
use Westpower\Winnnner\Service\GameService;
use Westpower\Winnnner\Service\MessageService;
use Westpower\Winnnner\Service\NotificationService;
use Westpower\Winnnner\Service\PlatformService;

class Agent
{
    public const DISTRIBUTION_FREQ = 10;
    /**
     * @return string
     */
    public static function pushCountUsersOnline(): string
    {
        NotificationService::send('usersOnline', ['count' => GameService::getCountUsersOnline()]);
        return __METHOD__ . '();';
    }

    /**
     * @return string
     */
    public static function distributionGameFund(): string
    {
        $currentHour = (new DateTime)->format('H');
        $games = GameTable::getList(['filter' => ['ACTIVE' => 'Y', 'STOP_TIME' => $currentHour]])->fetchAll();

        if ($games) {
            $platformFields = PlatformService::getFields();
            $distributionTime = (new DateTime(date('d.m.Y ' . $currentHour . ':00:00')))->add(((int) $platformFields['INCREASE_FUND_TIME_AFTER_GAME']) . ' minutes');

            if (
                (new DateTime)->add('-' . self::DISTRIBUTION_FREQ . ' minutes') <= $distributionTime
                && (new DateTime)->add(self::DISTRIBUTION_FREQ . ' minutes') >= $distributionTime
            ) {
                $bots = [];
                $dbBots = $GLOBALS['USER']->GetList($by = 'ID', $order = 'ASC', ['UF_BOT' => 1]);
                while ($bot = $dbBots->Fetch())
                    $bots[] = $bot['ID'];

                foreach ($games as $game) {
                    $rating = GameService::getRating($game['ID']);
                    if ($rating['ERROR'])
                        continue;

                    foreach ($rating as $rate) {
                        if ($rate['PRIZE'] <= 0 || in_array($rate['USER_ID'], $bots))
                            continue;

                        GameService::increaseGain($game['ID'], $rate['USER_ID'], $rate['PRIZE'], $rate['RATE']);
                    }

                    FundTransactionTable::add([
                        'USER_ID' => 0,
                        'GAME_ID' => $game['ID'],
                        'SUM' => $game['FUND'],
                        'TYPE' => GameService::FUND_INCREASE,
                    ]);

                    OptionTable::update(
                        ['MODULE_ID' => PlatformService::MODULE_ID, 'NAME' => 'GAME_FUND_' . $game['ID']],
                        ['VALUE' => $game['FUND']],
                    );
                }

                if ($platformFields['DECREASE_JETONS_BEFORE_GAME_ENDS'] == 'Y')
                    Application::getConnection()->query('UPDATE `b_uts_user` SET `UF_BALANCE_JETON` = 0');

                NotificationService::send('reload');
            }
        }

        return __METHOD__ . '();';
    }

    /**
     * @return string
     */
    public static function notifierClearJeton(): string
    {
        $platformFields = PlatformService::getFields();

        if ($platformFields['DECREASE_JETONS_BEFORE_GAME_ENDS'] == 'Y') {
            $currentHour = (new DateTime)->add('+1 hour')->format('H');
            $games = GameTable::getList(['filter' => ['ACTIVE' => 'Y', 'STOP_TIME' => $currentHour]])->fetchAll();

            if ($games) {
                $freq = 3;
                $notifyTime = (new DateTime(date('d.m.Y ' . $currentHour . ':00:00')))->add('-' . ((int) $platformFields['NOTIFY_DECREASE_JETONS_TIME_BEFORE_GAME_ENDS']) . ' minutes');

                if (
                    (new DateTime)->add('-' . $freq . ' minutes') < $notifyTime
                    && (new DateTime)->add($freq . ' minutes') > $notifyTime
                ) {
                    NotificationService::send('notify', [
                        'title' => 'Уведомление',
                        'body' => $platformFields['NOTIFY_DECREASE_JETONS_MESSAGE'],
                    ]);

                    $dbActiveUsers = $GLOBALS['USER']->GetList($by = 'ID', $order = 'ASC', ['!UF_BOT' => 1, '>UF_BALANCE_JETON' => 0], ['SELECT' => ['UF_TELEGRAM_ID']]);
                    while ($user = $dbActiveUsers->Fetch())
                        MessageService::send($user['UF_TELEGRAM_ID'], $platformFields['NOTIFY_DECREASE_JETONS_MESSAGE'], false);
                }
            }
        }

        return __METHOD__ . '();';
    }

    /**
     * @return string
     */
    public static function syncTimer(): string
    {
        NotificationService::send(
            'syncTimer',
            (new Converter(Converter::OUTPUT_JSON_FORMAT))->process(GameService::getFields(0, true))
        );

        return __METHOD__ . '();';
    }
}