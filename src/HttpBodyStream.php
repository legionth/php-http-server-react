<?php
namespace Legionth\React\Http;

use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use Evenement\EventEmitter;
use React\Stream\Util;

class HttpBodyStream extends EventEmitter implements StreamInterface, ReadableStreamInterface
{
    private $input;
    private $closed = false;

    public function __construct(ReadableStreamInterface $input)
    {
        $this->input = $input;

        $this->input->on('data', array($this, 'handleData'));
        $this->input->on('end', array($this, 'handleEnd'));
        $this->input->on('error', array($this, 'handleError'));
        $this->input->on('close', array($this, 'close'));
    }

    /**
     * @internal
     */
    public function handleData($data)
    {
        $this->emit('data', array($data));
    }

    /**
     * @internal
     */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /**
     * @internal
     */
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::__toString()
     */
    public function __toString()
    {
        return '';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::detach()
     */
    public function detach()
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::getSize()
     */
    public function getSize()
    {
        return 0;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::tell()
     */
    public function tell()
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::eof()
     */
    public function eof()
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::isSeekable()
     */
    public function isSeekable()
    {
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::seek()
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::rewind()
     */
    public function rewind()
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::isWritable()
     */
    public function isWritable()
    {
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::write()
     */
    public function write($string)
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::read()
     */
    public function read($length)
    {
        // TODO: Auto-generated method stub
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::getContents()
     */
    public function getContents()
    {
        return '';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\StreamInterface::getMetadata()
     */
    public function getMetadata($key = null)
    {
        // TODO: Auto-generated method stub
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \React\Stream\ReadableStreamInterface::isReadable()
     */

    public function isReadable()
    {
        return !$this->closed && $this->input->isReadable();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \React\Stream\ReadableStreamInterface::pause()
     */
    public function pause()
    {
        $this->input->pause();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \React\Stream\ReadableStreamInterface::resume()
     */
    public function resume()
    {
        $this->input->resume();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \React\Stream\ReadableStreamInterface::pipe()
     */
    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \React\Stream\ReadableStreamInterface::close()
     */
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
