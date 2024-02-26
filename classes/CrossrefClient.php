<?php

class CrossrefClient
{
    public function doiIsIndexed(string $doi): bool
    {
        $crossrefApiUrl = "https://api.crossref.org/works/".$doi;

        $headers = get_headers($crossrefApiUrl);
        $statusCode = $this->getStatusCode($headers);
        $HTTP_STATUS_OK = 200;

        return $statusCode == $HTTP_STATUS_OK;
    }

    public function getStatusCode(array $responseHeaders): int
    {
        $statusLine = $responseHeaders[0];

        return intval(explode(' ', $statusLine)[1]);
    }
}
