<?php

use React\Stream\ReadableStream;
use Legionth\React\Http\ChunkedEncoderStream;

class ChunkedEncoderStreamTest extends TestCase
{
    private $input;
    private $chunkedStream;

    public function setUp()
    {
        $this->input = new ReadableStream();
        $this->chunkedStream = new ChunkedEncoderStream($this->input);
    }


    public function testChunked()
    {
        $this->chunkedStream->on('data', $this->expectCallableOnce(array("5\r\nhello\r\n")));
        $this->input->emit('data', array('hello'));
    }

    public function testHandleClose()
    {
        $this->chunkedStream->on('close', $this->expectCallableOnce());

        $this->input->close();

        $this->assertFalse($this->chunkedStream->isReadable());
    }

    public function testHandleError()
    {
        $this->chunkedStream->on('error', $this->expectCallableOnce());
        $this->chunkedStream->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($this->chunkedStream->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedEncoderStream($input);
        $parser->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedEncoderStream($input);
        $parser->pause();
        $parser->resume();
    }

    public function testPipeStream()
    {
        $dest = $this->getMock('React\Stream\WritableStreamInterface');

        $ret = $this->chunkedStream->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    private function expectCallableConsecutive($numberOfCalls, array $with)
    {
        $mock = $this->createCallableMock();

        for ($i = 0; $i < $numberOfCalls; $i++) {
            $mock
            ->expects($this->at($i))
            ->method('__invoke')
            ->with($this->equalTo($with[$i]));
        }

        return $mock;
    }
}
