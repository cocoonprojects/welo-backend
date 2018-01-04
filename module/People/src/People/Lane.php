<?php

namespace People;

use Rhumsaa\Uuid\Uuid;

class Lane
{
    protected $id;

    protected $name;

    public function __construct(Uuid $id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId()
    {
        return $this->id->toString();
    }

}