<?php

namespace Application;

use IntegrationTest\BaseIntegrationTest;
use Application\Entity\User;
use Test\TestFixturesHelper;
use Zend\Http\Request;

class UsersSecondaryEmailsTest extends BaseIntegrationTest
{
    protected $request;

    protected $admin;
    protected $organization;
    protected $user;

    protected function setUp()
    {
        $this->request = new Request();

        $this->admin = $this->createUser(['given_name' => 'Admin', 'family_name' => 'Uber', 'email' => TestFixturesHelper::generateRandomEmail()], User::ROLE_ADMIN);
        $this->user = $this->createUser([ 'given_name' => 'John', 'family_name' => 'Doe', 'email' => TestFixturesHelper::generateRandomEmail() ], User::ROLE_USER);

        $this->organization = $this->createOrganization(TestFixturesHelper::generateRandomName(), $this->admin);

        $this->setupController('Application\Controller\UsersSecondaryEmails', 'users');
        $this->setupAuthenticatedUser($this->user->getEmail());
    }


    public function testUserSecondaryEmail()
    {
        $testEmail = TestFixturesHelper::generateRandomEmail();
        $testEmail2 = TestFixturesHelper::generateRandomEmail();
        $this->assertFalse($this->user->hasSecondaryEmail($testEmail));

        $result   = $this->controller->getList();
        $this->assertEquals(
            [
                'id' => $this->user->getId(),
                'secondaryEmails' => []
            ],
            $result->getVariables()
        );


        $result   = $this->controller->replaceList([ $testEmail, $testEmail2 ]);
        $response = $this->controller->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            [
                'id' => $this->user->getId(),
                'secondaryEmails' => [ $testEmail, $testEmail2 ]
            ],
            $result->getVariables()
        );


        $result   = $this->controller->getList();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            [
                'id' => $this->user->getId(),
                'secondaryEmails' => [ $testEmail, $testEmail2 ]
            ],
            $result->getVariables()
        );


        $this->user = $this->userService->findUser($this->user->getId());
        $this->assertTrue($this->user->hasSecondaryEmail($testEmail));


        $result   = $this->controller->replaceList([ $testEmail2 ]);

        $this->user = $this->userService->findUser($this->user->getId());
        $this->assertFalse($this->user->hasSecondaryEmail($testEmail));
        $this->assertTrue($this->user->hasSecondaryEmail($testEmail2));
    }
}
