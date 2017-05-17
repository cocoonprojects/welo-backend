<?php
namespace Kanbanize;

use Application\Entity\BasicUser;
use Application\IllegalStateException;
use TaskManagement\Task;
use TaskManagement\TaskCreated;
use TaskManagement\TaskOngoing;
use TaskManagement\TaskCompleted;
use TaskManagement\TaskClosed;
use TaskManagement\TaskAccepted;
use TaskManagement\TaskUpdated;
use TaksManagement\TaskMoved;
use Rhumsaa\Uuid\Uuid;
use TaskManagement\Stream;

class KanbanizeTask extends Task
{
    const EMPTY_ASSIGNEE = 'None';

    private $taskid;

    private $assignee = self::EMPTY_ASSIGNEE;

    private $columnname;

    public function getColumnName()
    {
        return $this->columnname;
    }

    public function getTaskId()
    {
        return $this->taskid;
    }

    public function getType()
    {
        return 'kanbanizetask';
    }

    public function getAssignee()
    {
        return $this->assignee;
    }

    public function getKanbanizeTaskId()
    {
        return $this->taskId;
    }

    public function getResourceId()
    {
        return 'Ora\KanbanizeTask';
    }

    public static function create(Stream $stream, $subject, BasicUser $createdBy, array $options = null)
    {
        if (!isset($options['taskid'])) {
            throw InvalidArgumentException('Cannot create a KanbanizeTask without a taskid option');
        }
        if (!isset($options['columnname'])) {
            throw InvalidArgumentException('Cannot create a KanbanizeTask without a columnname option');
        }

        $decision = false;

        if (is_array($options) &&
            isset($options['decision']) &&
            $options['decision'] == 'true') {
            $decision = true;
        }

        $rv = new self();
        $rv->id = Uuid::uuid4();
        $rv->status = self::STATUS_IDEA;
        $rv->recordThat(TaskCreated::occur($rv->id->toString(), [
            'status' => $rv->status,
            'taskid' => $options['taskid'],
            'organizationId' => $stream->getOrganizationId(),
            'streamId' => $stream->getId(),
            'by' => $createdBy->getId(),
            'userName' => $createdBy->getFirstname().' '.$createdBy->getLastname(),
            'columnname' => $options['columnname'],
            'lane' => isset($options['lane']) ? $options['lane'] : null,
            'subject' => $subject,
            'description' =>$options['description'],
            'decision' => $decision
        ]));
        return $rv;
    }

    public function setAssignee($assignee, BasicUser $updatedBy)
    {
        $assignee = is_null($assignee) ? self::EMPTY_ASSIGNEE : trim($assignee);
        $this->recordThat(TaskUpdated::occur($this->id->toString(), array(
            'assignee' => $assignee,
            'by' => $updatedBy->getId(),
        )));
        return $this;
    }

    public function setColumnName($name, BasicUser $updatedBy)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Column name cannot be empty');
        }

        $this->recordThat(TaskUpdated::occur($this->id->toString(), array(
            'columnname' => trim($name),
            'by' => $updatedBy->getId(),
        )));

        return $this;
    }

    protected function whenTaskCreated(TaskCreated $event)
    {
        parent::whenTaskCreated($event);

        $this->taskid = $event->payload()['taskid'];
        $this->columnname = $event->payload()['columnname'];

        if (isset($event->payload()['lane'])) {
            $this->lane = $event->payload()['lane'];
        }

        $this->subject = $event->payload()['subject'];
    }

    protected function whenTaskUpdated(TaskUpdated $event)
    {
        parent::whenTaskUpdated($event);

        $pl = $event->payload();

        if (isset($pl['columnname'])) {
            $this->columnname = $pl['columnname'];
        }

        if (isset($pl['lane'])) {
            $this->lane = $pl['lane'];
        }

        if (isset($pl['assignee'])) {
            $this->assignee = $pl['assignee'];
        }
    }
}
