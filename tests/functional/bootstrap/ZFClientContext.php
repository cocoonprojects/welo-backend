<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Testwork\Hook\Scope\AfterSuiteScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Gherkin\Node\PyStringNode;
use People\DTO\LaneData;
use Rhumsaa\Uuid\Uuid;
use Test\ZFHttpClient;
use PHPUnit_Framework_Assert as Assert;
use Test\TestFixturesHelper;

class ZFClientContext implements Context
{
    private $_client;

    private $_fixtureHelper;

    private $_restObject;
    private $_restObjectType;
    private $_restObjectMethod = 'get';
    private $_response;
    private $_requestUrl;

    private $json;
    private $jsonProperties;


    private static $tokens = [
        'mark.rogers@ora.local' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiI2MDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgyOTU1In0.rqGFFeOf5VdxO_qpz_fkwFJtgJH4Q5Kg6WUFGA_L1tMB-yyZj7bH3CppxxxvpekQzJ7y6aH6I7skxDh1K1Cayn3OpyaXHyG9V_tlgo08TKR7EK0TsBA0vWWiT7Oito97ircrw_4N4ZZFmF6srpNHda2uw775-7SpQ8fdI0_0LOn1IwF1MKvJIuZ9J7bR7PZsdyqLQSpNm8P5gJiA0c6i_uubtVEljVvr1H1mSoq6hViS9A2M-v4THlbH_Wki2pYp00-ggUu6dm25NeX300Q6x2RBHVY_bXpw7voRbXI1VAg_LxXDjv61l4lar6dOhK3qbsXm9P2JTEqyG7bYSAqtLA',
        'phil.toledo@ora.local' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiI3MDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgzMDYzIn0.etOL9ozjnNni8-cu3dF4RO1rcQhmUkJ3fOzBTEWK4IIJjaVhjdYwTX_FFiWG_pKNPAI0EItijRxAG4zh66zHV-6ERnTAD7VA6V7Si_LA8vAS3gIsB1XsrkJ2Xjrj8ax7HtzM5UVhHwEXDZGXJQ3XEZX0tXO-jUvvizZ5qwFSAopSpydcTjwQmMDdr_stGuGJ5qq03sEN4Z5iWugsJoVBSf389KlIfXqlvTnVy2tojDh4ba7sWhh-O9IkxCMtJrUckn2_iI1TS-3Z1iavVh8ebTwbVx41QAjAR_I_CerINNIeewRoVGu3R2gYdVvf4PphaUXZLS7sN3KaldvTVB59jA',
        'paul.smith@ora.local'  => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiIyMDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgzMjQzIn0.WTKW0CBmHlHIfBmtzTeakDUlX0p775w59bT1FKN2TIYcJ3nBEF_hmY0s3eEKZ6dOs4PjxyskVRYiB5dlbG1ZSYRbOJGysn5lvltXBmhOk2Ad3RiI8rina-Af0eBXS96A2BY2Qc2NN5t3EcjmIateH_dgG85adewQSZVJTTKKUBid46fdZ0TO5Y1jcr153xxMuE66W9gMGP2ffUGJIt01UQeuljQM1OF8Ss87l9tIcgRrKd5NiU5ap6JY4nTiZYgh8d7LPd4NfZ34GdQjt0vM0J9q_pQ9dN2GzuF9MO09TjRfmMNuE-fHboye4ahTaHH2OcUFDEMOF6XWy8tw8t7E3A',
        'bruce.wayne@ora.local' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiI4MDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgzMzE0In0.PFaRVhV_us6hLMjCyfVcA1GdhoSDlZDInOa-g7Ks2HMLYqiaOwzoRjxhLObBY8KQZ4h9mkBbhycnO6HsX6QtXlxdqB4jGACGAQzGxfS9l4kIUJzHacQxVO0SW58U-XITpKZL6tAnLo_rpfnWFdTKUWZ1lBx0Z7ymPiHIqmlrBSdXW9JJTP4OVCq4CsxfUpT65DcLCJebJ7rDbMgCGy6C2SvP676IjBqKeAf44_XjolvBvqHWbYx6WrgbQfZQpPmaqhggyKRRcivgsp8bd1GOuxM9bvXRagdqF1suac5SXZG8vgv-V3UjxyZpmu7XsJeWO085pPsOvG3i7EvIRKgqbg'
    ];

    private $currentToken;

    private $currentUser;

