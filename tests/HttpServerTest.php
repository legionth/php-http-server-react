<?php

use React\Socket\Server as Socket;
use Legionth\React\Http\HttpServer;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Request;
use React\Socket\Connection;
use React\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Legionth\React\Http\HttpBodyStream;
use React\Stream\ReadableStream;

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

        $this->connection = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'write',
                    'end',
                    'close',
                    'pause',
                    'resume',
                    'isReadable',
                    'isWritable'
                )
            )
            ->getMock();
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

    public function testChunkedEncodingIsSetted()
    {
        $callback = function(RequestInterface $request) {
            $stream = new ReadableStream();

            $body = new HttpBodyStream($stream);

            return new Response(
                200,
                array(
                    'Transfer-Encoding' => 'chunked'
                ),
                $body);
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n"));

        $this->connection->emit('data', array($request));
    }

    public function testStreamDataNoChunkedEncodingSetted()
    {
        $callback = function(RequestInterface $request) {
            $stream = new ReadableStream();

            $body = new HttpBodyStream($stream);

            return new Response(
                200,
                array(),
                $body);
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n"));

        $this->connection->emit('data', array($request));
    }

    public function testTwoTypesOfEncodingForStreaming()
    {
        $callback = function(RequestInterface $request) {
            $stream = new ReadableStream();

            $body = new HttpBodyStream($stream);

            return new Response(
                200,
                array('Transfer-Encoding' => 'another'),
                $body);
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n"));

        $this->connection->emit('data', array($request));
    }
    public function testNonNumericContentLengthResultsInError()
    {
        $callback = function(RequestInterface $request) {
            return new Response();
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\nContent-Length: bla\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 400 Bad Request\r\n\r\n"));

        $this->connection->emit('data', array($request));
    }

    public function testMultipleValuesInContentLengthResultsInError()
    {
        $callback = function(RequestInterface $request) {
            return new Response();
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\nContent-Length: 1\r\nContent-Length: 2\r\n\r\n";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 400 Bad Request\r\n\r\n"));

        $this->connection->emit('data', array($request));
    }

    public function testContentLenghtWithoutValueButBodyWillBeCutted()
    {
        $callback = function(RequestInterface $request) {
            $promise = new Promise(function ($resolve, $reject) use ($request) {
                $request->getBody()->on('end', function () use ($resolve) {
                    $resolve(new Response());
                });
            });

            return $promise;
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\n\r\nhello";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));

        $this->connection->emit('data', array($request));
    }

    public function testBiggerBodyThanContentLengthWillBeCutted()
    {
        $callback = function(RequestInterface $request) {
            $promise = new Promise(function ($resolve, $reject) use ($request) {
                $body = $request->getBody();
                $content = '';

                $body->on('data', function ($data) use (&$content) {
                    $content .= $data;
                });

                $body->on('end', function () use (&$content, $resolve) {
                    $resolve(new Response(
                        200,
                        array('Content-Length' => strlen($content)),
                        $content
                    ));
                });
            });

            return $promise;
        };

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\nContent-Length: 5\r\n\r\nhello world";

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\nContent-Length: 5\r\n\r\nhello"));

        $this->connection->emit('data', array($request));
    }

    public function testContentLengthInRequestIsZero()
    {
        $callback = function(RequestInterface $request) {
            $promise = new Promise(function ($resolve, $reject) use ($request) {
                $request->getBody()->on('end', function () use ($resolve) {
                    $resolve(new Response());
                });
            });

            return $promise;
        };

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));

        $request = "GET / HTTP/1.1\r\nHost: me.you\r\nContent-Length: 0\r\n\r\n";
        $this->connection->emit('data', array($request));
    }

    public function testDoubleClrfInBeginningOfHeaderWillResultInError()
    {
        $callback = function(RequestInterface $request) {
            throw new Exception();
        };

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 400 Bad Request\r\n\r\n"));
        $this->connection->emit('data', array("\r\n\r\n"));
    }

    public function testInvalidRequestResultesInError()
    {
        $callback = function(RequestInterface $request) {
            throw new Exception();
        };

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 400 Bad Request\r\n\r\n"));
        $this->connection->emit('data', array("bla\r\n\r\n"));
    }

    public function testSplittedHeader()
    {
        $this->socket->emit('connection', array($this->connection));
        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));
        $this->connection->emit('data', array("GET /ip HTTP/1.1\r\n"));
        $this->connection->emit('data', array("me.org\r\n\r\n"));
    }

    public function testStreamingSplittedContentLengthBody()
    {
        $callback = function(RequestInterface $request) {
            $promise = new Promise(function ($resolve, $reject) use ($request) {
                $length = 0;

                $request->getBody()->on('data', function ($data) use (&$length){
                    $length += strlen($data);
                });

                $request->getBody()->on('end', function() use ($resolve, &$length) {
                    $resolve(new Response(200, array('Content-Length' => strlen((string)$length)), (string)$length));
                });
            });

            return $promise;
        };

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\nContent-Length: 1\r\n\r\n5"));
        $this->connection->emit('data', array("GET /ip HTTP/1.1\r\n"));
        $this->connection->emit('data', array("Content-Length: 5\r\n"));
        $this->connection->emit('data', array("me.org\r\n\r\n"));
        $this->connection->emit('data', array("hel"));
        $this->connection->emit('data', array("lo"));
    }

    public function testStreamingSplittedChunkedEncodingBody()
    {
        $callback = function(RequestInterface $request) {
            $promise = new Promise(function ($resolve, $reject) use ($request) {
                $length = 0;

                $request->getBody()->on('data', function ($data) use (&$length){
                    $length += strlen($data);
                });

                $request->getBody()->on('end', function() use ($resolve, &$length) {
                    $resolve(new Response(200, array('Content-Length' => strlen((string)$length)), (string)$length));
                });
            });

            return $promise;
        };

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n10"));
        $this->connection->emit('data', array("GET /ip HTTP/1.1\r\n"));
        $this->connection->emit('data', array("Transfer-Encoding: chunked\r\n"));
        $this->connection->emit('data', array("me.org\r\n\r\n"));
        $this->connection->emit('data', array("5\r\nhel"));
        $this->connection->emit('data', array("lo\r\n"));
        $this->connection->emit('data', array("5\r\nworld\r\n"));
        $this->connection->emit('data', array("0\r\n\r\n"));
    }

    public function testCloseHttpBodyStreamWontCloseConnection()
    {
        $callback = function(RequestInterface $request) {
            $promise = new Promise(function ($resolve, $reject) use ($request) {
                $request->getBody()->on('end', function() use ($resolve) {
                    $resolve(new Response());
                });
                $request->close();
            });

            return $promise;
        };

        $socket = new Socket($this->loop);
        $server = new HttpServer($socket, $callback);

        $socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 400 Bad Request\r\n\r\n"));
        $this->connection->expects($this->never())->method('close');

        $this->connection->emit('data', array("\r\n\r\n"));
    }
}
