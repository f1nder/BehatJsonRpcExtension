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
     * Check response is successfully
     *
     * @Then /^response is successfully$/
     */
    public function responseIsSuccessfully()
    {
        Assertions::assertEquals(200, $this->response->getStatusCode(), sprintf(
            'Response with error "%s".',
            $this->response->getBody()
        ));

        Assertions::assertNull($this->response->getRpcErrorCode(), sprintf(
            'Response with error "%s".',
            $this->response->getBody()
        ));
    }

    /**
     * Check response is successfully with result.
     * In table data, you can use "PropertyPath" for get elements from JSON. As example:
     *
     * | element[key][foo-key] | some_value |
     * | element[key][bar-key] | some_value |
     *
     * @Then /^response is successfully with contain result:$/
     *
     * @param TableNode $table
     */
    public function responseIsSuccessfullyWithContainResult(TableNode $table)
    {
        Assertions::assertEquals(200, $this->response->getStatusCode(), sprintf(
            'Response with error: "%s".',
            $this->response->getBody()
        ));

        Assertions::assertNull($this->response->getRpcErrorCode(), sprintf(
            'Response with error: "%s".',
            $this->response->getBody()
        ));

        $expected = $table->getRowsHash();
        $actual = $this->response->getRpcResult();

        $this->assertArrayEquals($actual, $expected);
    }

    /**
     * Check response successfully with collection result
     *
     * @Then /^response is successfully with "(?P<count>[^"]+)" elements in collection. Result:$/
     *
     * @param TableNode $table
     * @param int       $count
     */
    public function responseIsSuccessfullyWithContainListAndCountResult(TableNode $table, $count)
    {
        $this->responseIsSuccessfullyWithContainListResult($table, $count);
    }

    /**
     * Check success response with scalar result
     *
     * @Then /^response is successfully with contain result "([^"]+)"$/
     *
     * @param string $result
     */
    public function responseIsSuccessfullyWithContainScalarResult($result)
    {
        Assertions::assertEquals(200, $this->response->getStatusCode(), sprintf(
            'Response with error "%s"',
            $this->response->getBody()
        ));

        Assertions::assertTrue(is_null($this->response->getRpcErrorCode()), sprintf(
            'Response with error "%s"',
            $this->response->getBody()
        ));

        Assertions::assertEquals($result, $this->response->getRpcResult(), sprintf(
            'Result must be a contain "%s"',
            $result
        ));
    }

    /**
     * Check response successfully with list result
     *
     * @Then /^response is successfully with collection result:$/
     *
     * @param TableNode $table
     * @param int       $count
     */
    public function responseIsSuccessfullyWithContainListResult(TableNode $table, $count = null)
    {
        $this->responseIsSuccessfully();

        $rpcResult = $this->response->getRpcResult();

        Assertions::assertTrue(is_array($rpcResult), sprintf(
            'The RPC response must be a array, but "%s" given.',
            gettype($rpcResult)
        ));

        if ($count !== null) {
            Assertions::assertCount((int) $count, $rpcResult, sprintf(
                'The response should have %d elements, but %d given.',
                $count,
                count($rpcResult)
            ));
        }

        foreach($table->getHash() as $key => $row) {
            if (isset($row['__key__'])) {
                $key = $row['__key__'];
                unset ($row['__key__']);
            }

            Assertions::assertArrayHasKey($key, $rpcResult, sprintf(
                'Not found data with key "%s" in array (%s).',
                $key,
                json_encode($rpcResult)
            ));

            $this->assertArrayEquals($rpcResult[$key], $row);
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

        $params = $params->getRowsHash();
        $requestParameters = [];

        foreach ($params as $parameterKey => $parameterValue) {
            $property = new PropertyPath($parameterKey);
            $propertyElements = $property->getElements();

            $activeKeyReference = &$requestParameters;

            while (null !== $propertyElement = array_shift($propertyElements)) {
                if (!isset($activeKeyReference[$propertyElement])) {
                    $activeKeyReference[$propertyElement] = []; // Force set array value
                }

                $activeKeyReference = &$activeKeyReference[$propertyElement];
            }

            if (preg_match('/^@\[([^\]]+)?\]$/', trim($parameterValue), $parts)) {
                if (!empty($parts[1])) {
                    $elements = explode(',', $parts[1]);
                    $elements = array_map('trim', $elements);
                } else {
                    // Empty string
                    $elements = [];
                }

                $parameterValue = $elements;
            }

            $activeKeyReference = $parameterValue;
        }

        $this->request = $this->client->request($id, $method, $requestParameters);
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
    protected function sendRequest()
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
     * Check array equals (For check JSON-RPC responses)
     *
     * @param array $actual   The data from JSON-RPC response
     * @param array $expected The array data from TableHash
     */
    protected function assertArrayEquals(array $actual, array $expected)
    {
        $propertyAccessor = new PropertyAccessor();

        foreach ($expected as $key => $needle) {
            $expectedValue = $expected[$key];

            // Fix type
            if ($expectedValue == '@true') {
                $expectedValue = true;
            } else if ($expectedValue == '@false') {
                $expectedValue = false;
            } else if (preg_match('/^@\[([^\]]+)?\]$/', $expectedValue, $parts)) {
                if (!empty($parts[1])) {
                    $expectedValue = explode(',', $parts[1]);
                    $expectedValue = array_map('trim', $expectedValue);
                } else {
                    $expectedValue = [];
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

            Assertions::assertArrayHasKey($rootKey, $actual, sprintf(
                'Not found key "%s" in array (%s).',
                $rootKey,
                json_encode($actual)
            ));

            Assertions::assertEquals($expectedValue, $actualValue, sprintf(
                'Failed assert equals for key "%s". Expected: "%s"; Actual: "%s". Body: "%s"',
                $key,
                is_scalar($expectedValue) ? $expectedValue : (is_array($expectedValue) ? 'Array(' . json_encode($expectedValue) . ')' : 'Invalid type'),
                is_scalar($actualValue) ? $actualValue : (is_array($actualValue) ? 'Array(' . json_encode($actualValue) . ')' : 'Invalid type'),
                $this->response->getBody()
            ));
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