    /**
     *  @BeforeSuite
     */
    public static function setupApplication(BeforeSuiteScope $scope){
        if(putenv('APPLICATION_ENV=acceptance')) {
            echo "APPLICATION_ENV=" . getenv('APPLICATION_ENV') . " set successfully!\n\n";
        } else {
            echo 'Setting APPLICATION_ENV=acceptance failed!';
            die();
        }

        echo shell_exec(__DIR__ . '/../../../vendor/bin/doctrine-module orm:schema-tool:drop --force');
        echo shell_exec(__DIR__ . '/../../../vendor/bin/doctrine-module orm:schema-tool:create');
        echo shell_exec(__DIR__ . '/../../../vendor/bin/doctrine-module dbal:import ' . __DIR__ . '/../../sql/init.sql');
    }

    /** @AfterSuite */
    public static function teardownApplication(AfterSuiteScope $scope){
        echo shell_exec(__DIR__ . '/../../../vendor/bin/doctrine-module orm:schema-tool:drop --force');

    }

    /**
     * @AfterScenario
     */
    public function closeConnections()
    {
        $connection = $this->_client
                         ->getServiceManager()
                         ->get('doctrine.connection.orm_default');

        $connection->close();
    }

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     */
    public function __construct()
    {
        $this->_restObject = new stdClass();

        if ($this->_client === null) {
            $config = __DIR__ . '/../../../config/application.test.config.php';
            $this->_client = ZFHttpClient::create($config);
            //$this->_client->enableErrorTrace();

            $this->_fixtureHelper = new TestFixturesHelper($this->_client->getServiceManager());
        }
    }

    /**
     * @Given /^that I want to make a new "([^"]*)"$/
     * @Given /^that I want to cast a new "([^"]*)"$/
     */
    public function thatIWantToMakeANew($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'post';
    }

    /**
     * @Given /^that I want to update a "([^"]*)"$/
     */
    public function thatIWantToUpdateA($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'put';
    }

    /**
     * @Given /^that I want to find a "([^"]*)"$/
     */
    public function thatIWantToFindA($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'get';
    }

    /**
     * @Then /^after I want to find a "([^"]*)"$/
     */
    public function afterIWantToFindA($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'get';
    }

    /**
     * @Given /^that I want to delete a "([^"]*)"$/
     */
    public function thatIWantToDeleteA($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'delete';
    }

    /**
     * @Given /^that its "([^"]*)" is "([^"]*)"$/
     */
    public function thatTheItsIs($propertyName, $propertyValue, PyStringNode $rawPropertyValue = null)
    {
        if ($propertyValue == '_json_') {
            $this->_restObject->$propertyName = $rawPropertyValue->getRaw();

            return;
        }

        $this->_restObject->$propertyName = $propertyValue;
    }

    /**
     * @Given the organization :id has the following lanes:
     */
    public function theOrganizationHasTheFollowingLanes($id, TableNode $table)
    {
        $org = $this->_fixtureHelper->getOrganization($id);

        $eventStore = $this->_fixtureHelper->getEventStore();

        $eventStore->beginTransaction();

        try {

            foreach ($table->getHash() as $line) {
                $org->addLane(Uuid::fromString($line['id']), LaneData::create($line), $this->currentUser);
            }

            $eventStore->commit();

        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $eventStore->rollback();

            throw $e;
        }
    }


    /**
     * @Given that I am authenticated as :email
     */
    public function thatIAmAuthenticatedAs($email)
    {
        if(isset(self::$tokens[$email])) {
            $this->currentUser = $this->_fixtureHelper->findUserByEmail($email);
            $this->currentToken = self::$tokens[$email];
        }
    }

    /**
     * @When I send a POST request to :url with JSON body:
     */
    public function iSendAPostRequestToWithBody($url, PyStringNode $data)
    {
        if ($this->currentToken !== null) {
            $this->_client->setJWTToken($this->currentToken);
        }

        $postFields = json_decode($data->getRaw(), true);

        $this->_response = $this->_client->post($url, $postFields);
    }

    /**
     * @When I send a PUT request to :url with JSON body:
     */
    public function iSendAPutRequestToWithBody($url, PyStringNode $data)
    {
        if ($this->currentToken !== null) {
            $this->_client->setJWTToken($this->currentToken);
        }

        $postFields = json_decode($data->getRaw(), true);

        $this->_response = $this->_client->put($url, $postFields);
    }

    /**
     * @When I send a GET request to :url
     */
    public function iSendAGetRequestTo($url)
    {
        if ($this->currentToken !== null) {
            $this->_client->setJWTToken($this->currentToken);
        }

        $this->_response = $this->_client->get($url);
    }

