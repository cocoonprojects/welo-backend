<?php
namespace FlowManagement;

use FlowManagement\Controller\CardsController;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use FlowManagement\Service\EventSourcingFlowService;
use FlowManagement\Service\CardCommandsListener;
use FlowManagement\Service\ItemCommandsListener;
use FlowManagement\Service\OrganizationCommandsListener;
use FlowManagement\Service\CreditsTransferNotifiedViaFlowCardListener;


class Module implements AutoloaderProviderInterface, ConfigProviderInterface{

	public function getControllerConfig() {
		return [
			'invokables' => [],
			'factories' => [
				'FlowManagement\Controller\Cards' => function ($sm) {

		            $locator = $sm->getServiceLocator();

					$controller = new CardsController(
                        $locator->get('FlowManagement\FlowService'),
                        $locator->get('Application\FrontendRouter')
                    );

					return $controller;
				},
			]
		];
	}

	public function getServiceConfig(){
		return [
				'factories' => [
						'FlowManagement\FlowService' => function ($locator) {
							$eventStore = $locator->get('prooph.event_store');
							$entityManager = $locator->get('doctrine.entitymanager.orm_default');
							return new EventSourcingFlowService($eventStore, $entityManager);
						},
						'FlowManagement\CardCommandsListener' => function ($locator) {
							$entityManager = $locator->get('doctrine.entitymanager.orm_default');
							return new CardCommandsListener($entityManager);
						},
						'FlowManagement\CreditsTransferNotifiedViaFlowCardListener' => function ($locator) {

							return new CreditsTransferNotifiedViaFlowCardListener(
                                $locator->get('Application\UserService'),
                                $locator->get('Accounting\CreditsAccountsService'),
                                $locator->get('doctrine.entitymanager.orm_default')
                            );
						},
						'FlowManagement\ItemCommandsListener' => function ($locator) {
							$flowService = $locator->get('FlowManagement\FlowService');
							$organizationService = $locator->get('People\OrganizationService');
							$userService = $locator->get('Application\UserService');
							$transactionManager = $locator->get('prooph.event_store');
							$taskService = $locator->get('TaskManagement\TaskService');
                            $entityManager = $locator->get('doctrine.entitymanager.orm_default');

                            return new ItemCommandsListener(
							    $flowService,
                                $organizationService,
                                $userService,
                                $transactionManager,
                                $taskService,
                                $entityManager
                            );
						},
						'FlowManagement\OrganizationCommandsListener' => function ($locator) {
							$flowService = $locator->get('FlowManagement\FlowService');
							$organizationService = $locator->get('People\OrganizationService');
							$userService = $locator->get('Application\UserService');
							$transactionManager = $locator->get('prooph.event_store');
							$taskService = $locator->get('TaskManagement\TaskService');
							return new OrganizationCommandsListener($flowService, $organizationService, $userService, $transactionManager, $taskService);
						},
				],
		];
	}

	public function getConfig()
	{
		return include __DIR__ . '/config/module.config.php';
	}

	public function getAutoloaderConfig()
	{		
		return array(
			'Zend\Loader\StandardAutoloader' => array(
				'namespaces' => array(
					__NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
				),
			),
		);
	}
}