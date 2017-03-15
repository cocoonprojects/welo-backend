<?php

namespace Test;

class ResponseDecorator
{
    protected $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function asJson()
    {
        $res = json_decode($this->response->getContent(), true);

        if (is_null($res)) {
            throw new \RuntimeException($this->response->getContent(), 1);
        }

        return $res;
    }

    public function isStatusOk()
    {
        return 200 == $this->response->getStatusCode();
    }

    public function isStatusNotFound()
    {
        return 404 == $this->response->getStatusCode();
    }

    public function isStatusBadRequest()
    {
        return 400 == $this->response->getStatusCode();
    }

    public function __call($name, $arguments)
    {
        if (!method_exists($this->response, $name)) {
            throw new \RuntimeException("cannot invoke $name on response", 1);
        }

        return $this->response->$name($arguments);
    }
}