<?php

namespace Solution\JsonRpcApiExtension\Context;

use Behat\Gherkin\Node\TableNode;
use Graze\GuzzleHttp\JsonRpc\ClientInterface;
use Graze\GuzzleHttp\JsonRpc\Message\Request;
use Graze\GuzzleHttp\JsonRpc\Message\Response;
use GuzzleHttp\Exception\RequestException;
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
    /** @var  string */
    protected $path;
    /** @var  array */
    protected $headers = [];

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
     * @Given  user :arg1 with pass :arg2
     */
    public function userWithPass($arg1, $arg2)
    {
        $this->headers['Authorization'] = 'Basic ' . base64_encode(sprintf('%s:%s', $arg1, $arg2));
    }

    /**
     * @Given path :arg1
     */
    public function path($path)
    {
        $this->path = $path;
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
     * @Then response is successfully with contain result:
     */
    public function responseIsSuccessfullyWithContainResult(TableNode $table)
    {
        Assertions::assertEquals(200, $this->response->getStatusCode(), sprintf('Response with error "%s"', $this->response->getBody()));
        Assertions::assertTrue(is_null($this->response->getRpcErrorCode()), sprintf('Response with error "%s"', $this->response->getBody()));

        $etalon = $table->getRowsHash();
        $actual = $this->response->getRpcResult();

        foreach ($etalon as $key => $needle) {
            Assertions::assertArrayHasKey($key, $actual, var_export($actual, true));
            $etalonValue = $etalon[$key];

            if ($etalonValue == '@true') {
                $etalonValue = true;
            } else {
                if ($etalonValue == '@false') {
                    $etalonValue = false;
                }
            }

            Assertions::assertEquals($etalonValue, $actual[$key], sprintf('Field: %s, response: %s', $key, $this->response->getBody()));
        }
    }

    /**
     * Sends Json rpc request to specific method without params
     *
     * @param string $method request method
     *
     * @When /^(?:I )?send a request to "([^"]+)" without params$/
     */
    public function iSendARequestWithoutParams($method)
    {
        $this->iSendARequest($method, new TableNode([]));
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
        $this->request->setHeaders($this->headers);
        $this->request->setPath($this->request->getPath() . $this->path);
        $this->sendRequest();
    }

    /**
     * Parse json-rpc error response
     *
     * @param $id
     * @param $message
     *
     * @Then /^(?:the )?response should be error with id "([^"]+)", message "([^"]+)"$/
     */
    public function theResponseShouldContainErrorWithMessage($id, $message)
    {
        Assertions::assertEquals(intval($id), $this->response->getRpcErrorCode());
        Assertions::assertEquals($message, $this->response->getRpcErrorMessage());
    }

    /**
     * Parse json-rpc error response
     *
     * @param int|string $id
     *
     * @Then /^(?:the )?response should be error with id "([^"]+)"$/
     */
    public function theResponseShouldContainError($id)
    {
        Assertions::assertEquals(intval($id), $this->response->getRpcErrorCode());
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
        Assertions::assertEquals(200, $this->response->getStatusCode());

        $this->theResponseShouldContainError($id, $message);
        $error = $this->getFieldFromBody('error', $this->response->getBody());
        Assertions::assertArrayHasKey('data', $error);
        Assertions::assertEquals($table->getRowsHash(), $error['data']);
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
            throw new \Exception((string)$this->response->getBody());
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