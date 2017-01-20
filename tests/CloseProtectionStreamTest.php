<?php

use Legionth\React\Http\CloseProtectionStream;
use React\Stream\ReadableStream;

class CloseProtectionStreamTest extends TestCase
{
    public function testCloseEventDoesntCloseInputStream()
    {
        $input = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->getMock();
        $input->expects($this->never())->method('close');

        $protection = new CloseProtectionStream($input);
        $protection->close();
    }

    public function testPause()
    {
        $input = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->getMock();
        $input->expects($this->once())->method('pause');

        $protection = new CloseProtectionStream($input);
        $protection->pause();
    }

    public function testHandleError()
    {
        $input = new ReadableStream();

        $protection = new CloseProtectionStream($input);
        $protection->on('error', $this->expectCallableOnce());
        $protection->on('close', $this->expectCallableOnce());

        $input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($protection->isReadable());
        $this->assertTrue($input->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $stream = new CloseProtectionStream($input, 0);
        $stream->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $protection = new CloseProtectionStream($input);
        $protection->pause();
        $protection->resume();
    }

    public function testPipeStream()
    {
        $input = new ReadableStream();

        $protection = new CloseProtectionStream($input);
        $dest = $this->getMock('React\Stream\WritableStreamInterface');

        $ret = $protection->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testHandleClose()
    {
        $input = new ReadableStream();

        $protection = new CloseProtectionStream($input);
        $protection->on('close', $this->expectCallableOnce());

        $input->close();
        $input->emit('end', array());

        $this->assertFalse($protection->isReadable());
        $this->assertFalse($input->isReadable());
    }

    public function testSendEndViaPipe()
    {
        $input = new ReadableStream();

        $protection = new CloseProtectionStream($input);
        $protection->on('close', $this->expectCallableOnce());

        $input->close();
        $input->emit('end', array());

        $this->assertFalse($protection->isReadable());
    }

    public function testStopEmittingDataAfterClose()
    {
        $input = new ReadableStream();

        $protection = new CloseProtectionStream($input);
        $protection->on('data', $this->expectCallableNever());

        $protection->on('close', $this->expectCallableOnce());

        $protection->close();

        $input->emit('data', array('hello'));

        $this->assertFalse($protection->isReadable());
        $this->assertTrue($input->isReadable());
    }

    public function testErrorIsNeverCalledAfterClose()
    {
        $input = new ReadableStream();

        $protection = new CloseProtectionStream($input);
        $protection->on('data', $this->expectCallableNever());
        $protection->on('error', $this->expectCallableNever());
        $protection->on('close', $this->expectCallableOnce());

        $protection->close();

        $input->emit('error', array(new Exception()));

        $this->assertFalse($protection->isReadable());
        $this->assertTrue($input->isReadable());
    }

    public function testEndWontBeEmittedAfterClose()
    {
        $input = new ReadableStream();

        $protection = new CloseProtectionStream($input);
        $protection->on('data', $this->expectCallableNever());
        $protection->on('close', $this->expectCallableOnce());

        $protection->close();

        $input->emit('end', array());

        $this->assertFalse($protection->isReadable());
        $this->assertTrue($input->isReadable());
    }

    public function closeEventPausesInputStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $protection = new CloseProtectionStream($input);
        $protection = new CloseProtectionStream($input);
        $protection->on('data', $this->expectCallableNever());
        $protection->on('close', $this->expectCallableOnce());

        $protection->close();
    }
}
