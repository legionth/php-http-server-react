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
use React\Stream\ReadableStream;
use React\Promise\Promise;
use Legionth\React\Http\Middleware;
use Psr\Http\Message\RequestInterface;

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
        $this->socket->on('connection', array(
            $this,
            'handleConnection'
        ));

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
    public function handleConnection(ConnectionInterface $connection)
    {
        $bodyBuffer = '';
        $headerCompleted = false;
        $bodyCompleted = false;
        $that = $this;

        $chunkStream = new ReadableStream();
        $chunkedDecoder = new ChunkedDecoder($chunkStream);

        $headerStream = new ReadableStream();
        $headerDecoder = new HeaderDecoder($headerStream);

        $connection->on('data', function ($data) use ($connection, &$headerCompleted, &$bodyBuffer, $that, &$chunkedDecoder, &$headerDecoder, $chunkStream, $headerStream) {
            if (!$headerCompleted) {
                $headerDecoder->on('data', function ($header) use (&$request, &$data, &$headerCompleted) {
                    $request = RingCentral\Psr7\parse_request($header);
                    $data = substr($data, strlen($header));
                    $headerCompleted = true;
                });
                $headerStream->emit('data', array($data));
            }

            if (isset($request)) {
                if ($that->isChunkedEncodingActive($request)) {
                    $chunkedDecoder->on('data', function ($chunk) use (&$bodyBuffer, &$request, $that, $connection) {
                        $bodyBuffer .= $chunk;
                        if (strlen($chunk) == 0) {
                            $that->sendBody($bodyBuffer, $connection, $request);
                        }
                    });
                    $chunkStream->emit('data', array($data));
                } else {
                    $bodyBuffer .= $data;
                    $contentLengthArray = $request->getHeader('Content-Length');

                    if (!empty($contentLengthArray) && strlen($bodyBuffer) == $contentLengthArray[0]) {
                        $that->sendBody($bodyBuffer, $connection, $request);
                    } else if (empty($contentLengthArray) || $contentLengthArray[0] == 0) {
                        $that->handleRequest($connection, $request);
                    }
                }
            }
        });
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

            $this->handlePromise($connection, $promise);
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
     * Handles an promise
     *
     * @param Connection $connection - connection between server and client
     * @param Promise $promise - Promise returned by the callback function of the server
     */
    private function handlePromise(Connection $connection, Promise $promise)
    {
        $promise->then(
            function ($response) use ($connection, $promise){
                $responseString = RingCentral\Psr7\str(new Response(500));

                if ($response instanceof Response) {
                    $responseString = RingCentral\Psr7\str($response);
                }

                $connection->write($responseString);
                $connection->end();
            },
            function () use ($connection) {
                $connection->write(RingCentral\Psr7\str(new Response(500)));
                $connection->end();
            }
        );
    }

    /**
     * Adds the body to the request before handling the request
     *
     * @param string $body - body to be added to the request object
     * @param ConnectionInterface $connection - client-server connection
     * @param Request $request - Adds the body to this request object
     */
    public function sendBody($body, ConnectionInterface $connection, Request $request)
    {
        $request = $request->withBody(RingCentral\Psr7\stream_for($body));
        $this->handleRequest($connection, $request);
    }
}
