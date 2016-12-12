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

$middlewareTimeBlocking = function (RequestInterface $request, callable $next) {
    // This middleware only allows the call of callback function
    // only between 00:00 am and 01:00 am
    if ((int)date('Hi') < 100 && (int)date('Hi') > 0) {
        return $next($request);
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

$middlewareAddLanguageHeader = function (RequestInterface $request, callable $next) {
    $request = $request->withAddedHeader('Language', 'german');
    return $next($request);
};

$middlewareAddDateHeaderToResponse = function (RequestInterface $request, callable $next) {
    $response = $next($request);
    $response = $response->withAddedHeader('Date', date('Y-m-d'));
    return $response;
};

$server = new HttpServer($socket, $callback);
$server->addMiddleware($middlewareTimeBlocking);
$server->addMiddleware($middlewareAddLanguageHeader);
$server->addMiddleware($middlewareAddDateHeaderToResponse);

$loop->run();
