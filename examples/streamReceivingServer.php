<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$callback = function(RequestInterface $request) {

    $body = $request->getBody();

    return new Promise(function ($resolve, $reject) use ($body) {
        $bodyContent = '';

        $body->on('data', function ($chunk) use ($resolve, &$bodyContent) {
            $bodyContent .= $chunk;
        });

            $body->on('end', function () use (&$bodyContent, $resolve) {
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

$loop = React\EventLoop\Factory::create();

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
