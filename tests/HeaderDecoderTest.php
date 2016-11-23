<?php

use React\Stream\ReadableStream;
use Legionth\React\Http\ChunkedDecoder;
use Legionth\React\Http\HeaderDecoder;

class HeaderDecoderTest extends TestCase
{
    public function setUp()
    {
        $this->input = new ReadableStream();
        $this->parser = new HeaderDecoder($this->input);
    }
    
    public function testMethodIsCalled()
    {
        $this->parser->on('data', $this->expectCallableOnceWith("GET /ip HTTP/1.1\r\nHost: httpbin.org\r\nTransfer-Encoding: chunked\r\n\r\n"));
        $this->input->emit('data', array("GET /ip HTTP/1.1\r\nHost: httpbin.org\r\nTransfer-Encoding: chunked\r\n\r\n"));
    }
    
    public function testSplittedHeader()
    {
    	$this->parser->on('data', $this->expectCallableOnceWith("GET /ip HTTP/1.1\r\nHost: httpbin.org\r\nTransfer-Encoding: chunked\r\n\r\n"));
    	$this->input->emit('data', array("GET /ip HTTP/1.1\r\n"));
    	$this->input->emit('data', array("Host: httpbin.org\r\n"));
    	$this->input->emit('data', array("Transfer-Encoding: chunked\r\n\r\n"));
    }
    
    public function testHandleClose()
    {
        $this->parser->on('close', $this->expectCallableOnce());
    
        $this->input->close();
    
        $this->assertFalse($this->parser->isReadable());
    }
    
    public function testHandleError()
    {
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
    
        $this->input->emit('error', array(new \RuntimeException()));
    
        $this->assertFalse($this->parser->isReadable());
    }
    
    public function testPauseStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');
        
        $parser = new HeaderDecoder($input);
        $parser->pause();
    }
    
    public function testResumeStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');
        
        $parser = new HeaderDecoder($input);
        $parser->pause();
        $parser->resume();
    }
    
    public function testPipeStream()
    {
        $dest = $this->getMock('React\Stream\WritableStreamInterface');
        
        $ret = $this->parser->pipe($dest);
        
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
