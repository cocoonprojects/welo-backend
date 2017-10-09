<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table("event_stream")
 */
class EventStream
{

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=200)
     */
    protected $eventId;

    /**
     * @ORM\Column(type="text")
     */
    protected $eventName;

    /**
     * @ORM\Column(type="text")
     */
    protected $payload;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $occurredOn;

    /**
     * @ORM\Column(type="text")
     */
    protected $aggregate_id;

    /**
     * @ORM\Column(type="text")
     */
    protected $aggregate_type;

    /**
     * @ORM\Column(type="integer", length=11)
     */
    protected $version;

    public function serialize()
    {
        return [
            'id' => $this->eventId,
            'payload' => unserialize($this->payload),
            'name' => $this->eventName,
            'occurredOn' => $this->occurredOn->format('Y-m-d H:i:s')
        ];
    }

}
