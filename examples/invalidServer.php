<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;

require __DIR__ . '/../vendor/autoload.php';

$callback = function() {
    throw new Exception();
};

$loop = React\EventLoop\Factory::create();

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
