<?php

namespace TaskManagement;

use TaskManagement\Controller\AcceptancesController;
use TaskManagement\Controller\ApprovalsController;
use TaskManagement\Controller\AttachmentsController;
use TaskManagement\Controller\Console\CleanEventsController;
use TaskManagement\Controller\Console\SendController;
use TaskManagement\Controller\Console\SharesRemindersController;
use TaskManagement\Controller\Console\SharesClosingController;
use TaskManagement\Controller\Console\ShiftOutWarningController;
use TaskManagement\Controller\EstimationsController;
use TaskManagement\Controller\MembersController;
use TaskManagement\Controller\OwnerController;
use TaskManagement\Controller\MemberStatsController;
use TaskManagement\Controller\PositionsController;
use TaskManagement\Controller\RemindersController;
use TaskManagement\Controller\Console\RemindersController as ConsoleRemindersController;
use TaskManagement\Controller\Console\VotingResultsController as ConsoleVotingsController;
use TaskManagement\Controller\SharesController;
use TaskManagement\Controller\StreamsController;
use TaskManagement\Controller\TasksController;
use TaskManagement\Controller\TransitionsController;
use TaskManagement\Controller\VotingResultsController;
use TaskManagement\Controller\HistoryController;
use TaskManagement\Processor\NotifyTaskRevertedToOpenProcessor;
use TaskManagement\Processor\RemoveMemberFromItemsProcessor;
use TaskManagement\Processor\RevertCreditsAssignedProcessor;
use TaskManagement\Processor\UpdateMembershipActivationProcessor;
use TaskManagement\Projector\TaskProjector;
use TaskManagement\Processor\UpdateItemPositionProcessor;
use TaskManagement\Service\AssignCreditsListener;
use TaskManagement\Service\CloseTaskListener;
use TaskManagement\Service\EventSourcingStreamService;
use TaskManagement\Service\EventSourcingTaskService;
use TaskManagement\Service\NotifyMailListener;
use TaskManagement\Service\StreamCommandsListener;
use TaskManagement\Service\TaskCommandsListener;
use TaskManagement\Service\TransferCreditsListener;
use TaskManagement\Service\CloseItemIdeaListener;
use TaskManagement\Service\AcceptCompletedItemListener;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Application\Service\EventProxyService;

class Module implements AutoloaderProviderInterface, ConfigProviderInterface
{
	public function getControllerConfig()
	{
		return [
			'factories' => [
				'TaskManagement\Controller\Tasks' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$streamService = $locator->get('TaskManagement\StreamService');
					$organizationService = $locator->get('People\OrganizationService');
					$kanbanizeService = $locator->get('Kanbanize\KanbanizeService');

					$controller = new TasksController(
						$taskService,
						$streamService,
						$organizationService,
						$kanbanizeService
					);

					return $controller;
				},
				'TaskManagement\Controller\Members' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$orgService = $locator->get('People\OrganizationService');
					$taskService = $locator->get('TaskManagement\TaskService');
					$userService = $locator->get('Application\UserService');
					$controller = new MembersController($orgService, $taskService, $userService);
					return $controller;
				},
				'TaskManagement\Controller\Owner' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$orgService = $locator->get('People\OrganizationService');
					$taskService = $locator->get('TaskManagement\TaskService');
					$userService = $locator->get('Application\UserService');
					$controller = new OwnerController($orgService, $taskService, $userService);
					return $controller;
				},
				'TaskManagement\Controller\Transitions' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$orgService = $locator->get('People\OrganizationService');

					$controller = new TransitionsController(
						$taskService,
						$orgService
					);

