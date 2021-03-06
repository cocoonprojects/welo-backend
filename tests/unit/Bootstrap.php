<?php

namespace UnitTest;

use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use ZFX\Authentication\AuthenticationServiceMock;

error_reporting(E_ALL | E_STRICT);
chdir(dirname(__DIR__));

$path = __DIR__ . '/../../vendor/zendframework/zendframework/library';
putenv("ZF2_PATH=".$path);

include __DIR__ . '/../../init_autoloader.php';
if(isset($loader)) {
	$loader->set('Prooph\\EventStoreTest\\', __DIR__ . '/../../vendor/prooph/event-store/tests');
}

/**
 * Test bootstrap, for setting up autoloading
 */
class Bootstrap
{
	protected static $serviceManager;
	protected static $config;

	public static function init($config)
	{
		$serviceManager = new ServiceManager(new ServiceManagerConfig());
		$serviceManager->setService('ApplicationConfig', $config);
		$serviceManager->get('ModuleManager')->loadModules();
		static::$serviceManager = $serviceManager;
		static::$config = $config;
	}

	public static function getServiceManager()
	{
		return static::$serviceManager;
	}
	
	public static function getConfig()
	{
		return static::$config;
	}
}

Bootstrap::init(include __DIR__.'/../../config/application.config.php');
