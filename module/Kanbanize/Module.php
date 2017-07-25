<?php
namespace Kanbanize;

use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Kanbanize\Controller\OrgSettingsController;
use Kanbanize\Controller\BoardsController;
use Kanbanize\Controller\SettingsController;
use Kanbanize\Controller\StatsController;
use Kanbanize\Controller\SyncController;
use Kanbanize\Controller\Console\KanbanizeToOraSyncController;
use Kanbanize\Service\KanbanizeAPI;
use Kanbanize\Service\KanbanizeServiceImpl;
use Kanbanize\Service\TaskCommandsListener;
use Kanbanize\Service\MailNotificationService;

class Module implements AutoloaderProviderInterface, ConfigProviderInterface
{
	public function getControllerConfig()
	{
		return array(
			'invokables' => array(
			),
			'factories' => array(
				'Kanbanize\Controller\Console\KanbanizeToOraSync' => function($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$orgService = $locator->get('People\OrganizationService');
					$userService = $locator->get('Application\UserService');
					$kanbanizeService = $locator->get('Kanbanize\KanbanizeService');
					$notificationService = $locator->get('Kanbanize\MailNotificationService');

					$controller = new KanbanizeToOraSyncController(
						$taskService,
						$orgService,
						$userService,
						$kanbanizeService,
						$notificationService
					);

					return $controller;
				},
				'Kanbanize\Controller\Settings' => function($sm){
					$locator = $sm->getServiceLocator();
					$organizationService = $locator->get('People\OrganizationService');
					$client = $locator->get('Kanbanize\KanbanizeAPI');
					$kanbanizeService = $locator->get('Kanbanize\KanbanizeService');

					$controller = new SettingsController(
						$organizationService,
						$client,
						$kanbanizeService
					);

					return $controller;
				},
				'Kanbanize\Controller\OrgSettings' => function($sm){
					$locator = $sm->getServiceLocator();
					$organizationService = $locator->get('People\OrganizationService');
					$client = $locator->get('Kanbanize\KanbanizeAPI');
					$controller = new OrgSettingsController($organizationService, $client);

					return $controller;
				},
				'Kanbanize\Controller\Boards' => function($sm){
					$locator = $sm->getServiceLocator();
					$organizationService = $locator->get('People\OrganizationService');
					$streamService = $locator->get('TaskManagement\StreamService');
					$client = $locator->get('Kanbanize\KanbanizeAPI');
					$kanbanizeService = $locator->get('Kanbanize\KanbanizeService');

					$controller = new BoardsController(
					    $organizationService,
                        $streamService,
                        $client,
                        $kanbanizeService
                    );

					return $controller;
				},
				'Kanbanize\Controller\Stats' => function($sm){
					$em = $sm->getServiceLocator()
							 ->get('doctrine.entitymanager.orm_default');

					return new StatsController($em);
				}
			)
		);
	}

	public function getServiceConfig()
	{
		return array (
			'invokables' => array(
			),
			'factories' => array (
				'Kanbanize\KanbanizeService' => function ($locator) {
					$entityManager = $locator->get('doctrine.entitymanager.orm_default');
					$api = $locator->get('Kanbanize\KanbanizeAPI');

					return new KanbanizeServiceImpl($entityManager, $api);
				},
				'Kanbanize\TaskCommandsListener' => function ($locator) {
					$entityManager = $locator->get('doctrine.entitymanager.orm_default');
					$taskService = $locator->get('TaskManagement\TaskService');

					return new TaskCommandsListener($entityManager, $taskService);
				},
				'Kanbanize\KanbanizeAPI' => function ($locator) {

				    return new KanbanizeAPI();
				},
				'Kanbanize\MailNotificationService'=> function ($locator){
					$mailService = $locator->get('AcMailer\Service\MailService');
					$orgService = $locator->get('People\OrganizationService');
					$rv = new MailNotificationService($mailService, $orgService);

					return $rv;
				},
			),
			'initializers' => array(
			)
		);
	}

	public function getConfig()
	{
		return include __DIR__ . '/config/module.config.php';
	}

	public function getAutoloaderConfig()
	{
		return array(
			'Zend\Loader\ClassMapAutoloader' => array(
				__DIR__ . '/autoload_classmap.php',
			),
			'Zend\Loader\StandardAutoloader' => array(
				'namespaces' => array(
					__NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
				)
			)
		);
	}
}