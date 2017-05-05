<?php

namespace FlowManagement\Service;

use Application\Service\UserService;
use Doctrine\ORM\EntityManager;
use FlowManagement\Entity\FlowCard;
use FlowManagement\Entity\WelcomeCard;
use People\OrganizationMemberAdded;
use People\Service\OrganizationService;
use Prooph\EventStore\EventStore;
use Rhumsaa\Uuid\Uuid;
use TaskManagement\TaskArchived;
use People\OrganizationMemberRoleChanged;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Application;
use TaskManagement\Service\TaskService;
use People\Entity\OrganizationMembership;

class OrganizationCommandsListener implements ListenerAggregateInterface {
	
	protected $listeners = [];
	/**
	 * @var FlowService
	 */
	private $flowService;
	/**
	 * @var OrganizationService
	 */
	private $organizationService;
	/**
	 * @var UserService
	 */
	private $userService;
	/**
	 * @var EventStore
	 */
	private $transactionManager;
	/**
	 * @var TaskService
	 */
	private $taskService;

    private $entityManager;

    public function __construct(FlowService $flowService,
			OrganizationService $organizationService, 
			UserService $userService, 
			EventStore $transactionManager,
			TaskService $taskService,
            EntityManager $entityManager){
		$this->flowService = $flowService;
		$this->organizationService = $organizationService;
		$this->userService = $userService;
		$this->transactionManager = $transactionManager;
		$this->taskService = $taskService;
		$this->entityManager = $entityManager;
	}
	
	public function attach(EventManagerInterface $events) {
        $this->listeners[] = $events->getSharedManager()->attach(Application::class, OrganizationMemberRoleChanged::class, array($this, 'processOrganizationMemberRoleChange'));
        $this->listeners[] = $events->getSharedManager()->attach(Application::class, OrganizationMemberAdded::class, array($this, 'processMemberAdded'));
    }
	
	public function processOrganizationMemberRoleChange(Event $event){

		$streamEvent = $event->getTarget();
		$organization = $this->organizationService->findOrganization($event->getParam('organizationId'));
		$orgMemberships = $this->organizationService->findOrganizationMemberships($organization, null, null);
		$changedBy = $this->userService->findUser($event->getParam('by'));
		$changedUser = $this->userService->findUser($event->getParam('userId'));
		$oldRole = $event->getParam('oldRole');
		$newRole = $event->getParam('newRole');
		$params = [$this->flowService, $organization, $changedBy, $changedUser, $oldRole, $newRole];

		array_walk($orgMemberships, function($member) use($params){
			$flowService = $params[0];
			$organization = $params[1];
			$changedBy = $params[2];
			$changedUser = $params[3];
			$oldRole = $params[4];
			$newRole = $params[5];
			$flowService->createOrganizationMemberRoleChangedCard($member->getMember(), $changedUser, $organization->getId(), $oldRole, $newRole, $changedBy);
		});
	}

    public function processMemberAdded(Event $event) {
        $streamEvent = $event->getTarget();
        $organizationId = $streamEvent->metadata()['aggregate_id'];

        $organization = $this->organizationService->getOrganization($organizationId);
        $organizationReadModel = $this->organizationService->findOrganization($organizationId);

        $userId = $event->getParam ( 'userId' );

        $user = $this->userService->findUser($userId);

        $welcomeText = $organization->getParams()->get('flow_welcome_card_text');

        $flowCard = WelcomeCard::create(Uuid::uuid4(), $organizationReadModel, $user, $welcomeText);

        $this->entityManager->persist($flowCard);
        $this->entityManager->flush();
    }

    public function detach(EventManagerInterface $events){
		foreach ( $this->listeners as $index => $listener ) {
			if ($events->detach ( $listener )) {
				unset ( $this->listeners [$index] );
			}
		}
	}
}