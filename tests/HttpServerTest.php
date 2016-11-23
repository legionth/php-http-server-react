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
    private $regularResponse;
    private $connection;

    public function setUp()
    {
        $this->loop = new React\EventLoop\StreamSelectLoop();
        $this->socket = new Socket($this->loop);

        $this->regularResponse = new Response();
        $this->httpServer = new HttpServer(
            $this->socket,
            function ($request) {
                return $this->regularResponse;
            }
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
    
    public function testThrowException()
    {
        $loop = new React\EventLoop\StreamSelectLoop();
        $socket = new Socket($this->loop);
        
        $httpServer = new HttpServer(
            $socket,
            function ($request) {
                throw new Exception();
            },
            new Response(500, array('Content-Length' => 5), 'error')
        );
        
        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\n\r\n";
        $connection = new ConnectionStub();
        $socket->emit('connection', array($connection));
        $connection->emit('data', array($request));
        $this->assertSame("HTTP/1.1 500 Internal Server Error\r\nContent-Length: 5\r\n\r\nerror", $connection->getData());
        
    }
}
