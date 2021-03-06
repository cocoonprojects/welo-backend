<?php

namespace Test;

use People\DTO\LaneData;
use People\Organization;
use TaskManagement\Entity\Vote;
use TaskManagement\Task;
use Zend\ServiceManager\ServiceManager;

class TestFixturesHelper
{
    private $serviceManager;

    private static $tokens = [
        'mark.rogers@ora.local' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiI2MDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgyOTU1In0.rqGFFeOf5VdxO_qpz_fkwFJtgJH4Q5Kg6WUFGA_L1tMB-yyZj7bH3CppxxxvpekQzJ7y6aH6I7skxDh1K1Cayn3OpyaXHyG9V_tlgo08TKR7EK0TsBA0vWWiT7Oito97ircrw_4N4ZZFmF6srpNHda2uw775-7SpQ8fdI0_0LOn1IwF1MKvJIuZ9J7bR7PZsdyqLQSpNm8P5gJiA0c6i_uubtVEljVvr1H1mSoq6hViS9A2M-v4THlbH_Wki2pYp00-ggUu6dm25NeX300Q6x2RBHVY_bXpw7voRbXI1VAg_LxXDjv61l4lar6dOhK3qbsXm9P2JTEqyG7bYSAqtLA',
        'phil.toledo@ora.local' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiI3MDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgzMDYzIn0.etOL9ozjnNni8-cu3dF4RO1rcQhmUkJ3fOzBTEWK4IIJjaVhjdYwTX_FFiWG_pKNPAI0EItijRxAG4zh66zHV-6ERnTAD7VA6V7Si_LA8vAS3gIsB1XsrkJ2Xjrj8ax7HtzM5UVhHwEXDZGXJQ3XEZX0tXO-jUvvizZ5qwFSAopSpydcTjwQmMDdr_stGuGJ5qq03sEN4Z5iWugsJoVBSf389KlIfXqlvTnVy2tojDh4ba7sWhh-O9IkxCMtJrUckn2_iI1TS-3Z1iavVh8ebTwbVx41QAjAR_I_CerINNIeewRoVGu3R2gYdVvf4PphaUXZLS7sN3KaldvTVB59jA',
        'paul.smith@ora.local'  => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiIyMDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgzMjQzIn0.WTKW0CBmHlHIfBmtzTeakDUlX0p775w59bT1FKN2TIYcJ3nBEF_hmY0s3eEKZ6dOs4PjxyskVRYiB5dlbG1ZSYRbOJGysn5lvltXBmhOk2Ad3RiI8rina-Af0eBXS96A2BY2Qc2NN5t3EcjmIateH_dgG85adewQSZVJTTKKUBid46fdZ0TO5Y1jcr153xxMuE66W9gMGP2ffUGJIt01UQeuljQM1OF8Ss87l9tIcgRrKd5NiU5ap6JY4nTiZYgh8d7LPd4NfZ34GdQjt0vM0J9q_pQ9dN2GzuF9MO09TjRfmMNuE-fHboye4ahTaHH2OcUFDEMOF6XWy8tw8t7E3A',
        'bruce.wayne@ora.local' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJ1aWQiOiI4MDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJpYXQiOiIxNDM4NzgzMzE0In0.PFaRVhV_us6hLMjCyfVcA1GdhoSDlZDInOa-g7Ks2HMLYqiaOwzoRjxhLObBY8KQZ4h9mkBbhycnO6HsX6QtXlxdqB4jGACGAQzGxfS9l4kIUJzHacQxVO0SW58U-XITpKZL6tAnLo_rpfnWFdTKUWZ1lBx0Z7ymPiHIqmlrBSdXW9JJTP4OVCq4CsxfUpT65DcLCJebJ7rDbMgCGy6C2SvP676IjBqKeAf44_XjolvBvqHWbYx6WrgbQfZQpPmaqhggyKRRcivgsp8bd1GOuxM9bvXRagdqF1suac5SXZG8vgv-V3UjxyZpmu7XsJeWO085pPsOvG3i7EvIRKgqbg',
    ];

    public static function generateRandomName() {
        return round(microtime(true) * 1000).'_'.rand(0,10000);
    }

    public static function generateRandomEmail() {
        return round(microtime(true) * 1000).'_'.rand(0,10000).'@foo.com';
    }

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function getJWTToken($email)
    {
        return static::$tokens[$email];
    }

