<?php

namespace Legionth\React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class HeaderDecoder extends EventEmitter implements ReadableStreamInterface
{
	private $closed = false;
	private $input;
	private $buffer = '';

	public function __construct(ReadableStreamInterface $input)
	{
		$this->input = $input;

		$this->input->on('data', array($this, 'handleData'));
		$this->input->on('end', array($this, 'handleEnd'));
		$this->input->on('error', array($this, 'handleError'));
		$this->input->on('close', array($this, 'close'));
	}

	public function handleData($data)
	{
		$this->buffer .= $data;
		if (strpos($this->buffer, "\r\n\r\n")) {
			$headerComplete = substr($this->buffer, 0, strpos($this->buffer, "\r\n\r\n") + 4);
			$this->buffer = '';
			$this->emit('data', array($headerComplete));
		}
	}

	/** @internal */
	public function handleEnd()
	{
		if (!$this->closed) {
			$this->emit('end');
			$this->close();
		}
	}

	/** @internal */
	public function handleError(\Exception $e)
	{
		$this->emit('error', array($e));
		$this->close();
	}

	public function isReadable()
	{
		return !$this->closed && $this->input->isReadable();
	}

	public function pause()
	{
		$this->input->pause();
	}

	public function resume()
	{
		$this->input->resume();
	}

	public function pipe(WritableStreamInterface $dest, array $options = array())
	{
		Util::pipe($this, $dest, $options);

		return $dest;
	}

	public function close()
	{
		if ($this->closed) {
			return;
		}

		$this->closed = true;
		$this->started = false;

		$this->input->close();

		$this->emit('close');
		$this->removeAllListeners();
	}
}
