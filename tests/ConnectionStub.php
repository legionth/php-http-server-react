<?php

namespace Legionth\React\Tests;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;

class ConnectionStub extends EventEmitter implements ConnectionInterface
{
    private $data = '';
    private $writtenData = '';

    public function isReadable()
    {
        return true;
    }

    public function isWritable()
    {
        return true;
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);
        return $dest;
    }

    public function write($data)
    {
        $this->data .= $data;
        $this->writtenData = $data;
        return true;
    }

    public function end($data = null)
    {
    }

    public function close()
    {
    }

    public function getData()
    {
        return $this->data;
    }

    public function getRemoteAddress()
    {
        return '127.0.0.1';
    }

    public function getWrittenData()
    {
        return $this->writtenData;
    }
}