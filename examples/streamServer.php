<?php

use Legionth\React\Http\HttpServer;
use Legionth\React\Http\HttpBodyStream;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use React\Stream\ReadableStream;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$callback = function(RequestInterface $request) use ($loop){
    $stream = new ReadableStream();

    $periodicTimer = $loop->addPeriodicTimer(0.5, function () use ($stream) {
        $stream->emit('data', array("world\n"));
    });

    $loop->addTimer(3, function () use ($stream, $periodicTimer) {
        $periodicTimer->cancel();
        $stream->emit('end', array('end'));
    });

    $responseBody = new HttpBodyStream($stream);

    $promise = new Promise(function ($resolve, $reject) use ($request, &$responseBody) {
        $request->getBody()->on('end', function () use ($resolve, &$responseBody){
            $resolve(new Response(200, array(), $responseBody));
        });
    });

    return $promise;
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
