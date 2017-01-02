<?php
namespace Legionth\React\Http;

use RingCentral\Psr7\Response;
use Legionth\React\Http\ChunkedEncoderStream;
use Legionth\React\Http\HttpBodyStream;
use React\Stream\ReadableStreamInterface;

class StreamedResponse extends Response
{

    public function __construct(
        ReadableStreamInterface $input,
        $status = 200,
        array $headers = array(),
        $version = '1.1',
        $reason = null,
        ChunkedEncoderStream $chunkedStream = null
    ) {
        if ($chunkedStream === null) {
            $chunkedStream = new ChunkedEncoderStream($input);
        }

        $body = new HttpBodyStream($chunkedStream);
        $headers['Transfer-Encoding'] = 'chunked';

        parent::__construct($status, $headers, $body, $version, $reason);
    }
}
