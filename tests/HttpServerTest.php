<?php

use React\Socket\Server as Socket;
use Legionth\React\Http\HttpServer;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Request;
use React\Socket\Connection;

class HttpServerTest extends TestCase
{
    private $httpServer;
    private $loop;
    private $socket;
    private $connection;

    public function setUp()
    {
        $this->loop = new React\EventLoop\StreamSelectLoop();
        $this->socket = new Socket($this->loop);
        $response = new Response();

        $callback = function ($request) use ($response) {
            return $response;
        };

        $this->httpServer = new HttpServer(
            $this->socket,
            $callback
        );

        $this->connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('write', 'end', 'close', 'pause', 'resume', 'isReadable', 'isWritable'))->getMock();
    }

    public function testRequestWithoutBody()
    {
        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\n\r\n";

        $this->socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testRequestWithContentLength()
    {
        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\nContent-Length: 3\r\n\r\n";
        $request .= "bla";

        $this->socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testRequestWithChunkedEncodig()
    {
        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\nTransfer-Encoding: chunked\r\n\r\n";
        $request .= "3\r\nbla\r\n0\r\n\r\n";

        $this->socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testCallbackFunctionThrowsException()
    {
        $request = "GET /something HTTP/1.1\r\nHost: example.org\r\n\r\n";

        $callback = function() {
            throw new Exception();
        };

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);
        $socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 500 Internal Server Error\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testWrongResponseType()
    {
        $request = "GET /something HTTP/1.1\r\nHost: example.org\r\n\r\n";

        $callback = function() {
            return "This is an invalid type";
        };

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);
        $socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 500 Internal Server Error\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }
}
