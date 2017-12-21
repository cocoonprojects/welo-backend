<?php

namespace TaskManagement\Controller;

use Application\Controller\OrganizationAwareController;
use Application\IllegalStateException;
use Application\InvalidArgumentException;
use People\Service\OrganizationService;
use TaskManagement\Service\StreamService;
use TaskManagement\Service\TaskService;
use TaskManagement\DTO\PositionData;

class PositionsController extends OrganizationAwareController
{
    protected $streamService;

    protected $taskService;

    public function __construct(
        OrganizationService $organizationService,
        StreamService $streamService,
        TaskService $taskService
    )
    {
        parent::__construct($organizationService);

        $this->streamService = $streamService;
        $this->taskService = $taskService;
    }

    protected function getCollectionOptions()
    {
        return ['GET', 'POST'];
    }

    protected function getResourceOptions()
    {
        return ['DELETE', 'GET', 'PUT'];
    }

    public function create($data)
    {
        if (is_null($this->identity())) {
            $this->response->setStatusCode(401);
            return $this->response;
        }


        $this->transaction()->begin();

        try {

            $dto = PositionData::fromArray($data);
            $streams = $this->streamService->findStreams($this->organization);
            $this->taskService->updateTasksPositions($streams[0], $dto, $this->identity());

            $this->transaction()->commit();

        } catch (InvalidArgumentException $e) {
            $this->transaction()->rollback();
            $this->response->setStatusCode(400);

            return $this->response;

        } catch (IllegalStateException $e) {
            $this->transaction()->rollback();
            $this->response->setStatusCode(412);

            return $this->response;

        } catch (\Exception $e) {
            $this->transaction()->rollback();
            $this->response->setStatusCode(500);

            return $this->response;
        }

        $this->response->setStatusCode(200);

        return $this->response;
    }
}