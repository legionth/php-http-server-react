# legionth/http-server-react

HTTP server written in PHP on top of ReactPHP.

**Table of Contents**
* [Usage](#usage)
 * [HttpServer](#httpserver)
  * [Create callback function](#create-a-callback-function)
 * [ChunkedDecoder](#chunkeddecoder)
  * [HeaderDecoder](#headerdecoder)
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

## Install

Will be added to composer soon :-)

## License

MIT
