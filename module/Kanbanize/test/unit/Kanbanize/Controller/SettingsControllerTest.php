<?php
namespace Kanbanize\Controller;

use ZFX\Test\Controller\ControllerTest;
use Kanbanize\Service\KanbanizeAPI;
use Kanbanize\Service\KanbanizeService;
use People\Service\OrganizationService;
use People\Entity\Organization;
use Application\Entity\User;
use People\Entity\OrganizationMembership;
use Kanbanize\Service\KanbanizeApiException;
use Rhumsaa\Uuid\Uuid;

class SettingsControllerTest extends ControllerTest
{
    protected function setupController()
    {
        $orgService = $this->getMockBuilder(OrganizationService::class)->getMock();
        $kanbanizeService = $this->getMockBuilder(KanbanizeService::class)->getMock();
        $client = $this->getMockBuilder(KanbanizeAPI::class)->getMock();

        return new SettingsController($orgService, $client, $kanbanizeService);
    }

    protected function setupRouteMatch()
    {
        return ['controller' => 'settings'];
    }

    protected function setupMore()
    {
        $this->user = User::createUser(Uuid::uuid4(), null, 'Stephen', 'Hero');
        $this->organization = new Organization('00000');
        $this->organization->setSettings(\People\Organization::KANBANIZE_SETTINGS, [
            "apiKey" => "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
            "accountSubdomain" => "foo"
        ]);
    }

    public function testKanbanizeFailsForWrongApiKey()
    {
        $this->user->addMembership($this->organization, OrganizationMembership::ROLE_ADMIN);
        $this->setupLoggedUser($this->user);

        $this->controller->getOrganizationService()
            ->expects($this->once())
            ->method('findOrganization')
            ->with($this->organization->getId())
            ->willReturn($this->organization);
        $this->controller->getOrganizationService()
            ->expects($this->once())
            ->method('getOrganization')
            ->with($this->organization->getId())
            ->willReturn($this->organization);
        $this->controller->getKanbanizeClient()
            ->expects($this->once())
            ->method('getProjectsAndBoards')
            ->willReturn('T');

        $this->routeMatch->setParam('orgId', $this->organization->getId());
        $result = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $arrayResult = json_decode($result->serialize(), true);
        $this->assertEquals($arrayResult['code'], "400");
        $this->assertEquals($arrayResult['description'], "error_on_kanbanize_get_board");
    }

    public function testKanbanizeFailsForWrongAccountSubdomain()
    {
        $this->user->addMembership($this->organization, OrganizationMembership::ROLE_ADMIN);
        $this->setupLoggedUser($this->user);

        $this->controller->getOrganizationService()
            ->expects($this->once())
            ->method('findOrganization')
            ->with($this->organization->getId())
            ->willReturn($this->organization);
        $this->controller->getOrganizationService()
            ->expects($this->once())
            ->method('getOrganization')
            ->with($this->organization->getId())
            ->willReturn($this->organization);
        $this->controller->getKanbanizeClient()
            ->expects($this->once())
            ->method('getProjectsAndBoards')
            ->will($this->throwException(new KanbanizeApiException("error_on_kanbanize_get_board")));

        $this->routeMatch->setParam('orgId', $this->organization->getId());
        $result = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $arrayResult = json_decode($result->serialize(), true);
        $this->assertEquals($arrayResult['code'], "400");
        $this->assertEquals($arrayResult['description'], "error_on_kanbanize_get_board");
    }
}
