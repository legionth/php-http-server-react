<?php

use React\Socket\Server as Socket;
use Legionth\React\Http\HttpServer;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Request;
use React\Socket\Connection;
use Legionth\React\Tests\ConnectionStub;

class HttpServerTest extends TestCase
{
    private $httpServer;
    private $loop;
    private $socket;
    private $regularResponse;

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
    }
    
    public function testRequestWithoutBody()
    {
        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\n\r\n";
        $connection = new ConnectionStub();
        $this->socket->emit('connection', array($connection));
        $connection->emit('data', array($request));
        $this->assertSame("HTTP/1.1 200 OK\r\n\r\n", $connection->getWrittenData());
    }
    
    public function testRequestWithContentLength()
    {
        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\nContent-Length: 3\r\n\r\n";
        $request .= "bla";
        
        $connection = new ConnectionStub();
        $this->socket->emit('connection', array($connection));
        $connection->emit('data', array($request));
        $this->assertSame("HTTP/1.1 200 OK\r\n\r\n", $connection->getWrittenData());
    }
    
    public function testRequestWithChunkedEncodig()
    {
        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\nTransfer-Encoding: chunked\r\n\r\n";
        $request .= "3\r\nbla\r\n0\r\n\r\n";
        $connection = new ConnectionStub();
        $this->socket->emit('connection', array($connection));
        $connection->emit('data', array($request));
        $this->assertSame("HTTP/1.1 200 OK\r\n\r\n", $connection->getWrittenData());
    }
}
