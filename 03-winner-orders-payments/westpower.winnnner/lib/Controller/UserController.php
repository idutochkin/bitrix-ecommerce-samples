<?
namespace Westpower\Winnnner\Controller;

use Bitrix\Main\Engine\Controller;
use Westpower\Winnnner\Service\TelegramService;
use Westpower\Winnnner\Service\UserService;

class UserController extends Controller
{
    /**
     * @return array
     */
    public function configureActions(): array
    {
        return [
            'checkAuth' => [
                'prefilters' => []
            ],
            'telegramGetBotName' => [
                'prefilters' => []
            ],
        ];
    }

    /**
     * @return string
     */
    public function telegramGetBotNameAction(): string
    {
        $options = TelegramService::getOptions();

        return $this->convertKeysToCamelCase($options['TELEGRAM_BOT']);
    }

    /**
     * @return bool
     */
    public function checkAuthAction(): bool
    {
        return $this->convertKeysToCamelCase(UserService::checkAuth());
    }

    /**
     *
     */
    public function logoutAction(): void
    {
        UserService::logout();
    }

    /**
     * @return array
     */
    public function getGiftsAction(): array
    {
        $userId = $GLOBALS['USER']->GetID();

        return $this->convertKeysToCamelCase(UserService::getGifts($userId));
    }

    /**
     * @return array
     */
    public function getProfileAction(): array
    {
        return $this->convertKeysToCamelCase(UserService::getAccessFieldsCurrentUser());
    }

    /**
     * @param array $fields
     * @return array
     */
    public function saveProfileAction(array $fields): array
    {
        $userId = $GLOBALS['USER']->GetID();

        return $this->convertKeysToCamelCase(UserService::saveProfile($userId, $fields));
    }

    /**
     * @return array
     */
    public function getReferralAction(): array
    {
        $userId = $GLOBALS['USER']->GetID();

        return $this->convertKeysToCamelCase(UserService::getReferral($userId));
    }

    /**
     * @param array $ids
     */
    public function blockUserAction(array $ids): void
    {
        foreach ($ids as $id) {
            UserService::blockUser($id);
        }
    }

    /**
     * @param array $ids
     */
    public function unBlockUserAction(array $ids): void
    {
        foreach ($ids as $id) {
            UserService::unBlockUser($id);
        }
    }

    /**
     * @param int $id
     * @return string
     */
    public function showProfileAction(int $id): string
    {
        $html = '';
        $params = [
            'NAME' => 'Имя',
            'EMAIL' => 'Email',
        ];
        $fields = UserService::getFields($id);

        foreach ($params as $code => $value)
            $html .= '<div><b>' . $value . '</b>: ' . $fields[$code] . '</div>';

        return $html;
    }
}