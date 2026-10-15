<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\GameService;
use Westpower\Winnnner\Service\PlatformService;

class GameController extends Controller
{
    /**
     * @return array
     */
    public function configureActions(): array
    {
        return [
            'getRating' => [
                'prefilters' => []
            ]
        ];
    }

    /**
     * @param int $level
     * @return array
     */
    public function getLevelDataAction(int $level): array
    {
        $gameId = PlatformService::getCalendarGameId();

        return $this->convertKeysToCamelCase(GameService::getData($gameId, $level));
    }

    /**
     * @param int $level
     * @param int $answer
     * @return bool
     */
    public function checkAnswerAction(int $level, int $answer): bool
    {
        $gameId = PlatformService::getCalendarGameId();

        return $this->convertKeysToCamelCase(GameService::checkAnswer($gameId, $level, $answer));
    }

    /**
     * @param int $training
     * @return int
     */
    public function saveResultAction(int $training): int
    {
        $gameId = PlatformService::getCalendarGameId();
        $userId = $GLOBALS['USER']->GetID();

        return GameService::saveResult($gameId, $userId, (bool) $training);
    }

    /**
     * @return bool
     */
    public function startAction(): bool
    {
        $userId = $GLOBALS['USER']->GetID();

        return GameService::start($userId);
    }

    /**
     * @return array
     */
    public function getRatingAction(): array
    {
        $gameId = PlatformService::getCalendarGameId();

        return $this->convertKeysToCamelCase(GameService::getRating($gameId));
    }

    /**
     * @return array
     */
    public function getResultAction(): array
    {
        $userId = $GLOBALS['USER']->GetID();

        return $this->convertKeysToCamelCase(GameService::getResult($userId));
    }

    /**
     * @return array
     */
    public function getHistoryAction(): array
    {
        $userId = $GLOBALS['USER']->GetID();

        return $this->convertKeysToCamelCase(GameService::getHistory($userId));
    }
}