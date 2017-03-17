<?php

namespace Test;

use Zend\Mvc\Application;
use Zend\Stdlib\Parameters;
use Zend\Uri\Http as HttpUri;
use Zend\Http\Request as HttpRequest;
use Zend\EventManager\StaticEventManager;
use Zend\Console\Console;

class ZFHttpClient
{
    protected $application;

    /**
     * If true rethrows application exceptions and errors (wrapped in an exception)
     *
     * @see Client::enableErrorTrace
     * @see Client::disableErrorTrace
     */
    protected $errorTrace = false;

    /**
     * If true adds debug information to output
     *
     * @see Client::enableDebug
     * @see Client::disableDebug
     */
    protected $debug = false;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    public function getApplication()
    {
        return $this->application;
    }

    public function disableErrorTrace()
    {
        $this->errorTrace = false;

        return $this;
    }

    public function enableErrorTrace()
    {
        $this->errorTrace = true;

        return $this;
    }

    public function enableDebug()
    {
        $this->debug = true;

        return $this;
    }

    public function disableDebug()
    {
        $this->debug = false;

        return $this;
    }

    public function getServiceManager()
    {
        return $this->application->getServiceManager();
    }

    protected function url($url, $method = HttpRequest::METHOD_GET, array $params = [])
    {
        $request = $this->application->getRequest();

        $query       = array();
        $uri         = new HttpUri($url);
        $queryString = $uri->getQuery();

        if ($queryString) {
            parse_str($queryString, $query);
        }

        if ($method === HttpRequest::METHOD_POST) {
            $request->setPost(new Parameters($params));
        }

        if ($method === HttpRequest::METHOD_PUT) {
            $request->setContent(http_build_query($params));
        }

        if ($method === HttpRequest::METHOD_GET) {
            $query = array_merge($query, $params);
        }

        $request->setMethod($method);
        $request->setQuery(new Parameters($query));
        $request->setUri($uri);

        return $this;
    }

    protected function dispatch($url, $method = HttpRequest::METHOD_GET, array $params = [])
    {
        if ($this->debug) {
            echo "calling $url\n";
        }

        $this->url($url, $method, $params);
        $this->application->run();

        if (!$this->errorTrace) {
            return;
        }

        $this->propagateErrors();
    }

    protected function propagateErrors()
    {
        $event = $this->application->getMvcEvent();

        if (!$event->isError()) {

            return;
        }

        if ($event->getParam('exception')) {

            throw $event->getParam('exception');
        }

        throw new \Exception($event->getError());
    }

    public function get($url)
    {
        $this->dispatch($url);

        return new ResponseDecorator($this->application->getResponse());
    }

    public function post($url, $body)
    {
        $this->dispatch($url, HttpRequest::METHOD_POST, $body);

        return new ResponseDecorator($this->application->getResponse());
    }

    public function put($url, $body)
    {
        $this->dispatch($url, HttpRequest::METHOD_PUT, $body);

        return new ResponseDecorator($this->application->getResponse());
    }

    public function delete($url)
    {
        $this->dispatch($url, HttpRequest::METHOD_DELETE);

        return new ResponseDecorator($this->application->getResponse());
    }

    public function options($url)
    {
        $this->dispatch($url, HttpRequest::METHOD_OPTIONS);

        return new ResponseDecorator($this->application->getResponse());
    }

    public function setJWTToken($token)
    {
        $this->application
            ->getRequest()
            ->getHeaders()
            ->clearHeaders()
            ->addHeaderLine('ORA-JWT', $token);
    }

    public function setHost($host)
    {
        $this->application
            ->getRequest()
            ->getServer()
            ->set('HTTP_HOST', $host);
    }

    public static function reset()
    {
        // reset server data
        $_SESSION = array();
        $_GET     = array();
        $_POST    = array();
        $_COOKIE  = array();

        // reset singletons
        StaticEventManager::resetInstance();
    }

    protected static function initApp($configFile)
    {
        $testConfig = include $configFile;

        Console::overrideIsConsole(false);
        $application = Application::init($testConfig);

        $events = $application->getEventManager();

        $events->detach($application->getServiceManager()->get('SendResponseListener'));

        return $application;
    }

    public static function create($configFile)
    {
        static::reset();

        $client = new static(static::initApp($configFile));

        return $client;
    }
}