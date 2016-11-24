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

class HttpServer extends EventEmitter
{

	private $socket;
	private $callback;
	private $errorResponse;

	/**
	 *
	 * @param ServerInterface $socket - the server runs on this socket (ip address and port)
	 * @param callable $callback - callback function which returns a RingCentral\Psr7\Response
	 */
	public function __construct(ServerInterface $socket, $callback, $errorResponse = null)
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

		if ($errorResponse === null) {
		    $errorResponse = new Response(500);
		}
		$this->errorResponse = $errorResponse;
	}

	/**
	 * Handles the requests of a client
	 *
	 * @param ConnectionInterface $connection
	 *        	- client-server connection stream
	 */
	public function handleConnection(ConnectionInterface $connection)
	{
		$bodyBuffer = '';
		$headerCompleted = false;
		$bodyCompleted = false;
		$that = $this;
		$callback = $this->callback;
		$errorResponse = $this->errorResponse;

		$chunkStream = new ReadableStream();
		$chunkedDecoder = new ChunkedDecoder($chunkStream);

		$headerStream = new ReadableStream();
		$headerDecoder = new HeaderDecoder($headerStream);

		$connection->on('data', function ($data) use ($connection, &$headerCompleted, &$bodyBuffer, $that, &$chunkedDecoder, &$headerDecoder, $chunkStream, $headerStream, $callback, $errorResponse) {
		    $promise = new Promise(function ($resolve, $reject) use ($data, $connection, &$headerCompleted, &$bodyBuffer, $that, &$chunkedDecoder, &$headerDecoder, $chunkStream, $headerStream, $callback)
		    {
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
    		            $chunkedDecoder->on('data', function ($chunk) use (&$bodyBuffer, &$request, $that, $connection, $callback, $resolve) {
    		                $bodyBuffer .= $chunk;
    		                if (strlen($chunk) == 0) {
    		                    $request = $request->withBody(RingCentral\Psr7\stream_for($bodyBuffer));
    		                    $resolve($callback($request));
		                    }
    		            });
		                $chunkStream->emit('data', array($data));
    		        }
    		        else {
    		            $bodyBuffer .= $data;
    		            $contentLengthArray = $request->getHeader('Content-Length');

    		            if (!empty($contentLengthArray) && strlen($bodyBuffer) == $contentLengthArray[0]) {
    		                $request = $request->withBody(RingCentral\Psr7\stream_for($bodyBuffer));
    		                $resolve($callback($request));
    		            } else if (empty($contentLengthArray) || $contentLengthArray[0] == 0) {
    		                $resolve($callback($request));
    		            }
    		        }
    		    }
            });

		    $promise->then(function ($response) use ($connection) {
		        $connection->write(RingCentral\Psr7\str($response));
		        $connection->end();
		    }, function () use ($connection, $errorResponse) {
		        $connection->write(RingCentral\Psr7\str($errorResponse));
		        $connection->end();
		    });
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
}
