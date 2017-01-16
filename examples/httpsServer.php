<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use React\Socket\SecureServer;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$callback = function (RequestInterface $request) {
    $content = '<html>
<body>
    <h1> Hello World! </h1>
    <p> This is your own little HTTPS server. Written in PHP :-) </p>
</body>
</html>';

    return new Response(
        200,
        array(
            'Content-Length' => strlen($content),
            'Content-Type' => 'text/html'
        ),
        $content
        );
};

$socket = new Socket($loop);
$socket = new SecureServer($socket, $loop,
    array(
        'local_cert' => 'server.pem',
        'local_pk' => 'server.key'
    )
);
$socket->listen(10000, 'localhost');
$socket->on('error', function (Exception $e) {
    echo 'Error' . $e->getMessage() . PHP_EOL;
});

$server = new HttpServer($socket, $callback);

$loop->run();
