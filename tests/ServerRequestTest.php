<?php

use Legionth\React\Http\ServerRequest;

class ServerRequestTest extends TestCase
{
    private $request;

    public function setUp()
    {
        $this->request = new ServerRequest();
    }

    public function testGetServerParams()
    {
        $request = new ServerRequest(
            null,
            '',
            array(),
            null,
            '1.1',
            array('test' => 'world')
        );

        $this->assertEquals(array('test' => 'world'), $request->getServerParams());
    }

    public function testGetFileParams()
    {
        $request = new ServerRequest(
            null,
            '',
            array(),
            null,
            '1.1',
            array(),
            array('test' => 'world')
        );
        $this->assertEquals(array('test' => 'world'), $request->getUploadedFiles());
    }

    public function testGetCookieParams()
    {
        $request = new ServerRequest(
            null,
            '',
            array(),
            null,
            '1.1',
            array(),
            array(),
            array('test' => 'world')
        );
        $this->assertEquals(array('test' => 'world'), $request->getCookieParams());
    }

    public function testGetQueryParams()
    {
        $request = new ServerRequest(
            null,
            '',
            array(),
            null,
            '1.1',
            array(),
            array(),
            array(),
            array('test' => 'world')
        );
        $this->assertEquals(array('test' => 'world'), $request->getQueryParams());
    }

    public function testGetParsedBody()
    {
        $request = new ServerRequest(
            null,
            '',
            array(),
            null,
            '1.1',
            array(),
            array(),
            array(),
            array(),
            array('test' => 'world')
        );

        $this->assertEquals(array('test' => 'world'), $request->getParsedBody());
    }

    public function testGetNoAttributes()
    {
        $this->assertEquals(array(), $this->request->getAttributes());
    }

    public function testWithAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('hello' => 'world'), $request->getAttributes());
    }

    public function testGetAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');

        $this->assertNotSame($request, $this->request);
        $this->assertEquals('world', $request->getAttribute('hello'));
    }

    public function testGetDefaultAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(null, $request->getAttribute('hi', null));
    }

    public function testWithoutAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');
        $request = $request->withAttribute('test', 'nice');

        $request = $request->withoutAttribute('hello');

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'nice'), $request->getAttributes());
    }

    public function testWithCookieParams()
    {
        $request = $this->request->withCookieParams(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getCookieParams());
    }

    public function testWithQueryParams()
    {
        $request = $this->request->withQueryParams(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getQueryParams());
    }

    public function testWithUploadedFiles()
    {
        $request = $this->request->withUploadedFiles(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getUploadedFiles());
    }

    public function testWithParsedBody()
    {
        $request = $this->request->withParsedBody(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getParsedBody());
    }
}
