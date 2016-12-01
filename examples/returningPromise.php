<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use React\Promise\Promise;
use RingCentral\Psr7\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$callback = function() use ($loop) {
    return new Promise(function($resolve, $reject) use ($loop) {
        // Some heavy caluclations
        $loop->addTimer(2, function () use ($resolve){
            $resolve(new Response());
        });
    });
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
