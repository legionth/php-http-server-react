<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$callback = function (RequestInterface $request) {
    $body = $request->getBody();

    return new Promise(function ($resolve, $reject) use ($body) {
        $contentLength = 0;
        $body->on('data', function ($chunk) use ($resolve, &$contentLength) {
            $contentLength += strlen($chunk);
        });

        $body->on('end', function () use (&$contentLength, $resolve) {
            $content = "Transferred data length: " . $contentLength ."\n";
            $resolve(
                new Response(
                    200,
                    array(
                        'Content-Length' => strlen($content),
                        'Content-Type' => 'text/html'
                    ),
                    $content
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
