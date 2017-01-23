<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$callback = function (RequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        $contentLength = 0;

        $request->getBody()->on('data', function ($chunk) use ($resolve, &$contentLength) {
            $contentLength += strlen($chunk);
        });

        $request->getBody()->on('end', function () use (&$contentLength, $resolve) {
            $content = '
<html>
<body>
    <h1> Hello World! </h1>
    <p> This is your own little server. Written in PHP :-) </p>
    <p><b> Transferred data length: ' . $contentLength . '</b></p>
</body>
</html>';

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
