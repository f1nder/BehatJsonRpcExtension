<?php

namespace Solution\JsonRpcApiExtension\Context;

use Behat\Gherkin\Node\TableNode;
use Graze\GuzzleHttp\JsonRpc\ClientInterface;
use Graze\GuzzleHttp\JsonRpc\Exception\RequestException;
use Graze\GuzzleHttp\JsonRpc\Message\Request;
use Graze\GuzzleHttp\JsonRpc\Message\Response;
use GuzzleHttp\Utils;
use PHPUnit_Framework_Assert as Assertions;

class JsonRpcClientContext implements JsonRpcClientAwareContext
{
    /** @var  ClientInterface */
    protected $client;
    /** @var  int */
    protected $requestId;
    /** @var  Response */
    protected $response;
    /** @var  Request */
    protected $request;

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

    /**
     * @param $id
     *
     * @Given /^I set request id "([^"]*)"$/
     */
    public function iSetRequestId($id)
    {
        $this->setRequestId($id);
    }

    /**
     * Sends Json rpc request to specific method.
     *
     * @param string $method request method
     * @param TableNode $params table of params values
     *
     * @When /^(?:I )?send a request to "([^"]+)" with params:$/
     */
    public function iSendARequest($method, TableNode $params)
    {
        $id = $this->getRequestId() ? $this->getRequestId() : uniqid();
        $this->request = $this->client->request($id, $method, $params->getRowsHash());

        $this->sendRequest();
    }

    /**
     * @param TableNode $result
     *
     * @Then /^(?:the )?response should contain result:$/
     */
    public function theResponseShouldContainResult(TableNode $result)
    {
        Assertions::assertEquals($result->getRowsHash(), $this->response->getRpcResult());
    }


    /**
     * Parse json-rpc error response
     *
     * @param $id
     * @param $message
     *
     * @Then /^(?:the )?response should be error with id "([^"]+)", message "([^"]+)"$/
     */
    public function theResponseShouldContainError($id, $message)
    {
        Assertions::assertEquals($this->response->getRpcErrorCode(), intval($id));
        Assertions::assertEquals($this->response->getRpcErrorMessage(), $message);
    }

    /**
     * Check json-rpc error data
     *
     * @param $id
     * @param $message
     * @param TableNode $table
     *
     * @Then /^(?:the )?response should be error with id "([^"]+)", message "([^"]+)", data:$/
     */
    public function theResponseShouldContainErrorData($id, $message, TableNode $table)
    {
        $this->theResponseShouldContainError($id, $message);
        $error = $this->getFieldFromBody('error', $this->response->getBody());
        Assertions::assertArrayHasKey('data', $error);
        Assertions::assertEquals($error['data'], $table->getRowsHash());
    }

    /**
     * @param mixed $requestId
     */
    protected function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    /**
     * @return mixed
     */
    protected function getRequestId()
    {
        return $this->requestId;
    }

    private function sendRequest()
    {
        try {
            $this->response = $this->client->send($this->request);
        } catch (RequestException $e) {
            $this->response = $e->getResponse();
            if (null === $this->response) {
                throw $e;
            }
        }
    }

    /**
     * @param $key
     * @param $body
     * @return null
     */
    protected function getFieldFromBody($key, $body)
    {
        $rpc = Utils::jsonDecode((string)$body, true);

        return isset($rpc[$key]) ? $rpc[$key] : null;
    }
}