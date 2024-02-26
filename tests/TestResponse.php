<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use APP\plugins\generic\plauditPreEndorsement\tests\TestStream;

class TestResponse
{
    private $statusCode;
    private $body;

    public function __construct($statusCode, $bodyContent)
    {
        $this->statusCode = $statusCode;
        $this->body = new TestStream($bodyContent);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getBody()
    {
        return $this->body;
    }
}
