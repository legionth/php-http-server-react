<?php
use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Request;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$callback = function (Request $request) use ($loop) {
    throw new Exception("Internal server error. Contact the system admin\n");
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');
$message = 'This should not happen. Sorry!';

$failResponse = new Response(
    503,
    array('Content-Length' => $message),
    $message
);

$server = new HttpServer($socket, $callback, $failResponse);
$loop->run();
