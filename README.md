# legionth/http-server-react

HTTP server written in PHP on top of ReactPHP.

**Table of Contents**
* [Usage](#usage)
 * [HttpServer](#httpserver)
  * [Create callback function](#create-a-callback-function)
 * [ChunkedDecoder](#chunkeddecoder)
 * [HeaderDecoder](#headerdecoder)
 * [Handling exceptions](#handling-exceptions)
 * [Return type of the callback function](#return-type-of-the-callback-function)
 * [Middleware](#middleware)
  * [Creating your own middleware](#creating-your-own-middleware)
* [License](#license)

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
$loop = React\EventLoop\Factory::create();

$callback = function (Request $request) use ($loop) {
    return new Response();
};

$socket = new Socket($loop);
$socket->listen(10000, 'localhost');

$server = new HttpServer($socket, $callback);
$loop->run();
```

### ChunkedDecoder

The `ChunkedDecoder` is used to decode the single chunks send by a HTTP request with a `Transfer-Encoding: chunked`. The HTTP server will send the encoded body to the callback function.

This class is based on [ReactPHP streams](https://github.com/reactphp/stream). The `HttpServer` will save the chunks until the body is completed and will forward the decoded request to the callback function.

### HeaderDecoder

The `HeaderDecoder` is used to decode and identify the header of a request. The header will be send when the header is completed which is marked by `\r\n\r\n`.

#### Handling exceptions

The code in the callback function can throw Exceptions, but this shouldn't affect the running server.
So every uncaught exception will be caught by the `HttpServer` and a 'HTTP 500 Internal Server Error' response
will be send to the client, when an exception occures.

Example:
```php
<?php

$loop = React\EventLoop\Factory::create();

$callback = function (Request $request) use ($loop) {
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
$callback = function ($request) {
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
$callback = function (Request $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        $response = heavyCalculationFunction();
        $resolve($response);
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

This is similiar to the conecept of [the fig standards](https://github.com/php-fig/fig-standards/blob/master/proposed/http-middleware/middleware-meta.md).

Add as many middlewares as you want you just need to follow the following design

```php
$callback = function (RequestInterface $request) {
    return new Response();
}

$middleware = function (RequestInterface $request, callable $next) {
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

$timeBlockingMiddleware = function (RequestInterface $request, callable $next) {
    // Will call the next middleware from 00:00 till 16:00
    // otherwise an 403 Forbidden response will be sent to the client
    if (((int)date('Hi') < 1600 && (int)date('Hi') > 0) {
        return $next($request);
    }
    return new Response(403);
};

$addHeaderToRequest = function (RequestInterface $request, callable $next) {
    $request = $request->withAddedHeader('Date', date('Y-m-d'));
    return $next($request);
};

$addHeaderToResponse = function (RequestInterface $request, callable $next) {
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

## Install

[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require legionth/http-server-react:^0.1
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

## License

MIT