					return $controller;
				},
				'TaskManagement\Controller\Estimations' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$controller = new EstimationsController($taskService);
					return $controller;
				},
				'TaskManagement\Controller\Shares' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$controller = new SharesController($taskService);
					return $controller;
				},
				'TaskManagement\Controller\Streams' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$streamService = $locator->get('TaskManagement\StreamService');
					$organizationService = $locator->get('People\OrganizationService');
					$controller = new StreamsController($streamService, $organizationService);
					return $controller;
				},
				'TaskManagement\Controller\Reminders' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$notificationService = $locator->get('TaskManagement\NotifyMailListener');
					$taskService = $locator->get('TaskManagement\TaskService');
					$orgService = $locator->get('People\OrganizationService');

					$controller = new RemindersController(
						$notificationService,
						$taskService,
						$orgService,
                        $locator->get('Application\FrontendRouter')
                    );

					return $controller;
				},
				'TaskManagement\Controller\MemberStats' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$orgService = $locator->get('People\OrganizationService');
					$userService = $locator->get('Application\UserService');
					$taskService = $locator->get('TaskManagement\TaskService');
					$controller = new MemberStatsController($orgService, $taskService, $userService);
					return $controller;
				},
				'TaskManagement\Controller\VotingResults' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$orgService = $locator->get('People\OrganizationService');

					$controller = new VotingResultsController(
						$taskService,
						$orgService
					);

					return $controller;
				},
				'TaskManagement\Controller\Approvals' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$controller = new ApprovalsController($taskService);
					return $controller;
				},
				'TaskManagement\Controller\Attachments' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$controller = new AttachmentsController($taskService);
					return $controller;
				},
				'TaskManagement\Controller\Acceptances' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$controller = new AcceptancesController($taskService);
					return $controller;
				},
				'TaskManagement\Controller\Console\Reminders' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$orgService = $locator->get('People\OrganizationService');
					$mailService = $locator->get('AcMailer\Service\MailService');

					$controller = new ConsoleRemindersController(
						$taskService,
						$mailService,
						$orgService,
                        $locator->get('Application\FrontendRouter')
					);

					$config = $locator->get('Config');
					if(isset($config['mail_domain'])) {
						$controller->setHost($config['mail_domain']);
					}

					return $controller;
				},
				'TaskManagement\Controller\Console\VotingResults' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$orgService = $locator->get('People\OrganizationService');
					$userService = $locator->get('Application\UserService');

					$controller = new ConsoleVotingsController(
						$taskService,
						$orgService,
						$userService
					);

					return $controller;
				},
                'TaskManagement\Controller\Console\SharesReminders' => function ($sm) {
                    $locator = $sm->getServiceLocator();
                    $taskService = $locator->get('TaskManagement\TaskService');
                    $mailService = $locator->get('AcMailer\Service\MailService');
                    $orgService = $locator->get('People\OrganizationService');

                    $controller = new SharesRemindersController(
                        $taskService,
                        $mailService,
                        $orgService,
                        $locator->get('Application\FrontendRouter')
                    );

                    return $controller;
                },
                'TaskManagement\Controller\Console\SharesClosing' => function ($sm) {
                    $locator = $sm->getServiceLocator();
                    $taskService = $locator->get('TaskManagement\TaskService');
                    $orgService = $locator->get('People\OrganizationService');
                    $userService = $locator->get('Application\UserService');

                    $controller = new SharesClosingController(
                        $taskService,
                        $orgService,
                        $userService
                    );

                    return $controller;
                },
                'TaskManagement\Controller\Console\Send' => function ($sm) {
                    $locator = $sm->getServiceLocator();

                    return new SendController(
                        $locator->get('prooph.event_store'),
                        $locator->get(EventProxyService::class)
                    );
                },
                'TaskManagement\Controller\History' => function ($sm) {
					$locator = $sm->getServiceLocator();
					$taskService = $locator->get('TaskManagement\TaskService');
					$controller = new HistoryController($taskService);
					return $controller;
				},
                'TaskManagement\Controller\Console\ShiftOutWarning' => function ($sm) {
					$locator = $sm->getServiceLocator();
                    $orgService = $locator->get('People\OrganizationService');
                    $userService = $locator->get('Application\UserService');
                    $em = $locator->get('doctrine.entitymanager.orm_default');

					return new ShiftOutWarningController($orgService, $userService, $em);
				},
                'TaskManagement\Controller\Positions' => function ($sm) {
                    $locator = $sm->getServiceLocator();
                    $orgService = $locator->get('People\OrganizationService');
                    $taskService = $locator->get('TaskManagement\TaskService');
                    $streamService = $locator->get('TaskManagement\StreamService');

                    return new PositionsController($orgService, $streamService, $taskService);
                },
                'TaskManagement\Controller\Console\CleanEvents' => function($sm) {
                    $locator = $sm->getServiceLocator();
					$eventStore = $locator->get('prooph.event_store');
                    $entityManager = $locator->get('doctrine.entitymanager.orm_default');
                    return new CleanEventsController($eventStore, $entityManager);
                }
            ]
		];
	}

	public function getServiceConfig()
	{
		return [
			'factories' => [
				'TaskManagement\StreamService' => function ($locator) {
					$eventStore = $locator->get('prooph.event_store');
					$entityManager = $locator->get('doctrine.entitymanager.orm_default');
					return new EventSourcingStreamService($eventStore, $entityManager);
				},
				'TaskManagement\NotifyMailListener'=> function ($locator){
					$mailService = $locator->get('AcMailer\Service\MailService');
					$userService = $locator->get('Application\UserService');
					$taskService = $locator->get('TaskManagement\TaskService');
					$orgService = $locator->get('People\OrganizationService');

					$rv = new NotifyMailListener(
					    $mailService,
                        $userService,
                        $taskService,
                        $orgService,
                        $locator->get('Application\FrontendRouter')
                    );

					$config = $locator->get('Config');

					if(isset($config['mail_domain'])) {
						$rv->setHost($config['mail_domain']);
					}

					return $rv;
				},
                TaskManagement\Projector\TaskProjector::class => function($locator) {
                    $entityManager = $locator->get('doctrine.entitymanager.orm_default');

				    return new TaskProjector($entityManager);
                },
                TaskManagement\Processor\RevertCreditsAssignedProcessor::class => function($locator) {

				    return new RevertCreditsAssignedProcessor(
                        $locator->get('Accounting\CreditsAccountsService'),
                        $locator->get('People\OrganizationService'),
				        $locator->get('doctrine.entitymanager.orm_default')
                    );
                },
                TaskManagement\Processor\UpdateItemPositionProcessor::class => function($locator) {

				    return new UpdateItemPositionProcessor(
                        $locator->get('People\OrganizationService'),
                        $locator->get('Application\UserService'),
				        $locator->get('TaskManagement\TaskService'),
                        $locator->get('doctrine.entitymanager.orm_default'),
                        $locator->get('prooph.event_store')
                    );
                },
                TaskManagement\Processor\RemoveMemberFromItemsProcessor::class => function($locator) {
				    return new RemoveMemberFromItemsProcessor(
				        $locator->get('TaskManagement\TaskService'),
				        $locator->get('Application\UserService'),
				        $locator->get('People\OrganizationService'),
                        $locator->get('doctrine.entitymanager.orm_default'),
                        $locator->get('prooph.event_store')
                    );
                },
                TaskManagement\Processor\NotifyTaskRevertedToOpenProcessor::class => function($locator) {
				    return new NotifyTaskRevertedToOpenProcessor(
				        $locator->get('People\OrganizationService'),
				        $locator->get('TaskManagement\TaskService'),
                        $locator->get('AcMailer\Service\MailService'),
                        $locator->get('doctrine.entitymanager.orm_default'),
                        $locator->get('Application\FrontendRouter')
                    );
                },
				'TaskManagement\TaskService' => function ($locator) {
					$eventStore = $locator->get('prooph.event_store');
					$entityManager = $locator->get('doctrine.entitymanager.orm_default');

					return new EventSourcingTaskService($eventStore, $entityManager);

				},
				'TaskManagement\TaskCommandsListener' => function ($locator) {
					$entityManager = $locator->get('doctrine.entitymanager.orm_default');
					$kanbanizeService = $locator->get('Kanbanize\KanbanizeService');
					$orgService = $locator->get('People\OrganizationService');

					return new TaskCommandsListener(
					    $entityManager,
                        $kanbanizeService,
                        $orgService
                    );
				},
				'TaskManagement\StreamCommandsListener' => function ($locator) {
					$entityManager = $locator->get('doctrine.entitymanager.orm_default');
					return new StreamCommandsListener($entityManager);
				},
				'TaskManagement\TransferCreditsListener' => function ($locator) {
					$taskService = $locator->get('TaskManagement\TaskService');
					$transactionManager = $locator->get('prooph.event_store');
					$organizationService = $locator->get('People\OrganizationService');
					$accountService = $locator->get('Accounting\CreditsAccountsService');
					$userService = $locator->get('Application\UserService');
					return new TransferCreditsListener($taskService, $organizationService, $accountService, $userService, $transactionManager);
				},
				'TaskManagement\CloseTaskListener' => function ($locator) {
					$taskService = $locator->get('TaskManagement\TaskService');
					$userService = $locator->get('Application\UserService');
					$transactionManager = $locator->get('prooph.event_store');
					return new CloseTaskListener($taskService, $userService, $transactionManager);
				},
				'TaskManagement\CloseItemIdeaListener' => function ($locator) {
					$taskService = $locator->get('TaskManagement\TaskService');
					$organizationService = $locator->get('People\OrganizationService');
					$userService = $locator->get('Application\UserService');
					$transactionManager = $locator->get('prooph.event_store');
					return new CloseItemIdeaListener($taskService,$userService, $organizationService, $transactionManager);
				},
				'TaskManagement\AcceptCompletedItemListener' => function ($locator) {
					$taskService = $locator->get('TaskManagement\TaskService');
					$organizationService = $locator->get('People\OrganizationService');
					$userService = $locator->get('Application\UserService');
					$transactionManager = $locator->get('prooph.event_store');
					return new AcceptCompletedItemListener($taskService,$userService, $organizationService, $transactionManager);
				},
				'TaskManagement\AssignCreditsListener' => function ($locator) {
					$taskService = $locator->get('TaskManagement\TaskService');
					$userService = $locator->get('Application\UserService');
					$transactionManager = $locator->get('prooph.event_store');
					return new AssignCreditsListener($taskService, $userService, $transactionManager);
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
