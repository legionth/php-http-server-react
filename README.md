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

## Install

Will be added to composer soon :-)

## License

MIT
