<?php
namespace Legionth\React\Http;

use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use Evenement\EventEmitter;
use React\Stream\Util;

/**
 * Uses a StreamInterface from PSR-7 and a ReadableStreamInterface from ReactPHP
 * to use PSR-7 objects and use ReactPHP streams
 * This is class is used in `HttpServer` to stream the body of a response
 * to the client. Because of this you can stream big amount of data without
 * necessity of buffering this data. The data will be send directly to the client.
 */
class HttpBodyStream extends EventEmitter implements StreamInterface, ReadableStreamInterface
{
    private $input;
    private $closed = false;
    private $encoder;

    /**
     * @param ReadableStreamInterface $input - Stream data from $stream as a body of a PSR-7 object
     */
    public function __construct(ReadableStreamInterface $input)
    {
        $this->input = $input;

        $this->input->on('data', array($this, 'handleData'));
        $this->input->on('end', array($this, 'handleEnd'));
        $this->input->on('error', array($this, 'handleError'));
        $this->input->on('close', array($this, 'close'));
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

        $this->input->removeListener('data', array($this, 'handleData'));
        $this->input->removeListener('end', array($this, 'handleEnd'));
        $this->input->removeListener('error', array($this, 'handleError'));
        $this->input->removeListener('close', array($this, 'close'));

        $this->input->close();

        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->removeAllListeners();
    }

    /** @ignore */
    public function __toString()
    {
        return '';
    }

    /** @ignore */
    public function detach()
    {
        return null;
    }

    /** @ignore */
    public function getSize()
    {
        return null;
    }

    /** @ignore */
    public function tell()
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function eof()
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function isSeekable()
    {
        return false;
    }

    /** @ignore */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function rewind()
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function isWritable()
    {
        return false;
    }

    /** @ignore */
    public function write($string)
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function read($length)
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function getContents()
    {
        return '';
    }

    /** @ignore */
    public function getMetadata($key = null)
    {
        return null;
    }

    /** @internal */
    public function handleData($data)
    {
        $this->emit('data', array($data));
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
