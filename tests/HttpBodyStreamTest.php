<?php
use Legionth\React\Http\HttpBodyStream;
use React\Stream\ReadableStream;
use React\Stream\WritableStream;

class HttpBodyStreamTest extends TestCase
{
    private $input;
    private $bodyStream;

    public function setUp()
    {
        $this->input = new ReadableStream();
        $this->bodyStream = new HttpBodyStream($this->input);
    }

    public function testDataEmit()
    {
        $this->bodyStream->on('data', $this->expectCallableOnce(array("hello")));
        $this->input->emit('data', array("hello"));
    }

    public function testPauseStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())
            ->method('pause');

        $bodyStream = new HttpBodyStream($input);
        $bodyStream->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())
            ->method('pause');

        $bodyStream = new HttpBodyStream($input);
        $bodyStream->pause();
        $bodyStream->resume();
    }

    public function testPipeStream()
    {
        $dest = $this->getMock('React\Stream\WritableStreamInterface');

        $ret = $this->bodyStream->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testHandleClose()
    {
        $this->bodyStream->on('close', $this->expectCallableOnce());

        $this->input->close();
        $this->input->emit('end', array());

        $this->assertFalse($this->bodyStream->isReadable());
    }

    public function testStopDataEmittingAfterClose()
    {
        $bodyStream = new HttpBodyStream($this->input);
        $bodyStream->on('close', $this->expectCallableOnce());
        $this->bodyStream->on('data', $this->expectCallableOnce(array("hello")));

        $this->input->emit('data', array("hello"));
        $bodyStream->close();
        $this->input->emit('data', array("world"));
    }

    public function testHandleError()
    {
        $this->bodyStream->on('error', $this->expectCallableOnce());
        $this->bodyStream->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($this->bodyStream->isReadable());
    }

    public function testToString()
    {
        $this->assertEquals('', $this->bodyStream->__toString());
    }

    public function testDetach()
    {
        $this->assertEquals(null, $this->bodyStream->detach());
    }

    public function testGetSize()
    {
        $this->assertEquals(null, $this->bodyStream->getSize());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testTell()
    {
        $this->bodyStream->tell();
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testEof()
    {
        $this->bodyStream->eof();
    }

    public function testIsSeekable()
    {
        $this->assertFalse($this->bodyStream->isSeekable());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testWrite()
    {
        $this->bodyStream->write('');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testRead()
    {
        $this->bodyStream->read('');
    }

    public function testGetContents()
    {
        $this->assertEquals('', $this->bodyStream->getContents());
    }

    public function testGetMetaData()
    {
        $this->assertEquals(null, $this->bodyStream->getMetadata());
    }

    public function testIsReadable()
    {
        $this->assertTrue($this->bodyStream->isReadable());
    }

    public function testPause()
    {
        $this->bodyStream->pause();
    }

    public function testResume()
    {
        $this->bodyStream->resume();
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testSeek()
    {
        $this->bodyStream->seek('');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testRewind()
    {
        $this->bodyStream->rewind();
    }

    public function testIsWriteable()
    {
        $this->assertFalse($this->bodyStream->isWritable());
    }

    private function expectCallableConsecutive($numberOfCalls, array $with)
    {
        $mock = $this->createCallableMock();

        for ($i = 0; $i < $numberOfCalls; $i ++) {
            $mock->expects($this->at($i))
                ->method('__invoke')
                ->with($this->equalTo($with[$i]));
        }

        return $mock;
    }
}
