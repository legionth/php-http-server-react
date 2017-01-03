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
     * @param ReadableStreamInterface $input - The data from this stream will be forwarded
     *                                         to the $encoder. The $encoder can be commited
     *                                         via the second parameter of this constructor
     * @param ReadableStreamInterface $encoder - This is a optional parameter which
     *                                           can be an encoding stream. This encoder
     *                                           will encode the data of $input. The
     *                                           default is a `ChunkedEncoderStream`
     *                                           which will encode the incoming data of
     *                                           $input as HTTP chunks.
     */
    public function __construct(ReadableStreamInterface $input, ReadableStreamInterface $encoder = null)
    {
        $this->input = $input;

        if ($encoder === null) {
            $encoder = new ChunkedEncoderStream($this->input);
        }
        $this->encoder = $encoder;

        $this->encoder->on('data', array($this, 'handleData'));
        $this->encoder->on('end', array($this, 'handleEnd'));
        $this->encoder->on('error', array($this, 'handleError'));
        $this->encoder->on('close', array($this, 'close'));
    }

    public function getEncoder()
    {
        return $this->encoder;
    }

    /**
     * Emits the data
     * @param string $data - data to be emitted
     */
    public function handleData($data)
    {
        $this->emit('data', array($data));
    }

    /**
     * Handles occuring exceptions on the stream
     * @param \Exception $e
     */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /**
     * Handles the end of the stream
     */
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    public function __toString()
    {
        return '';
    }

    public function detach()
    {}

    public function getSize()
    {
        return 0;
    }

    public function tell()
    {
        throw new \BadMethodCallException();
    }

    public function eof()
    {
        throw new \BadMethodCallException();
    }

    public function isSeekable()
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        return false;
    }

    public function rewind()
    {
        return;
    }

    public function isWritable()
    {
        return false;
    }

    public function write($string)
    {
        throw new \BadMethodCallException();
    }

    public function read($length)
    {
        throw new \BadMethodCallException();
    }

    public function getContents()
    {
        return '';
    }

    public function getMetadata($key = null)
    {
        return array();
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

        $this->readable = false;

        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->removeAllListeners();
    }
}
