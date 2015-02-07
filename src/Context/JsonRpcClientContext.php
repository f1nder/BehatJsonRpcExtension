<?php

namespace Solution\JsonRpcApiExtension\Context;

use Graze\GuzzleHttp\JsonRpc\ClientInterface;

class JsonRpcClientContext implements JsonRpcClientAwareContext
{
    /** @var  ClientInterface */
    protected $client;

    /**
     * Sets Json-Rpc Client instance.
     *
     * @param ClientInterface $client
     * @return void
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;
    }
}