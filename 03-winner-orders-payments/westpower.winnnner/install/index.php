<?
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Westpower\Winnnner\PullSchema;

class westpower_winnnner extends CModule
{
    var $MODULE_ID = 'westpower.winnnner';
    var $MODULE_VERSION = "1.0.0";
    var $MODULE_VERSION_DATE = "2020-12-04";
    var $MODULE_NAME = "Westpower Winnnner";
    var $MODULE_DESCRIPTION = 'Игровая платформа';
    var $PARTNER_NAME = "Westpower";
    var $PARTNER_URI = 'https://westpower.ru';
    var $errors;

    function __construct()
    {
        $this->MODULE_ID = str_replace("_", ".", get_class($this));
    }

    function DoInstall()
    {
        $this->registerEvents();		
        ModuleManager::RegisterModule($this->MODULE_ID);
		
        return true;
    }

    function DoUninstall()
    {
        $this->unRegisterEvents();		
        ModuleManager::UnRegisterModule($this->MODULE_ID);
		
        return true;
    }

    function registerEvents()
    {
        $eventManager = EventManager::getInstance();		
		$eventManager->registerEventHandler("pull", "OnGetDependentModule", $this->MODULE_ID, PullSchema::class, "OnGetDependentModule");
        $eventManager->registerEventHandler("main", "OnProlog", $this->MODULE_ID, PullSchema::class, "setPullUserId");
		
        return true;
    }    
    
    function unRegisterEvents()
    {
        $eventManager = EventManager::getInstance();		
		$eventManager->unRegisterEventHandler("pull", "OnGetDependentModule", $this->MODULE_ID);
        $eventManager->unRegisterEventHandler("main", "OnProlog", $this->MODULE_ID);
		
        return true;
    }
}