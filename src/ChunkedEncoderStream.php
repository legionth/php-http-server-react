<?php
namespace Legionth\React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class ChunkedEncoderStream extends EventEmitter implements ReadableStreamInterface
{
    private $input;
    private $closed;

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
        if (is_string($data)) {
            $completeChunk = $this->createChunk($data);
        }

        $this->emit('data', array($completeChunk));
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
    public function handleEnd($data = null)
    {
        if ($data != null && is_string($data)) {
            $completeChunk = $this->createChunk($data);
            $this->emit('data', array($completeChunk));
        }

        $this->emit('data', array("0\r\n\r\n"));

        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /**
     * @param string $data - string to be transformed in an valid
     *                       HTTP encoded chunk string
     * @return string
     */
    private function createChunk($data)
    {
        $byteSize = strlen($data);
        $chunkBeginning = $byteSize . "\r\n";

        return $chunkBeginning . $data . "\r\n";
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
