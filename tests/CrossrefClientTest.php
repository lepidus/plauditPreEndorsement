<?php

use APP\plugins\generic\plauditPreEndorsement\classes\CrossrefClient;
use PHPUnit\Framework\TestCase;

final class CrossrefClientTest extends TestCase
{
    private $crossrefClient;
    private $OK_HEADERS = ['HTTP/1.1 200 OK', 'Date: Sat, 29 May 2004 12:28:13 GMT', 'Server: Apache/1.3.27 (Unix)  (Red-Hat/Linux)', 'Last-Modified: Wed, 08 Jan 2003 23:11:55 GMT', 'ETag: "3f80f-1b6-3e1cb03b"', 'Accept-Ranges: bytes', 'Content-Length: 438', 'Connection: close', 'Content-Type: text/html'];
    private $NOT_FOUND_HEADERS = ['HTTP/1.1 404 Not Found', 'Date: Sat, 29 May 2004 12:28:13 GMT', 'Server: Apache/1.3.27 (Unix)  (Red-Hat/Linux)', 'Last-Modified: Wed, 08 Jan 2003 23:11:55 GMT', 'ETag: "3f80f-1b6-3e1cb03b"', 'Accept-Ranges: bytes', 'Content-Length: 438', 'Connection: close', 'Content-Type: text/html'];

    public function setUp(): void
    {
        parent::setUp();
        $this->crossrefClient = new CrossrefClient();
    }

    public function testGetStatusCode(): void
    {
        $this->assertEquals(200, $this->crossrefClient->getStatusCode($this->OK_HEADERS));
        $this->assertEquals(404, $this->crossrefClient->getStatusCode($this->NOT_FOUND_HEADERS));
    }

}
