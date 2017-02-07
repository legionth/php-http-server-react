<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$callback = function(ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        $responseBody = $request->getBody();

        $bodyContent = '';

        $responseBody->on('data', function ($chunk) use (&$bodyContent) {
            $bodyContent .= $chunk;
        });

        $responseBody->on('end', function () use (&$bodyContent, $resolve) {
            $resolve(
                new Response(
                    200,
                    array(
                        'Content-Length' => strlen($bodyContent),
                        'Content-Type' => 'text/html'
                    ),
                    $bodyContent
                )
            );
        });
    });
};

$badWordFilterMiddleware = function (ServerRequestInterface $request, $next) {
    $responseBody = $request->getBody();

    $responseBody->on('data', function ($data, $responseBody) {
        echo "Data: " . $data . "\n";
        if ($data === 'nob') {
            $data = 'bob';
        }
        $responseBody->emit('data', array($data));
    });

    return $next($request);
};

$loop = React\EventLoop\Factory::create();

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);
$server->addMiddleware($badWordFilterMiddleware);

$loop->run();
