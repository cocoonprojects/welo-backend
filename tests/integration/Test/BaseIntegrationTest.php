<?php

namespace IntegrationTest;

use Zend\Http\Request;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use Zend\Uri\Http;
use ZFX\Test\Authentication\AdapterMock;
use ZFX\Test\Authentication\OAuth2AdapterMock;

class BaseIntegrationTest extends \PHPUnit_Framework_TestCase
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
    protected $authService;

    public function __construct()
    {
        $this->serviceManager = Bootstrap::getServiceManager();
        $this->transactionManager = $this->serviceManager->get('prooph.event_store');

        $this->authService = $this->serviceManager->get('Zend\Authentication\AuthenticationService');
        $this->userService = $this->serviceManager->get('Application\UserService');
        $this->taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $this->streamService = $this->serviceManager->get('TaskManagement\StreamService');
        $this->organizationService = $this->serviceManager->get('People\OrganizationService');
        $this->kanbanizeService = $this->serviceManager->get('Kanbanize\KanbanizeService');
    }

    public function test()
    {
    }

    protected function setupAuthenticatedUser($email)
    {
        $adapter = new AdapterMock();
        $adapter->setEmail($email);
        $this->authService->authenticate($adapter);
    }

    protected function setupController($controllerName, $route)
    {
        $this->controller = $this->serviceManager->get('ControllerManager')->get($controllerName);

        $this->request    = new Request();

        $this->routeMatch = new RouteMatch(array('controller' => $route));
        $this->event      = new MvcEvent();
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
        return $this->userService->subscribeUser($data, $role);
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

    /**
     * @param string $name
     * @param \Application\Entity\Organization
     * @param \Application\Entity\User $user
     * @return Stream
     */
    protected function createStream($name, $organization, $admin)
    {
        return $this->streamService->createStream($organization, $name, $admin);
    }
}