    public function createOrganization($name, $admin, array $members = [], array $contributors = [], $lanes = [])
    {
        $orgService = $this->serviceManager->get('People\OrganizationService');
        $streamService = $this->serviceManager->get('TaskManagement\StreamService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $org = $orgService->createOrganization($name, $admin);
        $stream = $streamService->createStream($org, 'banana', $admin);

        $transactionManager->beginTransaction();

        try {

            $params = $org->getParams()->toArray();
            $params['manage_priorities'] = 1;

            foreach ($members as $member) {
                $org->addMember($member, Organization::ROLE_MEMBER);
            }

            foreach ($contributors as $contributor) {
                $org->addMember($contributor, Organization::ROLE_CONTRIBUTOR);
            }

            if ($lanes) {

                $params['manage_lanes'] = 1;

                foreach ($lanes as $lane) {
                    $org->addLane($lane['id'], LaneData::create(['name' => $lane['name']]), $admin);
                }
            }

            $org->setParams($params, $admin);

            $transactionManager->commit();
        } catch (\Exception $e) {
            dump($e->getMessage());
            $transactionManager->rollback();
            throw $e;
        }

        return ['org' => $org, 'stream' => $stream];
    }

    public function createOngoingTask($subject, $stream, $owner, $members=[])
    {
        $taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {

            $task = Task::create($stream, $subject, $owner);
            $taskService->addTask($task);

            $task->open($owner);
            $task->execute($owner);

            $task->addEstimation(100, $owner);

            foreach ($members as $member) {
                $task->addMember($member, Task::ROLE_MEMBER);
                $task->addEstimation(100, $member);
            }

            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $transactionManager->rollback();
            throw $e;
        }
        return $task;
    }

    public function createIdea($subject, $stream, $admin, $lane = null)
    {
        $taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {

            $options = [];

            if ($lane) {
                $options = ['lane' => $lane];
            }

            $task = Task::create($stream, $subject, $admin, $options);
            $taskService->addTask($task);

            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $transactionManager->rollback();
            throw $e;
        }

        return $task;
    }

    public function createOpenTask($subject, $stream, $admin, $lane = null)
    {
        $taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {

            $options = [];

            if ($lane) {
                $options = ['lane' => $lane];
            }

            $task = Task::create($stream, $subject, $admin, $options);
            $task->addApproval(Vote::VOTE_FOR, $admin, 'Voto a favore '.$admin->getId());

            $taskService->addTask($task);

            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $transactionManager->rollback();
            throw $e;
        }

        return $task;
    }

    public function createRejectedTask($subject, $stream, $admin)
    {
        $taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {
            $task = Task::create($stream, $subject, $admin);
            $task->reject($admin);

            $taskService->addTask($task);
            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $transactionManager->rollback();
            throw $e;
        }

        return $task;
    }

    public function createCompletedTask($subject, $stream, $owner, $members)
    {
        $taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {
            $task = Task::create($stream, $subject, $owner);
            $taskService->addTask($task);

            $task->open($owner);
            $task->execute($owner);

            $task->addEstimation(100, $owner);

            foreach ($members as $member) {
                $task->addMember($member);
                $task->addEstimation(100, $member);
            }

            $task->complete($owner);

            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $transactionManager->rollback();
            throw $e;
        }

        return $task;
    }

    public function createAcceptedTask($subject, $stream, $owner, $members)
    {
        $taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {
            $task = Task::create($stream, $subject, $owner);
            $taskService->addTask($task);

            $task->open($owner);
            $task->execute($owner);

            $task->addEstimation(100, $owner);

            foreach ($members as $member) {
                $task->addMember($member);
                $task->addEstimation(100, $member);
            }

            $task->complete($owner);
            $task->accept($owner);


            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $transactionManager->rollback();
            throw $e;
        }

        return $task;
    }

    public function createAcceptedTaskWithShares($subject, $stream, $owner, $members)
    {
        $taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {
            $task = Task::create($stream, $subject, $owner);
            $taskService->addTask($task);

            $task->open($owner);
            $task->execute($owner);

            $task->addEstimation(100, $owner);

            foreach ($members as $member) {
                $task->addMember($member);
                $task->addEstimation(100, $member);
            }

            $task->complete($owner);
            $task->accept($owner);

            $task->assignShares([
                $owner->getId() => '0.4',
                $members[0]->getId() => '0.4',
                $members[1]->getId() => '0.2',
            ], $owner);

            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $transactionManager->rollback();
            throw $e;
        }

        return $task;
    }

    public function createClosedTask($subject, $stream, $owner, $member1, $member2)
    {
        $taskService = $this->serviceManager->get('TaskManagement\TaskService');
        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();

        try {
            $task = Task::create($stream, $subject, $owner);
            $taskService->addTask($task);

            $task->open($owner);
            $task->execute($owner);

            $task->addEstimation(100, $owner);

            $task->addMember($member1);
            $task->addEstimation(100, $member1);

            $task->addMember($member2);
            $task->addEstimation(100, $member2);

            $task->complete($owner);
            $task->addAcceptance(Vote::VOTE_FOR, $owner, 'bella li');

            $task->accept($owner);

            $task->assignShares([$owner->getId() => '0.4', $member1->getId() => '0.4', $member2->getId() => '0.2'], $owner);
            $task->assignShares([$owner->getId() => '0.1', $member1->getId() => '0.8', $member2->getId() => '0.1'], $member1);
            $task->assignShares([$owner->getId() => '0.5', $member1->getId() => '0.2', $member2->getId() => '0.3'], $member2);

            $task->close($owner);

            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
            $transactionManager->rollback();
            throw $e;
        }

        return $task;
    }

    public function saveUser($user)
    {
        $userService = $this->serviceManager->get('Application\UserService');
        $userService->saveUser($user);
    }

    public function findUserByEmail($email)
    {
        $userService = $this->serviceManager->get('Application\UserService');

        return $userService->findUserByEmail($email);
    }

    public function getOrganization($id)
    {
        $orgService = $this->serviceManager->get('People\OrganizationService');

        return $orgService->getOrganization($id);
    }

    public function getEventStore()
    {
        return $this->serviceManager->get('prooph.event_store');
    }
}