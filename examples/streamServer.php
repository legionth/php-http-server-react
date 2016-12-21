<?php

use Legionth\React\Http\HttpServer;
use Legionth\React\Http\HttpBodyStream;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use React\Stream\ReadableStream;

require __DIR__ . '/../vendor/autoload.php';


$loop = React\EventLoop\Factory::create();

$callback = function(RequestInterface $request) use ($loop){
    $input = new ReadableStream();

    $periodicTimer = $loop->addPeriodicTimer(0.5, function () use ($input) {
        $input->emit('data', array("6\r\nworld\n\r\n"));
    });

    $loop->addTimer(3, function () use ($input, $periodicTimer) {
        $periodicTimer->cancel();
        $input->emit('data', array("3\r\nend\r\n0\r\n\r\n"));
        $input->emit('end');
    });

    $body = new HttpBodyStream($input);

    return new Response(
        200,
        array(
            'Transfer-Encoding' => 'chunked',
            'Connection' => 'keep-alive'
        ),
        $body);
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