    /**
     * @When I send a DELETE request to :url
     */
    public function iSendADeleteRequestTo($url)
    {
        if ($this->currentToken !== null) {
            $this->_client->setJWTToken($this->currentToken);
        }

        $this->_response = $this->_client->delete($url);
    }

    /**
     * @When /^I request "([^"]*)"$/
     */
    public function iRequest($pageUrl)
    {
        if ($this->currentToken !== null) {
            $this->_client->setJWTToken($this->currentToken);
        }

        switch (strtoupper($this->_restObjectMethod))
        {
            case 'GET':
                $response = $this->_client->get($pageUrl . '?' . http_build_query((array) $this->_restObject));
                break;

            case 'PUT':
                $postFields = (array) $this->_restObject;
                $response = $this->_client->put($pageUrl, $postFields);
                break;

            case 'POST':
                $postFields = (array) $this->_restObject;
                $response = $this->_client->post($pageUrl, $postFields);
                break;
            case 'DELETE':
                $response = $this->_client->delete($pageUrl . '?' . http_build_query((array) $this->_restObject));
                break;
            default:
                throw new Exception("_restObjectMethod NOT MANAGED!");
        }

        $this->_response = $response;
    }

    /**
     * @Then /^the response should be JSON$/
     */
    public function theResponseShouldBeJson()
    {
        //if(empty($this->json))
        {
            $this->json = json_decode($this->_response->getBody(true));

            if (empty($this->json)) {
                throw new Exception("Response was not JSON\n" . $this->_response->getBody(true));
            }
        }

        return $this->json;
    }

    /**
     * @Then /^the response should contain '([^']*)'$/
     */
    public function theResponseShouldContain($value)
    {
        if (strpos($this->_response->getBody(true), $value) === false) {
            throw new Exception("Response does not contain '$value'\n" . $this->_response->getBody(true));
        }
    }

    /**
     * @Then /^the response should be empty map$/
     */
    public function theResponseShouldBeEmptyMap(){
        $bodyResponse = $this->_response->getBody(true);
        $this->json = json_decode($bodyResponse);
        if(!$this->json instanceof \stdClass){
            throw new Exception("Response was not an empty map, but ".$bodyResponse);
        }
    }

    /**
     * @Then /^the "([^"]*)" property should be "([^"]*)"$/
     * @param $propertyName
     * @param $value
     * @throws Exception
     */

    public function thePropertyShouldBe($propertyName, $value){
        $rv = null;
        $str = '$rv = $this->json->'.str_replace('.', '->', $propertyName).';';
        eval($str);
        if($value == 'null' && !is_null($rv)) {
            throw new Exception("'$propertyName' property value is not '$value''. It is '$rv'");
        } elseif ($rv != $value) {
            throw new Exception("'$propertyName' property value is not equal to '$value'. It is '$rv'");
        }
    }

    /**
     * @Then /^the "([^"]*)" property size should be "([^"]*)"$/
     */
    public function thePropertySizeShouldBe($propertyName, $value)
    {
        $this->theResponseShouldHaveAProperty($propertyName);

        if (is_array($this->json->$propertyName)) {
            if (count($this->json->$propertyName) != $value) {
                throw new \Exception('Property size isn\'t equal to ' . $value .'! It is ' . count($this->json->$propertyName));
            }
        } elseif (count(get_object_vars($this->json->$propertyName)) != $value) {
            throw new \Exception('Property size isn\'t equal to ' . $value .'! It is ' . count($this->json->$propertyName));

        }
    }

    /**
     * @Then /^the "([^"]*)" property size should be greater or equal than "([^"]*)"$/
     */
    public function thePropertySizeShouldBeGreaterOrEqualThan($propertyName, $value)
    {
        $this->theResponseShouldHaveAProperty($propertyName);
        if (is_array($this->json->$propertyName)) {
            if (count($this->json->$propertyName) < $value) {
                throw new \Exception('Property size isn\'t greater or equal than '.$value .'! It is ' . count($this->json->$propertyName));
            }
        } elseif (count(get_object_vars($this->json->$propertyName)) < $value) {
            throw new \Exception('Property size isn\'t greater or equal than '.$value .'! It is ' . count($this->json->$propertyName));
        }
    }

    /**
     * @Given /^the type of the "([^"]*)" property is ([^"]*)$/
     */
    public function theTypeOfThePropertyIsNumeric($propertyName, $typeString)
    {
        $data = json_decode($this->_response->getBody(true));

        if (! empty($data))
        {
            if (! isset($data->$propertyName))
            {
                throw new Exception("Property '" . $propertyName .
                    "' is not set!\n");
            }
            // check our type
            switch (strtolower($typeString))
            {
                case 'numeric':
                    if (! is_numeric($data->$propertyName))
                    {
                        throw new Exception(
                            "Property '" . $propertyName .
                            "' is not of the correct type: " .
                            $theTypeOfThePropertyIsNumeric . "!\n");
                    }
                    break;
            }
        }
        else
        {
            throw new Exception(
                "Response was not JSON\n" . $this->_response->getBody(true));
        }
    }

