<?php

namespace Solution\JsonRpcApiExtension\Context;

use Behat\Gherkin\Node\TableNode;
use Graze\GuzzleHttp\JsonRpc\ClientInterface;
use Graze\GuzzleHttp\JsonRpc\Message\Request;
use Graze\GuzzleHttp\JsonRpc\Message\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Utils;
use PHPUnit_Framework_Assert as Assertions;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * JSON-RPC Client
 */
class JsonRpcClientContext implements JsonRpcClientAwareContext
{
    /**
     * @var  ClientInterface
     */
    protected $client;

    /**
     * @var int
     */
    protected $requestId;

    /**
     * @var  Response
     */
    protected $response;

    /**
     * @var  Request
     */
    protected $request;

    /**
     * @var  string
     */
    protected $path;

    /**
     * @var  array
     */
    protected $headers = [];

    /**
     * Sets Json-Rpc Client instance.
     *
     * @param ClientInterface $client
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Set the username and password for send request
     *
     * @Given  /^user "([^"]+)" with pass "([^"]+)"$/
     *
     * @param string $username
     * @param string $password
     */
    public function userWithPass($username, $password)
    {
        $header = base64_encode(sprintf('%s:%s', $username, $password));

        $this->headers['Authorization'] = 'Basic ' . $header;
    }

    /**
     * Set path for send request
     *
     * @Given /^path "([^"]+)"$/
     *
     * @param string $path
     */
    public function path($path)
    {
        $this->path = $path;
    }

    /**
     * Set request identifier
     *
     * @param string|integer $id
     *
     * @Given /^I set request id "([^"]*)"$/
     */
    public function iSetRequestId($id)
    {
        $this->setRequestId($id);
    }

    /**
     * Check response is successfully with result.
     * In table data, you can use "PropertyPath" for get elements from JSON. As example:
     *
     * | element[key][foo-key] | some_value |
     * | element[key][bar-key] | some_value |
     *
     * @Then response is successfully with contain result:
     *
     * @param TableNode $table
     */
    public function responseIsSuccessfullyWithContainResult(TableNode $table)
    {
        Assertions::assertEquals(200, $this->response->getStatusCode(), sprintf('Response with error "%s"', $this->response->getBody()));
        Assertions::assertTrue(is_null($this->response->getRpcErrorCode()), sprintf('Response with error "%s"', $this->response->getBody()));

        $etalon = $table->getRowsHash();
        $actual = $this->response->getRpcResult();

        $propertyAccessor = new PropertyAccessor();

        foreach ($etalon as $key => $needle) {
            $etalonValue = $etalon[$key];

            if ($etalonValue == '@true') {
                $etalonValue = true;
            } else {
                if ($etalonValue == '@false') {
                    $etalonValue = false;
                }
            }

            if (strpos($key, '[') !== false) {
                // Try get value via "PropertyPath"
                $elements = explode('[', $key);
                $elements = array_map(function ($element){
                    return trim($element, '][');
                }, $elements);

                $propertyPath = '[' . implode($elements, '][') . ']';

                $propertyPath = new PropertyPath($propertyPath);
                $actualValue = $propertyAccessor->getValue($actual, $propertyPath);
                $rootKey = $propertyPath->getElement(0);
            } else {
                $actualValue = $actual[$key];
                $rootKey = $key;
            }

            Assertions::assertArrayHasKey($rootKey, $actual, var_export($actual, true));
            Assertions::assertEquals($etalonValue, $actualValue, sprintf('Field: %s, response: %s', $key, $this->response->getBody()));
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
     * @param string    $method request method
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
     * @param string|integer $id
     * @param string         $message
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
     * @param string|integer $id
     * @param string         $message
     * @param TableNode      $table
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
     * Set request identifier
     *
     * @param string|integer $requestId
     */
    protected function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    /**
     * Get request id
     *
     * @return mixed
     */
    protected function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * Send request
     *
     * @throws \Exception
     */
    private function sendRequest()
    {
        try {
            $this->response = $this->client->send($this->request);
        } catch (RequestException $e) {
            $this->response = $e->getResponse();

            if (null === $this->response) {
                throw $e;
            }

            throw new \Exception((string) $this->response->getBody());
        }
    }

    /**
     * Get field from body
     *
     * @param string $key
     * @param string $body
     *
     * @return mixed
     */
    protected function getFieldFromBody($key, $body)
    {
        $rpc = Utils::jsonDecode((string)$body, true);

        return isset($rpc[$key]) ? $rpc[$key] : null;
    }
}
