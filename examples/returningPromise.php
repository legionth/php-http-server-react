<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use React\Promise\Promise;
use RingCentral\Psr7\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$pseudoHeavyCalculation = function ($resolve) use ($loop){
    // Needs 2 seconds to answer a reequest caused by "calculations"
    $loop->addTimer(2, function () use ($resolve){
        $resolve(new Response());
    });
};

$callback = function() use ($loop, $pseudoHeavyCalculation) {
    return new Promise(function($resolve, $reject) use ($loop, $pseudoHeavyCalculation) {
        $pseudoHeavyCalculation($resolve);
    });
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
