<?php

namespace Application\Controller;

use Application\Entity\User;
use Application\IllegalStateException;
use Application\View\ErrorJsonModel;
use TaskManagement\Service\StreamService;
use Application\Service\UserService;
use TaskManagement\Task;
use TaskManagement\View\TaskJsonModel;
use Zend\Filter\FilterChain;
use Zend\Filter\StringTrim;
use Zend\Filter\StripNewlines;
use Zend\Filter\StripTags;
use Zend\I18n\Validator\IsInt;
use Zend\Json\Json;
use Zend\Validator\EmailAddress;
use Zend\Validator\GreaterThan;
use Zend\Validator\InArray as StatusValidator;
use Zend\Validator\NotEmpty;
use Zend\Validator\Regex as UuidValidator;
use Zend\Validator\ValidatorChain;
use Kanbanize\Service\KanbanizeService;
use Kanbanize\KanbanizeTask as KanbanizeTask;
use Zend\View\Model\JsonModel;
use ZFX\Rest\Controller\HATEOASRestfulController;

class UsersSecondaryEmailsController extends HATEOASRestfulController
{
    protected static $collectionOptions = [
            'GET',
            'PUT'
    ];
    protected static $resourceOptions = [
            'DELETE',
            'GET',
            'PUT'
    ];

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(UserService $taskService)
    {
        $this->userService = $taskService;
    }


    public function getList()
    {
        if (is_null($this->identity())) {
            $this->response->setStatusCode(401);

            return $this->response;
        }

        $user = $this->userService->findUser($this->identity()->getId());

        return new JsonModel([
            'id' => $user->getId(),
            'secondaryEmails' => $user->getSecondaryEmails()
        ]);
    }


    public function replaceList($emails)
    {
        if (is_null($this->identity())) {
            $this->response->setStatusCode(401);

            return $this->response;
        }

        $loggedUser = $this->identity();

        foreach ($emails as $email) {
            $userFound = $this->userService->findUserByEmail($email);

            if ($userFound && $userFound->getId()!=$loggedUser->getId()) {
                $this->response->setStatusCode(401);
                $this->response->setReasonPhrase(
                    "email address $email already in use by another account"
                );
                return $this->response;
            }
        }


        $loggedUser->setSecondaryEmails($emails);

        $this->userService->saveUser($loggedUser);

        return new JsonModel([
            'id' => $loggedUser->getId(),
            'secondaryEmails' => $loggedUser->getSecondaryEmails()
        ]);
    }


    protected function getCollectionOptions()
    {
        return self::$collectionOptions;
    }


    protected function getResourceOptions()
    {
        return self::$resourceOptions;
    }


    public function getUserService()
    {
        return $this->userService;
    }
}
