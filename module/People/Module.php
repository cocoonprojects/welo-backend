<?php
namespace People;

use People\Controller\HistoryController;
use People\Controller\LanesSettingsController;
use People\Controller\MembersController;
use People\Controller\OrganizationsController;
use People\Controller\InvitesController;
use People\Controller\AcceptInviteController;
use People\Projector\OrganizationProjector;
use People\Service\EventSourcingOrganizationService;
use People\Service\OrganizationCommandsListener;
use People\Service\SendMailListener;

class Module
{
	public function getControllerConfig()
	{
		return [
			'factories' => [
				'People\Controller\Invites' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$orgService = $locator->get('People\OrganizationService');
					$mailService = $locator->get('AcMailer\Service\MailService');

					$controller = new InvitesController($orgService, $mailService);

					$config = $locator->get('Config');
					if(isset($config['mail_domain'])) {
						$controller->setHost($config['mail_domain']);
					}

					return $controller;
				},
				'People\Controller\AcceptInvite' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$orgService = $locator->get('People\OrganizationService');

					$controller = new AcceptInviteController($orgService);

					return $controller;
				},
				'People\Controller\Organizations' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$orgService = $locator->get('People\OrganizationService');
					$controller = new OrganizationsController($orgService);
					return $controller;
				},
				'People\Controller\Members' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$orgService = $locator->get('People\OrganizationService');
					$userService = $locator->get('Application\UserService');

					$controller = new MembersController($orgService, $userService);

					return $controller;
				},
				'People\Controller\History' => function ($sm) {
					$locator = $sm->getServiceLocator();
                    $entityManager = $locator->get('doctrine.entitymanager.orm_default');
					$controller = new HistoryController($entityManager);

					return $controller;
				},
                'People\Controller\LanesSettings' => function($sm) {

		            $locator = $sm->getServiceLocator();
                    $orgService = $locator->get('People\OrganizationService');
                    $taskService = $locator->get('TaskManagement\TaskService');

                    return new LanesSettingsController($orgService, $taskService);
                }
			]
		];
	}

	public function getServiceConfig()
	{
		return [
			'factories' => [
                OrganizationProjector::class => function($locator) {
                    $entityManager = $locator->get('doctrine.entitymanager.orm_default');

                    return new OrganizationProjector($entityManager);
                },
				'People\OrganizationService' => function ($serviceLocator) {
					$eventStore = $serviceLocator->get('prooph.event_store');
					$entityManager = $serviceLocator->get('doctrine.entitymanager.orm_default');
					return new EventSourcingOrganizationService($eventStore, $entityManager);
				},
				'People\OrganizationCommandsListener' => function ($serviceLocator) {
					$entityManager = $serviceLocator->get('doctrine.entitymanager.orm_default');
					return new OrganizationCommandsListener($entityManager);
				},
				'People\SendMailListener' => function ($serviceLocator) {
					$mailService = $serviceLocator->get('AcMailer\Service\MailService');
					$userService = $serviceLocator->get('Application\UserService');
					$organizationService = $serviceLocator->get('People\OrganizationService');

					return new SendMailListener(
					    $mailService,
                        $userService,
                        $organizationService,
                        $serviceLocator->get('Application\FrontendRouter')
                    );
				}
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