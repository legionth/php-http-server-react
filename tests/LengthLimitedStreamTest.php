<?php

use Legionth\React\Http\LengthLimitedStream;
use React\Stream\ReadableStream;
use React\Socket\Server as Socket;
use RingCentral\Psr7\Response;
use Legionth\React\Http\HttpServer;

class LengthLimitedStreamTest extends TestCase
{
    private $input;
    private $stream;
    private $server;
    private $loop;
    private $socket;

    public function setUp()
    {
        $this->loop = new React\EventLoop\StreamSelectLoop();
        $this->socket = new Socket($this->loop);

        $callback = function ($request) {
            return new Response();
        };

        $this->server = new HttpServer(
            $this->socket,
            $callback
        );

        $this->input = new ReadableStream();
        $this->stream = new LengthLimitedStream($this->input, 5, $this->server);
    }

    public function testSimpleChunk()
    {
        $this->stream->on('data', $this->expectCallableOnceWith('hello'));
        $this->input->emit('data', array("hello world"));
    }

    public function testHandleError()
    {
        $this->stream->on('error', $this->expectCallableOnce());
        $this->stream->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($this->stream->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $parser = new LengthLimitedStream($input, 0, $this->server);
        $parser->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $parser = new LengthLimitedStream($input, 0, $this->server);
        $parser->pause();
        $parser->resume();
    }

    public function testPipeStream()
    {
        $dest = $this->getMock('React\Stream\WritableStreamInterface');

        $ret = $this->stream->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testHandleClose()
    {
        $this->stream->on('close', $this->expectCallableOnce());

        $this->input->close();
        $this->input->emit('end', array());

        $this->assertFalse($this->stream->isReadable());
    }
}
