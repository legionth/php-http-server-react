<?php

namespace Legionth\React\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use RingCentral\Psr7\Request;

class ServerRequest extends Request implements ServerRequestInterface
{
    private $attributes = array();

    private $serverParams;
    private $fileParams;
    private $cookies;
    private $queryParams;
    private $parsedBody;

    public function __construct(
        $method = null,
        $uri = '',
        array $headers = array(),
        StreamInterface $body = null,
        $protocol = '1.1',
        array $serverParams = array(),
        array $fileParams = array(),
        array $cookies = array(),
        array $queryParams = array(),
        $parsedBody = null
    )
    {
        $this->serverParams = $serverParams;
        $this->fileParams = $fileParams;
        $this->cookies = $cookies;
        $this->queryParams = $queryParams;
        $this->parsedBody = $parsedBody;

        parent::__construct(
            $method,
            $uri,
            $headers,
            $body,
            $protocol
        );
    }

    public function getServerParams()
    {
        return $this->serverParams;
    }

    public function getCookieParams()
    {
        return $this->cookies;
    }

    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookies = $cookies;
        return $new;
    }

    public function getQueryParams()
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    public function getUploadedFiles()
    {
        return $this->fileParams;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->fileParams = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        if (!array_key_exists($name, $this->attributes)) {
            return $default;
        }
        return $this->attributes[$name];
    }

    public function withAttribute($name, $value)
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute($name)
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
