<?php

namespace Solution\JsonRpcApiExtension\Client;

use Graze\GuzzleHttp\JsonRpc\Client;
use Graze\GuzzleHttp\JsonRpc\Message\RequestInterface;
use Graze\GuzzleHttp\JsonRpc\Message\Response;
use GuzzleHttp\ClientInterface as HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use GuzzleHttp\Client as HttpClient;

class JsonRpcClient extends Client
{
    protected $kernel;


    public function __construct(HttpClientInterface $httpClient, KernelInterface $kernel = null)
    {
        $this->kernel = $kernel;
        parent::__construct($httpClient);
    }


    public function send(RequestInterface $request)
    {
        return parent::send($request);
    }

    /**
     * @param  string $url
     * @param  array $config
     * @return Client
     */
    public static function factory($url, array $config = [], $kernel = null)
    {
        return new self(
            new HttpClient(
                array_replace_recursive(
                    [
                        'base_url' => $url,
                        'message_factory' => self::createMessageFactory(),
                        'defaults' => [
                            'headers' => [
                                'Accept-Encoding' => 'gzip;q=1.0,deflate;q=0.6,identity;q=0.3'
                            ]
                        ]
                    ],
                    $config
                )
            )
        );
    }
}