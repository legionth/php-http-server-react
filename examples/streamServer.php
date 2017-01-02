<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use Psr\Http\Message\RequestInterface;
use React\Stream\ReadableStream;
use Legionth\React\Http\StreamedResponse;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$callback = function(RequestInterface $request) use ($loop){
    $stream = new ReadableStream();

    $periodicTimer = $loop->addPeriodicTimer(0.5, function () use ($stream) {
        $stream->emit('data', array("world\n"));
    });

    $loop->addTimer(3, function () use ($stream, $periodicTimer) {
        $periodicTimer->cancel();
        $stream->emit('end', array(''));
    });

    return new StreamedResponse(
        $stream
    );
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
