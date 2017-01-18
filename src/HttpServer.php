<?php
namespace Legionth\React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;
use React\Stream\Stream;
use React\Socket\Connection;
use RingCentral\Psr7;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Request;
use React\Socket\ConnectionInterface;
use RingCentral;
use React\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Stream\ReadableStreamInterface;

class HttpServer extends EventEmitter
{

    private $socket;
    private $callback;
    private $middlewares;

    /**
     *
     * @param ServerInterface $socket - the server runs on this socket (ip address and port)
     * @param callable $callback - callback function which returns a RingCentral\Psr7\Response
     */
    public function __construct(ServerInterface $socket, $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('The given parameter is not callable');
        }

        $this->socket = $socket;
        $this->callback = $callback;

        $this->socket->on(
            'connection',
            array(
                $this,
                'handleConnection'
            )
        );

        $this->middlewares = array();
    }

    public function addMiddleware($middleware)
    {
        if (!is_callable($middleware)) {
            throw new \InvalidArgumentException('The given parameter is not callable');
        }

        $this->middlewares[] = $middleware;
    }

    /**
     * Handles the requests of a client
     *
     * @param ConnectionInterface $connection - client-server connection stream
     */
    public function handleConnection($connection)
    {
        $that = $this;
        $headerBuffer = '';

        $listener =  function ($data) use (&$connection, $that, &$headerBuffer, &$listener) {
            $headerBuffer .= $data;
            if (strpos($headerBuffer, "\r\n\r\n") !== false) {
                $connection->removeListener('data', $listener);
                // header is completed
                $fullHeader = (string)substr($headerBuffer, 0, strpos($headerBuffer, "\r\n\r\n") + 4);

                try {
                    $request = RingCentral\Psr7\parse_request($fullHeader);
                } catch (\Exception $ex) {
                    $that->sendResponse(new Response(400), $connection);
                    return;
                }

                if ($request->hasHeader('Content-Length')) {
                    $contentLength = $request->getHeaderLine('Content-Length');

                    $int = (int) $contentLength;
                    if ((string)$int !== (string)$contentLength) {
                        // Send 400 status code if the value of 'Content-Length'
                        // is not an integer or is duplicated
                        $that->sendResponse(new Response(400), $connection);
                        return;
                    }
                }

                $that->handleBody($request, $connection);

                // remove header from $data, only body is left
                $data = (string)substr($data, strlen($fullHeader));
                if ($data !== '') {
                    $connection->emit('data', array($data));
                }
            }
        };

        $connection->on('data', $listener);
    }

    /** @internal */
    public function handleBody(RequestInterface $request, ConnectionInterface $connection)
    {
        if ($this->isChunkedEncodingActive($request)) {
            // Add ChunkedDecoder to stream
            $chunkedDecoder = new ChunkedDecoder($connection, $this);
            $bodyStream = new HttpBodyStream($chunkedDecoder);
            $request = $request->withBody($bodyStream);
            $this->handleRequest($connection, $request);
            return;
        }

        $contentLength = (int)$request->getHeaderLine('Content-Length');

        $stream = new LengthLimitedStream($connection, $contentLength);
        $bodyStream = new HttpBodyStream($stream);

        $request = $request->withBody($bodyStream);
        $this->handleRequest($connection, $request);

        if (!$request->hasHeader('Content-Length')) {
            // Request hasn't defined 'Content-Length' will ignore rest of the request
            // and ends the stream
            $bodyStream->emit('end', array());
        }
    }

    /** @internal */
    public function sendResponse(ResponseInterface $response, ConnectionInterface &$connection)
    {
        $connection->write(RingCentral\Psr7\str($response));
        $connection->end();
    }

    /**
     * Checks if the 'Transfer-Encoding: chunked' is set anywhere in the header
     * @param Request $request - user request object containing the header
     * @return boolean
     */
    public function isChunkedEncodingActive(Request $request)
    {
        $transferEncodingArray = $request->getHeader('Transfer-Encoding');

        if (!empty($transferEncodingArray)) {
            foreach ($transferEncodingArray as $value) {
                if ($value == 'chunked') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Processes the request by the given callback functions and writes the responses on the connection stream
     *
     * @param ConnectionInterface $connection - connection between user and server, the response will be written
     *                                          on this connection
     * @param Request $request - User request to be handled by the callback function
     */
    public function handleRequest(ConnectionInterface $connection, Request $request)
    {
        $callback = $this->callback;

        try {
            $response = $this->executeMiddlewareChain($this->middlewares, $request, $callback);

            $promise = $response;
            if (!$promise instanceof Promise) {
                $promise = new Promise(function($resolve, $reject) use ($response){
                    return $resolve($response);
                });
            }



            $this->handlePromise($connection, $promise, $request);
        } catch (\Exception $exception) {
            $connection->write(RingCentral\Psr7\str(new Response(500)));
            $connection->end();
        }
    }

    /**
     * Executes the middlware chain and the callback function to receive the response object
     *
     * @param array $middlewareChain - middleware chain to execute
     * @param RequestInterface $request - initial request from the client
     * @param callable $callback - user callback function, last chain link
     * @return Response returns the response object handled by different middlwares or only the callback function
     */
    private function executeMiddlewareChain(array $middlewareChain, RequestInterface $request, $callback)
    {
        $firstCallback = array_shift($middlewareChain);

        $next = function (Request $request) use (&$middlewareChain, $callback, &$next) {
            if (empty($middlewareChain)) {
                return $callback($request);
            }

            $current = array_shift($middlewareChain);
            return $current($request, $next);
        };

        if ($firstCallback === null) {
            $firstCallback = $callback;
        }

        return $firstCallback($request, $next);
    }

    /**
     * Handles a promise
     *
     * @param Connection $connection - connection between server and client
     * @param Promise $promise - Promise returned by the callback function of the server
     */
    private function handlePromise(Connection $connection, Promise $promise, RequestInterface $request)
    {
        $that = $this;

        $promise->then(
            function ($response) use ($connection, $promise, $that, $request){
                $responseString = RingCentral\Psr7\str(new Response(500));
                if ($response instanceof Response) {
                    $body = $response->getBody();
                    if ($body instanceof ReadableStreamInterface) {
                        // reset Transfer-Encoding header and set always chunked encoding
                        $response = $response->withHeader('Transfer-Encoding', 'chunked');
                        // Send the header first without the body,
                        // the body will be streamed
                        $emptyBody = RingCentral\Psr7\stream_for('');
                        $response = $response->withBody($emptyBody);

                        $connection->write(RingCentral\Psr7\str($response));
                        $chunkedEncoder = new ChunkedEncoderStream($body);
                        $chunkedEncoder->pipe($connection);

                        return;
                    }
                    $responseString = RingCentral\Psr7\str($response);
                }

                $connection->write($responseString);

                if (!$that->isConnectionPersistent($request)) {
                    $connection->end();
                    return;
                }
            },
            function () use ($connection) {
                $connection->write(RingCentral\Psr7\str(new Response(500)));
                $connection->end();
            }
        );
    }

    private function isConnectionPersistent(RequestInterface $request)
    {
        $keepAlive = false;

        if ($request->getProtocolVersion() === '1.1') {
            $keepAlive = true;
        }

        if ($request->hasHeader('Connection')) {
            $connectionLine = $request->getHeaderLine('Connection');
            if ($connectionLine === 'keep-alive') {
                $keepAlive = true;
            } elseif ($connectionLine === 'close') {
                $keepAlive = false;
            }
        }

        return $keepAlive;
    }
}
