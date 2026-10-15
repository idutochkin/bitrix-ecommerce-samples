<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;

class westpower_fiji extends CModule
{
    /**
     * @var string
     */
    public $MODULE_ID;

    /**
     * @var string
     */
    public $MODULE_VERSION;

    /**
     * @var string
     */
    public $MODULE_VERSION_DATE;

    /**
     * @var string
     */
    public $MODULE_NAME;

    /**
     * @var string
     */
    public $MODULE_DESCRIPTION;

    /**
     * @var string
     */
    public $SHOW_SUPER_ADMIN_GROUP_RIGHTS;

    /**
     * @var string
     */
    public $MODULE_GROUP_RIGHTS;

    /**
     * @var string
     */
    public $PARTNER_NAME;

    /**
     * @var string
     */
    public $PARTNER_URI;

    /**
     * gazprom_provider constructor.
     */
    public function __construct()
    {
        $this->MODULE_ID = 'westpower.fiji';
        $this->MODULE_VERSION = '1.0.0';
        $this->MODULE_VERSION_DATE = '2024-09-12 11:43:30';
        $this->MODULE_NAME = 'Westpower - Фиджи';
        $this->MODULE_DESCRIPTION = 'Westpower - Фиджи';
        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
        $this->MODULE_GROUP_RIGHTS = 'Y';
        $this->PARTNER_NAME = 'Westpower';
        $this->PARTNER_URI = 'https://westpower.ru';
    }

    /**
     *
     */
    public function doInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallEvents();
        $this->InstallAgents();
        $this->InstallFiles();
    }

    /**
     *
     */
    public function doUnInstall(): void
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);

        $this->UnInstallEvents();
        $this->UnInstallAgents();
        $this->UnInstallFiles();
    }

    /**
     *
     */
    public function InstallEvents(): void
    {
        $eventManager = EventManager::getInstance();

        $eventManager->registerEventHandler(
            'sale',
            'OnSaleOrderPaid',
            $this->MODULE_ID,
            'Westpower\\Fiji\\Service\\OrderService',
            'eventPaid'
        );
    }

    /**
     *
     */
    public function UnInstallEvents(): void
    {
        $eventManager = EventManager::getInstance();

        $eventManager->unRegisterEventHandler(
            'sale',
            'OnSaleOrderPaid',
            $this->MODULE_ID,
            'Westpower\\Fiji\\Service\\OrderService',
            'eventPaid'
        );
    }

    /**
     *
     */
    public function InstallAgents(): void
    {
        CAgent::AddAgent(
            'Westpower\Fiji\Agent\OrderAgent::cashDayClosing();',
            $this->MODULE_ID,
            'N',
            3600,
            ConvertTimeStamp(strtotime(date('d.m.Y')) + 3600, 'FULL'),
            'Y',
            ConvertTimeStamp(strtotime(date('d.m.Y')) + 3600, 'FULL'),
            10,
            1
        );
        CAgent::AddAgent(
            'Westpower\Fiji\Agent\PagerAgent::callFromQueue();',
            $this->MODULE_ID,
            'N',
            60,
            ConvertTimeStamp(strtotime(date('d.m.Y')) + 60, 'FULL'),
            'Y',
            ConvertTimeStamp(strtotime(date('d.m.Y')) + 60, 'FULL'),
            10,
            1
        );
    }

    /**
     *
     */
    public function UnInstallAgents(): void
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);
    }

    /**
     *
     */
    function InstallFiles(): void
    {
        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/cron/',
            $_SERVER['DOCUMENT_ROOT'] . '/cron/'
        );
    }

    /**
     *
     */
    function UnInstallFiles(): void
    {
        DeleteDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/cron/',
            $_SERVER['DOCUMENT_ROOT'] . '/cron/'
        );
    }

    /**
     * @return string[][]
     */
    /*function GetModuleRightList(): array
    {
        return [
            'reference_id' => ['D', 'R', 'W'],
            'reference' => [
                    '[D] закрыт',
                    '[R] просмотр всех данных модуля',
                    '[W] запись'
                ]
        ];
    }*/
}