<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

class TestStream
{
    private $content;

    public function __construct($content)
    {
        $this->content = $content;
    }

    public function getContents()
    {
        return $this->content;
    }
}
