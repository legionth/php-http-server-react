<?php

use React\Socket\Server as Socket;
use Legionth\React\Http\HttpServer;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Request;
use React\Socket\Connection;
use React\Promise\Promise;
use Psr\Http\Message\RequestInterface;

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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testIsNotCallable()
    {
        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, 'not correct');
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

    public function testCallbackFunctionReturnsPromiseAndServerResponsesWithOkMessage()
    {
        $callback = function () {
            return new Promise(function ($resolve, $reject) {
                $resolve(new Response());
            });
        };

        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testPromiseReturnsInvalidValueAndServerResponsesWhithInternalErrorMessage()
    {
        $callback = function () {
            return new Promise(function ($resolve, $reject) {
                $resolve('Invalid');
            });
        };

        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 500 Internal Server Error\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testPromiseThrowsExceptionAndServerResponsesWithInternalServer()
    {
        $callback = function () {
            return new Promise(function ($resolve, $reject) {
                throw new Exception();
            });
        };

        $request = "GET /ip HTTP/1.1\r\nHost: httpbin.org\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 500 Internal Server Error\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testAddOneMiddleware()
    {
        $callback = function (RequestInterface $request) {
            $headerArray = $request->getHeader('From');
            if (empty($headerArray)) {
                throw new Exception();
            }

            return new Response();
        };

        $middleware = function (RequestInterface $request, $next) {
            if (!is_callable($next)) {
                throw new Exception();
            }

            $request = $request->withAddedHeader('From', 'user@example.com');

            return $next($request);
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);
        $server->addMiddleware($middleware);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testAddTwoMiddlwares()
    {
        $callback = function (RequestInterface $request) {
            $headerArray = $request->getHeader('From');
            // Second middlware should remove the header added by the first middleware
            if (empty($headerArray)) {
                return new Response();
            }
            throw new Exception();
        };

        $middleware = function (RequestInterface $request, $next) {
            if (!is_callable($next)) {
                throw new Exception();
            }

            $request = $request->withAddedHeader('From', 'user@example.com');

            return $next($request);
        };

        $middlewareTwo = function (RequestInterface $request, $next) {
            if (!is_callable($next)) {
                throw new Exception();
            }

            $request = $request->withoutHeader('From');

            return $next($request);
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);
        $server->addMiddleware($middleware);
        $server->addMiddleware($middlewareTwo);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }

    public function testMiddlewareReturnsForbiddenMessage()
    {
        $callback = function (RequestInterface $request) {
            return new Response();
        };

        $middleware = function (RequestInterface $request, $next) {
            if (!is_callable($next)) {
                throw new Exception();
            }

            $host = $request->getHeader('Host');
            if ($host[0] == "me.you") {
                return new Response(400);
            }
            return $next($request);
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);
        $server->addMiddleware($middleware);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 400 Bad Request\r\n\r\n"));
        $this->connection->emit('data', array($request));
    }
}
