<?php

use Legionth\React\Http\HttpServer;
use React\Socket\Server as Socket;
use Psr\Http\Message\RequestInterface;
use React\Promise\Promise;
use React\SocketClient\TcpConnector;
use React\SocketClient\DnsConnector;
use RingCentral\Psr7\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$callback = function(RequestInterface $request) use ($loop){
    return new Promise(function ($resolve, $reject) use ($request, $loop) {
        $connector = new TcpConnector($loop);
        $resolverFactory = new React\Dns\Resolver\Factory();
        $resolver = $resolverFactory->create('8.8.8.8', $loop);
        $dnsConnector = new DnsConnector($connector, $resolver);


        $host = $request->getHeader('Host')[0];
        $hostArray = parse_url($host);

        $port = 80;
        if (isset($hostArray['port'])) {
            $port = $hostArray['port'];
        }

        if (isset($hostArray['host'])) {
            $host = $hostArray['host'];
        }

        // Create to given Host:Port
        $dnsConnector->create($host, $port)->then(function ($stream) use ($request, $resolve){
            $body = $request->getBody();
            $body->on('data', function ($chunk) use ($resolve, $stream) {
                $stream->write($chunk);
            });

            $responseBuffer = '';
            $stream->on('data', function ($data) use (&$responseBuffer){
                $responseBuffer .= $data;
            });

            $stream->on('close', function () use ($resolve, &$responseBuffer){
                try {
                    $resolve(RingCentral\Psr7\parse_response($responseBuffer));
                } catch (\Exception $ex) {
                    $resolve(new Response(400));
                }
            });

            $stream->write(RingCentral\Psr7\str($request));
        });


    });
};

$socket = new Socket($loop);
$socket->listen(10001, 'localhost');

$server = new HttpServer($socket, $callback);

$loop->run();
