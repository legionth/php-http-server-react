# legionth/http-server-react

HTTP server written in PHP on top of ReactPHP.

**Table of Contents**
* [Usage](#usage)
  * [HttpServer](#httpserver)
    * [Create callback function](#create-a-callback-function)
  * [ChunkedDecoder](#chunkeddecoder)
  * [Handling exceptions](#handling-exceptions)
  * [Return type of the callback function](#return-type-of-the-callback-function)
  * [Middleware](#middleware)
    * [Creating your own middleware](#creating-your-own-middleware)
  * [Streaming responses](#streaming-responses)
  * [HTTPS server](#https-server)
* [License](#license)

## Notice (June 2017)

This repository is just a prototype and won't be supported anymore.
Every feature of this prototype moved to the official [ReactPHP HTTP-Server](https://github.com/reactphp/http).

## Usage

### HttpServer

The HTTP server needs a socket and callback function to work. The socket will be used to communicate between server and client.
The callback function is used to react on requests and return responses. The callback function *must* return either a promise or
a response object. The `HttpServer` class uses [PSR-7 Middleware](https://packagist.org/packages/ringcentral/psr7) objects.
And these need to be used also in the [callback function](#create-a-callback-function).

#### Create a callback function

The `HttpServer` uses a callback function. This callback function has a request object as its only paremter and expects to return
a response object.

Create your own callback function to react on responses as you wish (e.g. check the response, fetch values from the database and
send the response). But be careful, blocking operations like database or file operations can lead to
a slow down server.

```php
$callback = function (ServerRequestInterface $request) {
    $content = '<html>
<body>
    <h1> Hello World! </h1>
    <p> This is your own little server. Written in PHP :-) </p>
</body>
</html>';

    return new Response(
        200,
        array(
            'Content-Length' => strlen($content),
            'Content-Type' => 'text/html'
        ),
        $content
    );
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);
$loop->run();
```

This example will respond with a simple HTML site on every request send to this server. But this will always send a response to the client as soon the header of the request
has arrived at the server. If the request consists of body data, these will be ignored and the TCP connection will be closed as the response is sent to the client.
To handle the body data you have to use streams.

Every version after `v0.4.0` will stream requests. This means the body of the request object of your callback function.

Streaming requests makes it possible to send big amount of data in small chunks from the client to the server. E.g you can start the computation of the request,
when your application received an specific part of the body.

The body of the request object in your callback and middleware function will be a [ReadableStreamInterface](https://github.com/reactphp/stream).

Every request body stream will send an end event when the stream is successfully completed. We have to use a [promise](https://github.com/reactphp/promise) to ensure that the
response only will be send to the client when the request stream is finished.

The next example will do the same as the previous example, but will wait until the request stream will be finished.

```php
$callback = function (ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        $request->getBody->on('end', function () use (&$contentLength, $resolve) {
            $content = '<html>
<body>
    <h1> Hello World! </h1>
    <p> This is your own little server. Written in PHP :-) </p>
</body>
</html>';

            return new Response(
                200,
                array(
                    'Content-Length' => strlen($content),
                    'Content-Type' => 'text/html'
                ),
                $content
            );
        });
    };
}
```

The body of the request will always be a [ReactPHP stream](https://github.com/reactphp/stream). The PSR-7 methods of the [StreamInterface](http://www.php-fig.org/psr/psr-7/#streams)
are currently not needed and have no function at this point of development, but they may have in the further development.

In the following example a listener will be added to the 'data' event, which will count just the transferred string data length.
At the end of the body stream the length of the transferred data will be send in an text via a HTTP response to the client.

```php
$callback = function (ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($body) {
        $contentLength = 0;
        $request->getBody()->on('data', function ($chunk) use ($resolve, &$contentLength) {
            $contentLength += strlen($chunk);
        });

        $request->getBody()->on('end', function () use (&$contentLength, $resolve) {
            $content = "Transferred data length: " . $contentLength ."\n";
            $resolve(
                new Response(
                    200,
                    array(
                        'Content-Length' => strlen($content),
                        'Content-Type' => 'text/html'
                    ),
                    $content
                )
            );
        });
    });
};
```

This is just an example you can use a [BufferedSink](https://github.com/reactphp/stream) from the `reactphp/stream` to avoid these lines of code.

This example just streams the body of the request. The body of the response can alseo be streamed. Check out the [Streaming responses](#streaming-responses) chapter.

The [ServerRequestInterface](http://www.php-fig.org/psr/psr-7/#server-side-requests) MUST be used  as first parameter in the callback and middleware functions.
The parameters of the request have the default values defined by the PSR-7 Interface and can be changed by the middleware.

Check out the `examples` folder how your server could look like.

### ChunkedDecoder

The `ChunkedDecoder` is used to decode the single chunks send by a HTTP request with a `Transfer-Encoding: chunked`. The HTTP server will send the encoded body to the callback function.

This class is based on [ReactPHP streams](https://github.com/reactphp/stream). The `HttpServer` will save the chunks until the body is completed and will forward the decoded request to the callback function.

#### Handling exceptions

The code in the callback function can throw Exceptions, but this shouldn't affect the running server.
So every uncaught exception will be caught by the `HttpServer` and a 'HTTP 500 Internal Server Error' response
will be send to the client, when an exception occures.

Example:
```php
<?php

$loop = React\EventLoop\Factory::create();

$callback = function (ServerRequestInterface $request) use ($loop) {
    throw new Exception();
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);
$loop->run();
```

This example will lead to a 'HTTP 500 Internal Server Error' on any request.

Hint: This response is the default response on an uncaught exception. If you want the user to see more than any empty site in the browser,
catch your exception and create your own Response Object with header and body.

```php
<?php
$callback = function (ServerRequestInterface $request) {
    try {
        //something that could go wrong
    } catch(Exception $exception) {
        return new Response(500, array('Content-Length' => 5), 'error');
    }
}
$httpServer = new HttpServer($socket, $callback);
```

#### Return type of the callback function

The return type of the callback function **must** be a [response object](https://packagist.org/packages/ringcentral/psr7) or
a [promise](https://github.com/reactphp/promise).

For heavy calculations you should consider using promises. Not using them can slow down the server.

```php
$callback = function (ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        $request->getBody()->on('end', function () {
            $response = heavyCalculationFunction();
            $resolve($response);
        });
    });
};
```
Checkout the `examples` folder how to use promises in the callback function.

The promise **must** return a response object anything else will lead to a 'HTTP 500 Internal Server Error' response for the client.

Other types aren't allowed and will lead to a 'HTTP 500 Internal Server Error' response for the client.

### Middleware

#### Creating your own middleware

You can create your own middleware. These middleware lies between the `HttpServer` and the user callback function. The `HttpServer` would
call the callback function, if the response object is created. With an added middleware the `HttpServer` will call this middleware first.
The next chain link would be another middleware or the callback function at the end. Every middleware has to return an response object.
Otherwise the `HttpServer` will return a "500 Internal Server Error" message.
The middleware can not only manipulate the request objects, but also the response objects returned by the other added middleware or the callback function.

This is similiar to the concept of [the fig standards](https://github.com/php-fig/fig-standards/blob/master/proposed/http-middleware/middleware-meta.md).

Add as many middlewares as you want you just need to follow the following design

```php
$callback = function (ServerRequestInterface $request) {
    return new Response();
}

$middleware = function (ServerRequestInterface $request, callable $next) {
    // check or maninpulate the request object
    ...
    // call of next middleware chain link
    return $next($request);
}

$server = new HttpServer($socket, $callback);
$server->addMiddleware($middleware);
```

Make sure you add the `return $next($request)` in your middleware code. Otherwise the response of the last called middleware will be returned.
The `return $next($request)` will call the next middleware or the user callback function, if it's the last part of this middleware chain.

The added middleware will be executed the order you added them.

```php
...

$timeBlockingMiddleware = function (ServerRequestInterface $request, callable $next) {
    // Will call the next middleware from 00:00 till 16:00
    // otherwise an 403 Forbidden response will be sent to the client
    if (((int)date('Hi') < 1600 && (int)date('Hi') > 0) {
        return $next($request);
    }
    return new Response(403);
};

$addHeaderToRequest = function (ServerRequestInterface $request, callable $next) {
    $request = $request->withAddedHeader('Date', date('Y-m-d'));
    return $next($request);
};

$addHeaderToResponse = function (ServerRequestInterface $request, callable $next) {
    $response = $next($request);
    $response = $response->withAddedHeader('Age', '12');
    return $response;
};

$server = new HttpServer($socket, $callback);
$server->addMiddleware($timeBlockingMiddleware);
$server->addMiddleware($addHeaderToRequest);
$server->addMiddleware($addHeaderToResponse);
```
In this example `$timeBlockingMiddleWare` will be called first, the `$addHeaderToRequest` as second and `$addHeaderToResponse` as third .
The last part of the chain is the `callback` function.

This little example should show how you can use the middlwares e.g. to check or manipulate the requests/response objects.

Checkout the `examples/middleware` how to add multiple middlewares.

### Streaming responses

Data that would take a while to be completed caused by computation, can be streamed directly to the client without buffering the whole data.
Streaming makes it possible to send big amount of data in small chunks to the client.

Use an instance of the `HttpBodyStream` and use this instance as the body for `Response` object you want to return.

```php
$callback = function (ServerRequestInterface $request) {
    $input = new ReadableStream();
    $responseBody = new HttpBodyStream($input);
    
    // your computation
    // emit via `$input`
    
    $promise = new Promise(function ($resolve, $reject) use ($request, $responseBody) {
        $request->getBody()->on('end', function () use ($resolve, $responseBody){
            $resolve(new Response(200, array(), $responseBody));
        });
    });

    return $promise;
}
```

The `HttpServer` will use the emitted data from the `ReadableStream` to send this data directly to the client.
If you use the `HttpBodyStream` the whole transfer will be [chunked encoded](https://en.wikipedia.org/wiki/Chunked_transfer_encoding), other values set for `Transfer-Encoding` will be ignored.

Check out the `examples` folder how your computation could look like.

### HTTPS Server

The HTTP server can be set to an HTTPS server by using the [SecureServer from the socket package of ReactPHP](https://github.com/reactphp/socket#secureserver).

Following example shows how you can use this:

```php
$socket = new Socket($loop);
$secureSocket = new SecureServer(
    $socket,
    $loop,
    array('local_cert' => 'secret.pem')
);

$secureSocket->listen(10000, 'localhost');

$secureSocket->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$server = new HttpServer($secureSocket, $callback);
```

Check out the `examples` folder how your HTTPS server could look like.

To execute the example you have to use a self-signed certificate. You can use the 
[script to generate a self-signed certificate](https://github.com/reactphp/socket/blob/master/examples/10-generate-self-signed.php)

## Install

[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require legionth/http-server-react:^0.1
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

## License

MIT
