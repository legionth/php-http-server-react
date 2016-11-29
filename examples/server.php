<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;

require __DIR__ . '/../vendor/autoload.php';

$callback = function($request) {
    $body = '
<html>
<body>
    <h1> Hello World! </h1>
    <p> This is your own little server. Written in PHP :-) </p>
    <p> The request to this server was: </p>' . nl2br(RingCentral\Psr7\str($request)) . '
</body>
</html>';
    

    return new Response(200, array('Content-Length' => strlen($body)), $body);
};

$loop = React\EventLoop\Factory::create();

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
