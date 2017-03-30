<?php
namespace Kanbanize\Entity;

use Doctrine\ORM\Mapping as ORM;
use TaskManagement\Entity\Task;

/**
 * @ORM\Entity
 * @ORM\Table(name="kanbanizetasks")
 */
class KanbanizeTask extends Task
{
    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    private $taskId;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    private $columnName;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    private $assignee;

    public function setTaskId($taskId)
    {
        $this->taskId = $taskId;

        return $this;
    }

    public function getTaskId()
    {
        return $this->taskId;
    }

    public function setColumnName($columnName)
    {
        $this->columnName = $columnName;
        return $this;
    }

    public function getColumnName()
    {
        return $this->columnName;
    }

    public function setAssignee($assignee)
    {
        $this->assignee = $assignee;
        return $this;
    }

    public function getAssignee()
    {
        return $this->assignee;
    }

    public function getAssigneeName()
    {
        $owner = $this->getOwner();

        if (!$owner) {
            return;
        }

        return $owner->getUser()->getDislayedName();
    }

    public function isUpdatedBefore(\DateTime $when)
    {
        return $this->getMostRecentEditAt()->format('U') <= $when->format('U');
    }

    public function getType()
    {
        return 'kanbanizetask';
    }

    public function getResourceId()
    {
        return 'Ora\KanbanizeTask';
    }
}
