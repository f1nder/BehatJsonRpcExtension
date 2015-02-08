<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'JsonRpcException.php';

$app = new Silex\Application();
$app['debug'] = true;
$app->match(
    'json-rpc',
    function (Request $req) {

        $server = new Zend\Json\Server\Server();
        $server->addFunction('app', 'test');
        $server->addFunction('errorWithData', 'test');
        $server->setReturnResponse(true);

        if ('GET' == $req->getMethod()) {
            return new JsonResponse($server->getServiceMap()->toArray());
        }

        $request = new \Zend\Json\Server\Request\Http();
        $request->setParams(['params' => $request->getParams()]);
        $content = $server->handle($request);

        file_put_contents('../server.log', $content . PHP_EOL, FILE_APPEND);

        return new Response($content);
    }
);

function app($params)
{
    return $params;
}

function errorWithData($params)
{
    throw new \JsonRpcException($params['message'], $params['errorCode'], json_decode($params['data'], true));
}

$app->run();