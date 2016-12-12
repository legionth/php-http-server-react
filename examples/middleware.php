<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Psr\Http\Message\RequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$callback = function($request) {
    $body = '
<html>
<body>
    <h1> Welcome to our midnight shop! </h1>
</body>
</html>';

    return new Response(
        200,
        array(
            'Content-Length' => strlen($body),
            'Content-Type' => 'text/html'
        ),
        $body);
};

$loop = React\EventLoop\Factory::create();

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$middlewareTimeBlocking = function (RequestInterface $request, array $callables) {
    // This middleware only allows the call of callback function
    // only between 00:00 am and 01:00 am
    if ((int)date('Hi') < 100 && (int)date('Hi') > 0) {
        $next = array_shift($callables);
        return $next($request, $callables);
    }
    
    $body = '
<html>
<body>
    <h1> Sorry the midnight shop is only open from midnight to 1 am! </h1>
</body>
</html>';
    
    return new Response(
        200,
        array(
            'Content-Length' => strlen($body),
            'Content-Type' => 'text/html'
        ),
        $body);
};

$server = new HttpServer($socket, $callback);
$server->addMiddleware($middlewareTimeBlocking);

$loop->run();
