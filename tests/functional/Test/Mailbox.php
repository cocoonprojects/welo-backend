<?php

namespace Test;

use Guzzle\Http\Client;

/**
 * A convenience wrapper for Mailcatcher
 */
class Mailbox
{
    protected $client;

    private function __construct(){}

    public function clean()
    {
        $response = $this->client->delete('/messages')->send();

        return $response;
    }

    public function getMessages()
    {
        $response = $this->client->get('/messages')->send();

        return json_decode($response->getBody());
    }

    public function getMessage($id)
    {
        $response = $this->client->get("/messages/$id.html")->send();

        return $response;
    }

    public function getLastMessage()
    {
        $response = $this->client->get('/messages')->send();

        $mails = json_decode($response->getBody());

        $lastEmail = array_pop($mails);

        return $this->getMessage($lastEmail->id)->getBody(true);

    }

    public static function create($url = 'http://127.0.0.1:1080')
    {
        $mailbox = new self();
        $mailbox->client = new Client($url);

        return $mailbox;
    }


}