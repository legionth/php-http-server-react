<?php

use Legionth\React\Http\LengthLimitedStream;
use React\Stream\ReadableStream;

class LengthLimitedStreamTest extends TestCase
{
    private $input;
    private $stream;

    public function setUp()
    {
        $this->input = new ReadableStream();
    }

    public function testSimpleChunk()
    {
        $stream = new LengthLimitedStream($this->input, 5);
        $stream->on('data', $this->expectCallableOnceWith('hello'));
        $stream->on('end', $this->expectCallableOnce());
        $this->input->emit('data', array("hello world"));
    }

    public function testInputStreamKeepsEmitting()
    {
        $stream = new LengthLimitedStream($this->input, 5);
        $stream->on('data', $this->expectCallableOnceWith('hello'));
        $stream->on('end', $this->expectCallableOnce());

        $this->input->emit('data', array("hello world"));
        $this->input->emit('data', array("world"));
        $this->input->emit('data', array("world"));
    }

    public function testZeroLengthInContentLengthWillIgnoreEmittedDataEvents()
    {
        $stream = new LengthLimitedStream($this->input, 0);
        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableOnce());
        $this->input->emit('data', array("hello world"));
    }

    public function testHandleError()
    {
        $stream = new LengthLimitedStream($this->input, 0);
        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($stream->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $stream = new LengthLimitedStream($input, 0);
        $stream->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $stream = new LengthLimitedStream($input, 0);
        $stream->pause();
        $stream->resume();
    }

    public function testPipeStream()
    {
        $stream = new LengthLimitedStream($this->input, 0);
        $dest = $this->getMock('React\Stream\WritableStreamInterface');

        $ret = $stream->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testHandleClose()
    {
        $stream = new LengthLimitedStream($this->input, 0);
        $stream->on('close', $this->expectCallableOnce());

        $this->input->close();
        $this->input->emit('end', array());

        $this->assertFalse($stream->isReadable());
    }
}
