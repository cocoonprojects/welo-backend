<?php
namespace TaskManagement;

use PHPUnit_Framework_TestCase;
use Test\ZFHttpClient;


class RollbackStateTransitionProcessTest extends PHPUnit_Framework_TestCase
{
    protected $client;

    private static $tokens = [
        'bruce.wayne@ora.local' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiI4MDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgzMzE0In0.PFaRVhV_us6hLMjCyfVcA1GdhoSDlZDInOa-g7Ks2HMLYqiaOwzoRjxhLObBY8KQZ4h9mkBbhycnO6HsX6QtXlxdqB4jGACGAQzGxfS9l4kIUJzHacQxVO0SW58U-XITpKZL6tAnLo_rpfnWFdTKUWZ1lBx0Z7ymPiHIqmlrBSdXW9JJTP4OVCq4CsxfUpT65DcLCJebJ7rDbMgCGy6C2SvP676IjBqKeAf44_XjolvBvqHWbYx6WrgbQfZQpPmaqhggyKRRcivgsp8bd1GOuxM9bvXRagdqF1suac5SXZG8vgv-V3UjxyZpmu7XsJeWO085pPsOvG3i7EvIRKgqbg',
    ];

    public function setUp()
    {
        $config = getenv('APP_ROOT_DIR') . '/config/application.test.config.php';
        $this->client = ZFHttpClient::create($config);
        $this->client->enableErrorTrace();
        $this->client->setJWTToken(static::$tokens['bruce.wayne@ora.local']);
    }


	public function testRevertOngoingToOpen() {

        $serviceManager = $this->client->getServiceManager();
        $userService = $serviceManager->get('Application\UserService');
        $admin = $userService->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $userService->findUserByEmail('phil.toledo@ora.local');
        $member2 = $userService->findUserByEmail('paul.smith@ora.local');

        $res = $this->createOrganization($serviceManager,'my org', $admin, [$member1, $member2]);
        $task = $this->createTask($serviceManager, 'Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member1, $member2]);


        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "open" ]
            );

        $task = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_OPEN, $task['status']);
        $this->assertEmpty($task['members']);
    }


    protected function createOrganization($serviceManager, $name, $admin, array $members)
    {
        $orgService = $serviceManager->get('People\OrganizationService');
        $streamService = $serviceManager->get('TaskManagement\StreamService');
        $transactionManager = $serviceManager->get('prooph.event_store');

        $org = $orgService->createOrganization($name, $admin);
        $stream = $streamService->createStream($org, 'banana', $admin);

        $transactionManager->beginTransaction();

        try {

            foreach ($members as $member) {
                $org->addMember($member);
            }

            $transactionManager->commit();
        } catch (\Exception $e) {
            $transactionManager->rollback();
            throw $e;
        }

        return ['org' => $org, 'stream' => $stream];
    }

    protected function createTask($serviceManager, $subject, $stream, $admin, array $members)
    {
        $taskService = $serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {

            $task = Task::create($stream, $subject, $admin);
            $task->addMember($admin, Task::ROLE_OWNER);

            foreach ($members as $member) {
                $task->addMember($member, Task::ROLE_MEMBER);
            }

            $task->open($admin);
            $task->execute($admin);

            $task->addEstimation(1500, $admin);

            foreach ($members as $member) {
                $task->addEstimation(2050, $member);
            }

            $taskService->addTask($task);

            $transactionManager->commit();
        }catch (\Exception $e) {
            $transactionManager->rollback();
            throw $e;
        }

        return $task;
    }

}
