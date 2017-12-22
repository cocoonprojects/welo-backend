<?php

namespace ZFX\Test;

use Test\TestFixturesHelper;
use Test\ZFHttpClient;

class WebTestCase extends \PHPUnit_Framework_TestCase
{
    protected $client;

    protected $fixtures;

    public function setUp()
    {
        $config = getenv('APP_ROOT_DIR') . '/config/application.test.config.php';

        $this->client = ZFHttpClient::create($config);
        $this->client->enableErrorTrace();

        $this->fixtures = new TestFixturesHelper($this->client->getServiceManager());

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
    }
}