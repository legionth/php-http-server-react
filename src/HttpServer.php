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
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Stream\ReadableStreamInterface;
use Psr\Http\Message\StreamInterface;

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
        $headerCompleted = false;
        $that = $this;

        $input = new ReadableStream();

        $headerStream = new ReadableStream();
        $headerDecoder = new HeaderDecoder($headerStream);

        $request = null;
        $headerSend = false;

        $contentSize = 0;

        $bodyStream = null;

        $connection->on('data', function ($data) use ($connection, &$headerCompleted, &$bodyBuffer, $that, &$headerDecoder, &$input, $headerStream, &$request, &$headerSend, &$contentSize, &$bodyStream) {
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
                    if (!$headerSend) {
                        // Send header without body to stream the data
                        $chunkedDecoder = new ChunkedDecoder($input);
                        $bodyStream = new HttpBodyStream($chunkedDecoder);
                        $that->sendBody($bodyStream, $connection, $request);
                        $headerSend = true;
                    }

                    $input->emit('data', array($data));
                } else {
                    $contentLengthArray = $request->getHeader('Content-Length');

                    if (count($contentLengthArray) > 1) {
                        $that->sendResponse(new Response(400), $connection);
                        return;
                    } elseif (empty($contentLengthArray) && $data == '') {
                        $that->handleRequest($connection, $request);
                        return;
                    } elseif (empty($contentLengthArray) ) {
                        $that->sendResponse(new Response(411), $connection);
                        return;
                    }

                    $contentLength = $contentLengthArray[0];

                    if (!is_numeric($contentLength)) {
                        $that->sendResponse(new Response(400), $connection);
                        return;
                    }

                    if (!$headerSend) {
                        // Send header without body to stream the data
                        $bodyStream = new HttpBodyStream($input);
                        $that->sendBody($bodyStream, $connection, $request);
                        $headerSend = true;
                    }

                    if (($contentSize + strlen($data)) > $contentLength) {
                        // Only emit data to 'Content-Length', the rest will be ignored
                        $data = substr($data, 0, $contentLength - $contentSize);
                        $input->emit('data', array($data));
                        $bodyStream->emit('end', array());
                        return;
                    }

                    $contentSize += strlen($data);
                    $input->emit('data', array($data));

                    if ($contentSize == $contentLength) {
                        $bodyStream->emit('end', array());
                        return;
                    }
                }
            }
        });
    }

    /**
     * Sends a response on the connection and ends the connection
     * @param ResponseInterface $response - Response message to be sent on the connection
     * @param ConnectionInterface $connection - Connection between user and server
     */
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
     * Handles a promise
     *
     * @param Connection $connection - connection between server and client
     * @param Promise $promise - Promise returned by the callback function of the server
     */
    private function handlePromise(Connection $connection, Promise $promise)
    {
        $that = $this;
        $promise->then(
            function ($response) use ($connection, $promise, $that){
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
     * @param StreamInterface $body - body to be added to the request object
     * @param ConnectionInterface $connection - client-server connection
     * @param Request $request - Adds the body to this request object
     */
    public function sendBody(StreamInterface $body, ConnectionInterface $connection, Request $request)
    {
        $request = $request->withBody($body);
        $this->handleRequest($connection, $request);
    }
}
