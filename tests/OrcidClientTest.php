<?php

use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;
use APP\plugins\generic\plauditPreEndorsement\PlauditPreEndorsementPlugin;
use PHPUnit\Framework\TestCase;

final class OrcidClientTest extends TestCase
{
    private $testRecord = [
        'person' => [
            'last-modified-date' => '',
            'name' => [
                'created-date' => [
                    'value' => 1666816304613
                ],
                'last-modified-date' => [
                    'value' => 1666816304613
                ],
                'given-names' => [
                    'value' => 'Alfred'
                ],
                'family-name' => [
                    'value' => 'Hitchcock'
                ],
                'credit-name' => '',
                'source' => '',
                'visibility' => 'public',
                'path' => '0000-0001-5542-5100'
            ]
        ]
    ];

    public function testGetFullNameFromRecord(): void
    {
        $plugin = new PlauditPreEndorsementPlugin();
        $contextId = 1;
        $orcidClient = new OrcidClient($plugin, $contextId);

        $this->assertEquals('Alfred Hitchcock', $orcidClient->getFullNameFromRecord($this->testRecord));
    }
}