    /**
     * @Then the response should be a JSON like:
     */
    public function theResponseShouldBeAJSONLike(PyStringNode $string)
    {
        Assert::assertJsonStringEqualsJsonString($string->getRaw(), $this->_response->getBody(true));
    }

    /**
     * @Then the response should be like:
     */
    public function theResponseShouldBeLike(PyStringNode $string)
    {
        $actual = json_decode($this->_response->getBody(true), true);

        $expected = json_decode($string->getRaw(), true);

        Assert::assertEquals(array_values($expected), array_values($actual));
    }

    /**
     * @Then /^the response status code should be (\d+)$/
     */
    public function theResponseStatusCodeShouldBe($httpStatus)
    {
        if ((string) $this->_response->getStatusCode() !== $httpStatus)
        {
            throw new \Exception(
                'HTTP code does not match ' . $httpStatus . ' (actual: ' .
                $this->_response->getStatusCode() .
                ') reason: ' .
                $this->_response->getReasonPhrase() .
                ' body: ' .
                $this->_response->getBody()
            );
        }
    }

    /**
     * @Then /^the header "([^"]*)" should be "([^"]*)"$/
     */
    public function theHeaderShouldBe($header, $value)
    {

    }

    /**
     * @Then /^the response should have a "([^"]*)" property$/
     */
    public function theResponseShouldHaveAProperty($propertyName)
    {
        $properties = $this->getJsonProperties();
        if(substr($propertyName, 0, 1) === '/' && substr($propertyName, -1) === '/') {
            $filtered = array_filter($properties, function ($value) use ($propertyName) {
                if(preg_match($propertyName, $value)) {
                    return $value;
                }
            });
            if(count($filtered) == 0) {
                throw new Exception("Property '" . $propertyName ."' does not exist in response " . implode(', ', $properties));
            }
        } elseif (! in_array($propertyName, $properties)) {
            throw new Exception("Property '" . $propertyName ."' does not exist in response " . implode(', ', $properties));
        }
    }

    /**
     * @Then /^the response shouldn't have a "([^"]*)" property$/
     */
    public function theResponseShouldntHaveAProperty($propertyName)
    {
        $properties = $this->getJsonProperties();
        if(substr($propertyName, 0, 1) === '/' && substr($propertyName, -1) === '/') {
            $filtered = array_filter($properties, function ($value) use ($propertyName) {
                if(preg_match($propertyName, $value)) {
                    return $value;
                }
            });
            if(count($filtered) > 0) {
                throw new Exception("Property '" . $propertyName ."' exists in response " . implode(', ', $filtered));
            }
        } elseif (in_array($propertyName, $properties)) {
            throw new Exception("Property '" . $propertyName ."' exists in response ");
        }
    }

    /**
     * @Then /^echo last response$/
     * @Then echo the response
     */
    public function echoLastResponse()
    {
        print_r($this->_requestUrl);
        print_r("\n\n");
        print_r($this->_response->getBody(true));
    }

    /**
     * @Then /^echo last request token$/
     */
    public function echoLastRequestToken()
    {
        print_r("Last Request Token: ".$this->currentToken);
    }

    protected function getResponse()
    {
        return $this->_response;
    }

    protected function getJsonProperties()
    {
        //if(is_null($this->jsonProperties))
        {
            $json = $this->theResponseShouldBeJson();
            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($json), \RecursiveIteratorIterator::SELF_FIRST);
            $this->jsonProperties = array();
            foreach ($iterator as $key => $value) {

                // Build long key name based on parent keys
                for ($i = $iterator->getDepth() - 1; $i >= 0; $i--) {

                    $firstKeyChunk = $iterator->getSubIterator($i)->key();

                    if (strpos($firstKeyChunk, ':')) {
                        $firstKeyChunk = "{'$firstKeyChunk'}";
                    }

                    if (preg_match('/(^[\d]+)/', $key))
                        $key = $firstKeyChunk . preg_replace('/(^\d+)/', '[${1}]', $key);
                    else
                        $key = $firstKeyChunk . '.' . $key;
                }
                $this->jsonProperties[] = $key;
            }
        }
        return $this->jsonProperties;
    }
}
