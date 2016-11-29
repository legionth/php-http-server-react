<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use React\Promise\Promise;
use RingCentral\Psr7\Response;

require __DIR__ . '/../vendor/autoload.php';

$callback = function() {
    return new Response();
};

$loop = React\EventLoop\Factory::create();

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
