<?php

namespace Solution\JsonRpcApiExtension\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Graze\GuzzleHttp\JsonRpc\ClientInterface;
use Solution\JsonRpcApiExtension\Context\JsonRpcClientAwareContext;

class ClientAwareInitializer implements ContextInitializer
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * Initializes initializer.
     *
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Initializes provided context.
     *
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof JsonRpcClientAwareContext) {
            $context->setClient($this->client);
        }
    }
}