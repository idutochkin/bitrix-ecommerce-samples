<?
namespace Westpower\Winnnner\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Context;
use CUser;
use CFile;
use Westpower\Winnnner\Orm\OptionTable;
use Bitrix\Main\Web\Uri;
use Bitrix\Main\Web\HttpClient;

class TelegramService
{
    const MESSAGE_TYPE_MESSAGE = 1;
    const MESSAGE_TYPE_GET_CONTACT = 2;
    const MESSAGE_TYPE_LOGIN = 3;

    /**
     * @return int
     */
    public static function auth(): int
    {
        $userId = 0;
        $request = Context::getCurrent()->getRequest();

        if (self::checkHash($request->getQueryList()->toArray())) {
            $user = $GLOBALS['USER']->GetList(($by = "ID"), ($order = "ASC"), ["UF_TELEGRAM_ID" => $request->get('id')], ["SELECT" => ["UF_*"]])->Fetch();

            if ($user) {
                if (!$user['PERSONAL_PHOTO'])
                    (new CUser)->Update($user["ID"], ['PERSONAL_PHOTO' => CFile::MakeFileArray($request->get('photo_url'))]);

                $userId = $user['ID'];
            }
        }

        return $userId;
    }

    /**
     * @return array
     */
    public static function getOptions(): array
    {
        $options = OptionTable::getList(['filter' => [
            'MODULE_ID' => PlatformService::MODULE_ID,
            '%NAME' => 'TELEGRAM'
        ]])->fetchAll();

        return array_column($options, 'VALUE', 'NAME');
    }

    /**
     * @param array $data
     * @return bool
     */
    private static function checkHash(array $data): bool
    {
        $telegramToken = self::getOptions()['TELEGRAM_TOKEN'];

        $arDataCheck = [];
        foreach($data as $key => $value)
            if($key != 'hash')
                $hash .= $key . '=' . $value . "\n";
        sort($arDataCheck);
        $arDataCheck = implode("\n", $arDataCheck);
        $secretKey = hash('sha256', $telegramToken, true);
        $hash = hash_hmac('sha256', $arDataCheck, $secretKey);

        return strcmp($hash, $data->hash) !== 0;
    }

    /**
     * @throws \Exception
     */
    public static function handlerMessage(): void
    {
        $request = Context::getCurrent()->getRequest();

        $secret = (string) Option::get(PlatformService::MODULE_ID, 'TELEGRAM_WEBHOOK_SECRET');
        $header = (string) $request->getHeader('X-Telegram-Bot-Api-Secret-Token');
        if ($secret === '' || !hash_equals($secret, $header))
            return;

        $input = $request->getInput();
        if (!$input)
            return;

        $input = Json::decode($input);

        $message = $input['message'];
        if (!$message)
            return;

        $fromId = $message['from']['id'];

        if (self::getByTelegramId($fromId))
            self::sendMessage($fromId, '', self::MESSAGE_TYPE_LOGIN);
        else {
            if ($message['text'] == '/start') {
                if ($fromId && !$message['from']['is_bot'])
                    self::sendMessage($fromId, '', self::MESSAGE_TYPE_GET_CONTACT);
            } elseif ($contact = $message['contact']) {
                if (
                    $fromId == $contact['user_id']
                    && $contact['user_id'] > 0
                    && !empty($contact['phone_number'])
                )
                    self::receiveContact($contact);
            }
        }
    }

    /**
     * @param $contact
     * @throws \Exception
     */
    private static function receiveContact($contact): void
    {
        $phone = str_replace(['+', '(', ')', '-', ' '], '', $contact['phone_number']);

        if ($userByPhone = $GLOBALS['USER']->GetByLogin($phone)->Fetch())
            self::updateTelegramId($userByPhone['ID'], $contact['user_id']);
        else {
            UserService::add([
                'PHONE' => $phone,
                'PASSWORD' => time(),
                'NAME' => $contact['first_name'],
                'LAST_NAME' => $contact['last_name'],
                'TELEGRAM_ID' => $contact['user_id'],
            ]);
        }

        self::sendMessage($contact['user_id'], 'Вы зарегистрированы.');
        self::sendMessage($contact['user_id'], '', self::MESSAGE_TYPE_LOGIN);
    }

