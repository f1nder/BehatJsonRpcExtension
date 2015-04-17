<?php


namespace Solution\JsonRpcApiExtension\Context;

use Behat\Behat\Context\Context;
use Graze\GuzzleHttp\JsonRpc\ClientInterface;

interface JsonRpcClientAwareContext extends Context
{
    /**
     * Sets Json-Rpc Client instance.
     *
     * @param ClientInterface $client
     * @return void
     */
    public function setClient(ClientInterface $client);
}
