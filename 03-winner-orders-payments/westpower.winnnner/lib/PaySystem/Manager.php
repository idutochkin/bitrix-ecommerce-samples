<?
namespace Westpower\Winnnner\PaySystem;

use Westpower\Winnnner\Orm\PaySystemTable;
use Bitrix\Main\IO\Directory;
use Westpower\Winnnner\Service\PlatformService;

class Manager
{
    /**
     * @param $request
     * @return string
     */
    public static function searchByRequest($request): string
    {
        $paySystems = PaySystemTable::getList(['filter' => [
            'ACTIVE' => 'Y',
            'ID' => array_keys(array_filter(PlatformService::getFields()['PAY_SYSTEM_ACCESS'], fn ($item) =>
                in_array($request->getHttpHost(), $item['DOMAIN'])))
        ]])->fetchAll();

        foreach ($paySystems as $paySystem) {
            $className = '\Westpower\Winnnner\PaySystem\Handlers\\' . ucfirst($paySystem['HANDLER']);

            if ((new $className())->isMyResponse($request, $paySystem['ID']))
                return $className;
        }

        return '';
    }

    /**
     * @return array
     */
    public static function getHandlers(): array
    {
        $handlers = [];

        $files = (new Directory(__DIR__ . '/Handlers'))->getChildren();
        foreach ($files as $file) {
            if ($file instanceof Directory)
                continue;

            $name = explode('.', $file->getName())[0];
            if ($name === 'Base')
                continue;

            $handlers[] = [
                'CODE' => strtolower($name),
                'NAME' => $name
            ];
        }

        return $handlers;
    }
}