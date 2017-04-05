<?php

namespace FlowManagement\Controller;

use Application\Service\FrontendRouter;
use FlowManagement\Service\FlowService;
use ZFX\Test\Controller\ControllerTest;
use Application\Entity\User;
use FlowManagement\Entity\VoteIdeaCard;
use FlowManagement\FlowCardInterface;
use People\Entity\Organization;
use TaskManagement\Entity\Task;
use TaskManagement\Entity\Stream;
use Rhumsaa\Uuid\Uuid;

class CardControllerTest extends ControllerTest
{
    private $user;
    private $organization;
    
    protected function setupController()
    {
        $flowServiceStub = $this->getMockBuilder(FlowService::class)->getMock();
        $feRouter = $this->getMockBuilder(FrontendRouter::class)->getMock();

        return new CardsController($flowServiceStub, $feRouter);
    }
    
    protected function setupRouteMatch()
    {
        return ['controller' => 'cards'];
    }
    
    protected function setupMore()
    {
        $this->user = User::createUser(Uuid::uuid4());
        $this->user->setFirstname('Stephen');
        $this->user->setLastname('Hero');
        $this->user->setRole(User::ROLE_USER);
        $this->organization = new Organization("000000");
    }
    
    public function testGetEmptyListFromFlow()
    {
        $this->setupLoggedUser($this->user);
        
        $this->controller->getFlowService()
            ->expects($this->once())
            ->method('findFlowCards')
            ->willReturn([]);
        
        $this->request->setMethod('get');
        
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $arrayResult = json_decode($result->serialize(), true);
        $this->assertCount(0, $arrayResult['_embedded']['ora:flowcard']);
        $this->assertNotEmpty($arrayResult['_links']['self']['href']);
        $this->assertArrayNotHasKey('next', $arrayResult['_links']);
        $this->assertEquals(0, $arrayResult['count']);
        $this->assertEquals(0, $arrayResult['total']);
    }
    
    public function testGetListFromFlow()
    {
        $this->setupLoggedUser($this->user);
        
        $card = new VoteIdeaCard('100000', $this->user);
        $stream = new Stream("000002", $this->organization);
        $item = new Task("000003", $stream);
        $card->setContent(FlowCardInterface::VOTE_IDEA_CARD, [
            "orgId" => $this->organization->getId()
        ]);
        $card->setItem($item);
        $this->controller->getFlowService()
            ->expects($this->once())
            ->method('findFlowCards')
            ->willReturn([$card]);
        $this->controller->getFlowService()
            ->expects($this->once())
            ->method('countCards')
            ->willReturn(1);
        
        $this->request->setMethod('get');
        
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $arrayResult = json_decode($result->serialize(), true);
        $this->assertCount(1, $arrayResult['_embedded']['ora:flowcard']);
        $this->assertNotEmpty($arrayResult['_links']['self']['href']);
        $this->assertArrayNotHasKey('next', $arrayResult['_links']);
        $this->assertEquals(1, $arrayResult['count']);
        $this->assertEquals(1, $arrayResult['total']);
    }
    
    public function testGetListFromFlowAsAnonymous()
    {
        $this->setupAnonymous();
        $this->request->setMethod('get');
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();
    
        $this->assertEquals(401, $response->getStatusCode());
    }
}