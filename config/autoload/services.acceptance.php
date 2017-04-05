<?php

use Application\Authentication\OAuth2\UserServiceOAuth2AdapterMock;
use Kanbanize\Service\KanbanizeAPI;

return array(
    'doctrine' => array (
        'configuration' => array(
            'orm_default' => array(
                'generate_proxies'	=> true,
                'proxy_dir'			=> __DIR__ . '/../../data/DoctrineORMModule/Proxies/'
            )
        )
    ),
    'service_manager' => array(
        'factories' => array(
            'Application\Service\AdapterResolver' => function ($locator) {
                $userService = $locator->get('Application\UserService');

                return new UserServiceOAuth2AdapterMock($userService);
            },
            'Kanbanize\KanbanizeAPI' => function ($locator) {
                $mockGenerator = new \PHPUnit_Framework_MockObject_Generator();

                return $mockGenerator->getMock(
                    KanbanizeAPI::class, ['getProjectsAndBoards', 'getBoardStructure', 'getTaskDetails']
                );
            },
        ),
    ),
);