    /**
     * @param int $userId
     * @param int $telegramId
     */
    private static function updateTelegramId(int $userId, int $telegramId): void
    {
        (new CUser)->Update($userId, ['UF_TELEGRAM_ID' => $telegramId]);
    }

    /**
     * @param int $id
     * @return array
     */
    private static function getByTelegramId(int $id): array
    {
        $arUser = $GLOBALS['USER']->GetList(($by = "ID"), ($order = "ASC"), ["UF_TELEGRAM_ID" => $id], ["SELECT" => ["UF_*"]])->Fetch();
        return $arUser ?: [];
    }

    /**
     * @param int $id
     * @param string $message
     * @param int $type
     */
    public static function sendMessage(int $id, string $message = '', int $type = self::MESSAGE_TYPE_MESSAGE): void
    {
        $buttons = '';

        switch ($type) {
            case self::MESSAGE_TYPE_LOGIN:
                $message = 'Добро пожаловать на игровую платформу Winnnner! Для продолжения авторизации нажмите кнопку Войти.';
                $buttons = '{"inline_keyboard":[[{"text":"Войти","login_url":{"url":"https://' . Context::getCurrent()->getRequest()->getHttpHost() . '/auth/"}}]]}';

                break;
            case self::MESSAGE_TYPE_GET_CONTACT:
                $message = 'Для продолжения регистрации на платформе Winnnner отправьте нам свой контакт.';
                $buttons = '{"keyboard":[[{"text":"Поделиться контактом","request_contact":true}]],"one_time_keyboard":true,"resize_keyboard":true}';
                
                break;
        }
        
        self::send($id, $message, $buttons);
    }

    /**
     * @param int $id
     * @param string $message
     * @param string $buttons
     * @return array
     */
    private static function send(int $id, string $message, string $buttons = ''): array
    {
        $params['chat_id'] = $id;
        $params['text'] = trim($message);
        $params['reply_markup'] = urlencode($buttons ?: '{"remove_keyboard":true}');

        $uri = new Uri('https://api.telegram.org/bot' . self::getOptions()['TELEGRAM_TOKEN'] . '/sendMessage?chat_id=' . $id . '&text=' . $params['text'] . '&reply_markup=' . $params['reply_markup']);

        $httpClient = new HttpClient();
        $httpClient->query('GET', $uri->getUri());

        return Json::decode($httpClient->getResult());
    }

    /**
     * @return array
     */
    public static function setWebhook(): array
    {
        $secret = bin2hex(random_bytes(32));
        Option::set(PlatformService::MODULE_ID, 'TELEGRAM_WEBHOOK_SECRET', $secret);

        $uri = new Uri('https://api.telegram.org/bot' . self::getOptions()['TELEGRAM_TOKEN'] . '/setWebhook');
        $uri->addParams([
            'url' => 'https://' . Context::getCurrent()->getServer()->getServerName() . '/bitrix/services/main/ajax.php?action=westpower%3Awinnnner.TelegramController.handlerMessage',
            'secret_token' => $secret,
        ]);

        $httpClient = new HttpClient();
        $httpClient->query('GET', $uri->getUri());

        return Json::decode($httpClient->getResult());
    }

    /**
     * @param string $name
     * @param string $token
     */
    public static function setBot(string $name, string $token): void
    {
        Option::set(PlatformService::MODULE_ID, 'TELEGRAM_BOT', $name);
        Option::set(PlatformService::MODULE_ID, 'TELEGRAM_TOKEN', $token);

        self::setWebhook();
    }
}