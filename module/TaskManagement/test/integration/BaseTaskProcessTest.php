<?php

use IntegrationTest\Bootstrap;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\TreeRouteStack as HttpRouter;
use Zend\Mvc\Router\RouteMatch;
use Zend\Uri\Http;

class BaseTaskProcessTest extends \PHPUnit_Framework_TestCase
{
    protected $controller;
    protected $event;
    protected $request;
    protected $response;
    protected $routeMatch;

    protected $serviceManager;
    protected $transactionManager;

    protected $taskService;
    protected $streamService;
    protected $userService;
    protected $organizationService;
    protected $kanbanizeService;

    public function __construct()
    {
        $this->serviceManager = Bootstrap::getServiceManager();
        $this->transactionManager = $this->serviceManager->get('prooph.event_store');

        $this->userService = $this->serviceManager->get('Application\UserService');
        $this->taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $this->streamService = $this->serviceManager->get('TaskManagement\StreamService');
        $this->organizationService = $this->serviceManager->get('People\OrganizationService');
        $this->kanbanizeService = $this->serviceManager->get('Kanbanize\KanbanizeService');
    }

    public function test()
    {
    }

    protected function setupController($controllerName, $actionName)
    {
        $this->controller = $this->serviceManager->get('ControllerManager')->get($controllerName);

        $this->request	= new Request();

        $this->routeMatch = new RouteMatch(array('controller' => $actionName));
        $this->event	  = new MvcEvent();
        $config = $this->serviceManager->get('Config');
        $routerConfig = isset($config['router']) ? $config['router'] : array();
        $router = $this->serviceManager->get('HttpRouter');
        $router->setRequestUri(new Http("http://example.com"));

        $this->event->setRouter($router);
        $this->event->setRouteMatch($this->routeMatch);
        $this->controller->setEvent($this->event);
        $this->controller->setServiceLocator($this->serviceManager);

        $pluginManager = $this->serviceManager->get('ControllerPluginManager');
        $this->controller->setPluginManager($pluginManager);
    }

    /**
     * @param array $data
     * @param $role
     * @return User
     */
    protected function createUser($data, $role)
    {
        return $this->userService->create($data, $role);
    }

    /**
     * @param string $name
     * @param \Application\Entity\User $user
     * @return Organization
     */
    protected function createOrganization($name, $admin)
    {
        return $this->organizationService->createOrganization($name, $admin);
    }

    protected function createStream($name, $organization, $admin, $serviceManager)
    {
        $stream = null;
        $streamService = $serviceManager->get('TaskManagement\StreamService');

        return $streamService->createStream($organization, $name, $admin);
    }

}