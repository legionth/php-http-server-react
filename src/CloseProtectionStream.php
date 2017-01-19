<?php

namespace Legionth\React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

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
        return $this->input->isReadable();
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
        return $this->input->pipe($dest, $options);
    }

     public function close()
     {
         if ($this->closed) {
             return;
         }

         $this->closed = true;

         // ???
         $this->input->pause();

         $this->readable = false;

         $this->emit('end', array($this));
         $this->emit('close', array($this));
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

}
