<?php
use IntegrationTest\Bootstrap;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\TreeRouteStack as HttpRouter;
use Zend\Mvc\Router\RouteMatch;
use Zend\Uri\Http;

/**
 * Created by PhpStorm.
 * User: rad
 * Date: 08/03/17
 * Time: 19:54
 */
class BaseTaskProcessTest extends \PHPUnit_Framework_TestCase
{
    protected $controller;
    protected $event;
    protected $request;
    protected $response;
    protected $routeMatch;
    protected $serviceManager;
    protected $taskService;
    protected $streamService;
    protected $userService;
    protected $organizationService;
    protected $kanbanizeService;

    public function __construct()
    {
        $this->serviceManager = Bootstrap::getServiceManager();
        $this->userService = $this->serviceManager->get('Application\UserService');
        $this->taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $this->streamService = $this->serviceManager->get('TaskManagement\StreamService');
        $this->organizationService = $this->serviceManager->get('People\OrganizationService');
        $this->kanbanizeService = $this->serviceManager->get('Kanbanize\KanbanizeService');
    }

    protected function setupController($controller, $action)
    {
        $this->controller = $controller;

        $this->request	= new Request();

        $this->routeMatch = new RouteMatch(array('controller' => $action));
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
}