<?php

namespace Legionth\React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

/**
 * Protects the passed ReadableStream to be closed
 */
class CloseProtectionStream extends EventEmitter implements ReadableStreamInterface
{
    private $connection;
    private $closed = false;

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
        return !$this->closed && $this->stream->isReadable();
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
         // $this->input won't be closed here just the listeners will be removed
         $this->input->removeListener('data', array($this, 'handleData'));
         $this->input->removeListener('error', array($this, 'handleError'));
         $this->input->removeListener('end', array($this, 'handleEnd'));
         $this->input->removeListener('close', array($this, 'close'));

         $this->removeAllListeners();
     }

     /** @internal */
     public function handleData($data)
     {
        $this->emit('data', array($data));
     }

     /** @internal */
     public function handleEnd()
     {
         $this->emit('end');
         $this->close();
     }

     /** @internal */
     public function handleError(\Exception $e)
     {
         $this->emit('error', array($e));
         $this->close();
     }

}
