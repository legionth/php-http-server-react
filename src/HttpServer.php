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

class HttpServer extends EventEmitter
{

	private $socket;

	private $callback;

	/**
	 *
	 * @param ServerInterface $socket
	 *        	- the server runs on this socket (ip address and port)
	 * @param callable $callback
	 *        	- callback function which returns a RingCentral\Psr7\Response
	 */
	public function __construct(ServerInterface $socket, callable $callback)
	{
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
	 * @param ConnectionInterface $connection
	 *        	- client-server connection stream
	 */
	public function handleConnection(ConnectionInterface $connection)
	{
		echo "on connect\n";
		$headerBuffer = '';
		$bodyBuffer = '';
		$headerCompleted = false;
		$bodyCompleted = false;
		
		$connection->on('data', function ($data) use ($connection, &$headerBuffer, &$bodyBuffer, &$headerCompleted) {
			$callback = $this->callback;
			
			if (!$headerCompleted) {
				$headerBuffer .= $data;
				if (strpos($headerBuffer, "\r\n\r\n")) {
					$headerComplete = substr($headerBuffer, 0, strpos($data, "\r\n\r\n") + 4);
					$request = RingCentral\Psr7\parse_request($headerComplete);
					$headerBuffer = '';
					$headerCompleted = true;
					$data = str_replace($headerComplete, '', $data);
				}
			}
			
			if (isset($request)) {
				print_r($request->getHeader('Content-Length'));
				
				if (empty($request->getHeader('Content-Length')) || $request->getHeader('Content-Length')[0] == 0) {
					$response = $callback($request);
					$connection->write(RingCentral\Psr7\str($response));
					return;
				}
				
				$bodyBuffer .= $data;

				if (strlen($bodyBuffer) == $request->getHeader('Content-Length')[0]) {
					$request = $request->withBody(RingCentral\Psr7\stream_for($bodyBuffer));
					$response = $callback($request);
					$connection->write(RingCentral\Psr7\str($response));
					return;
				}
			}
		});
	}
}
