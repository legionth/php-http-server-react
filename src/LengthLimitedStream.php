<?php

namespace Legionth\React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class LengthLimitedStream extends EventEmitter implements ReadableStreamInterface
{
    private $stream;
    private $closed = false;
    private $encoder;
    private $transferredLength = 0;
    private $maxLength;

    /**
     * @param ReadableStreamInterface $input - Stream data from $stream as a body of a PSR-7 object
     */
    public function __construct(ReadableStreamInterface $stream, $maxLength)
    {
        $this->stream = $stream;
        $this->maxLength = $maxLength;

        $this->stream->on('data', array($this, 'handleData'));
        $this->stream->on('end', array($this, 'handleEnd'));
        $this->stream->on('error', array($this, 'handleError'));
        $this->stream->on('close', array($this, 'close'));
    }

    public function isReadable()
    {
        return !$this->closed && $this->stream->isReadable();
    }

    public function pause()
    {
        $this->stream->pause();
    }

    public function resume()
    {
        $this->stream->resume();
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

        $this->readable = false;

        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleData($data)
    {
        if ($this->transferredLength === $this->maxLength) {
            if ($this->maxLength === 0) {
                $this->emit('end', array());
            }
            $this->stream->removeListener('data', array($this, 'handleData'));
            // Ignore if the maximum length is reached
            return;
        }

        if (($this->transferredLength + strlen($data)) > $this->maxLength) {
            // Only emit data until the value of 'Content-Length' is reached, the rest will be ignored
            $data = (string)substr($data, 0, $this->maxLength - $this->transferredLength);
        }

        if ($data !== '') {
            $this->transferredLength += strlen($data);
            $this->emit('data', array($data));
        }

        if ($this->transferredLength === $this->maxLength) {
            // 'Content-Length' reached, stream will end
            $this->emit('end', array());
        }
    }

    /** @internal */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /** @internal */
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

}
