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

class HttpServer extends EventEmitter
{

    private $socket;
    private $callback;

    /**
     *
     * @param ServerInterface $socket - the server runs on this socket (ip address and port)
     * @param callable $callback - callback function which returns a RingCentral\Psr7\Response
     */
    public function __construct(ServerInterface $socket, $callback)
    {
        if (!is_callable($callback)) {
            throw new \Exception('The given parametr is not callable');
        }

        $this->socket = $socket;
        $this->callback = $callback;
        $this->socket->on('connection', array(
            $this,
            'handleConnection'
        ));
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
                    $data = str_replace($header, '', $data);
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
        $response = $callback($request);
        $connection->write(RingCentral\Psr7\str($response));
        $connection->end();
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
