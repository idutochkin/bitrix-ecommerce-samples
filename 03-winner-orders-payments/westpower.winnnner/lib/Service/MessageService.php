<?
namespace Westpower\Winnnner\Service;

class MessageService
{
    /**
     * @param int $id
     * @param string $message
     * @param bool $byUserId
     */
    public static function send(int $id, string $message, bool $byUserId = true): void
    {
        TelegramService::sendMessage(
            $byUserId ? (int) $GLOBALS['USER']->GetByID($id)->Fetch()['UF_TELEGRAM_ID'] : $id,
            $message
        );
    }
}