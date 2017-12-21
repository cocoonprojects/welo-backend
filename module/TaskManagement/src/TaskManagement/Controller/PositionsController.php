<?php

namespace TaskManagement\Controller;


use Application\Controller\OrganizationAwareController;
use People\Service\OrganizationService;
use TaskManagement\Service\TaskService;
use Zend\I18n\Validator\IsInt;
use Zend\Validator\ValidatorChain;
use Zend\View\Model\JsonModel;

class PositionsController extends OrganizationAwareController
{

    protected static $collectionOptions = [
        'GET',
        'POST'
    ];
    protected static $resourceOptions = [
        'DELETE',
        'GET',
        'PUT'
    ];

    public function __construct(OrganizationService $organizationService, TaskService $taskService)
    {
        parent::__construct($organizationService);
        $this->taskService = $taskService;
    }


    public function create($data)
    {
        if (is_null($this->identity())) {
            $this->response->setStatusCode(401);
            return $this->response;
        }

        $integerValidator = new ValidatorChain();
        $integerValidator
            ->attach(new IsInt());

        foreach ($data as $id => $position) {
            if (!$integerValidator->isValid($position)) {
                $this->response->setStatusCode(400);

                return $this->response;
            }
        }

        $this->transaction()->begin();
        try {

            $this->taskService->updateTasksPositions($data);

        } catch (\Exception $e) {
            $this->transaction()->rollback();
            $this->response->setStatusCode(500);
            return $this->response;
        }

        $this->response->setStatusCode(200);
        return $this->response;
    }

    protected function getCollectionOptions() {
        return self::$collectionOptions;
    }

    protected function getResourceOptions() {
        return self::$resourceOptions;
    }
}