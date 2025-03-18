<?php

use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;
use APP\plugins\generic\plauditPreEndorsement\PlauditPreEndorsementPlugin;
use PHPUnit\Framework\TestCase;

final class OrcidClientTest extends TestCase
{
    private $orcidClient;
    private $orcidRecordResponse = [
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
    private $emptyWorksResponse = [
        'last-modified-date' => null,
        'group' => [],
        'path' => '/0000-0001-5542-5100/works'
    ];
    private $filledWorksResponse = [
        'last-modified-date' => [
            'value' => 1710359793007
        ],
        'group' => [
            [
                'last-modified-date' => [
                    'value' => 1710359793007
                ],
                'external-ids' => [],
                'work-summary' => []
            ]
        ],
        'path' => '/0000-0001-5542-5100/works'
    ];

    public function setUp(): void
    {
        parent::setUp();
        $plugin = new PlauditPreEndorsementPlugin();
        $contextId = 1;
        $this->orcidClient = new OrcidClient($plugin, $contextId);
    }

    public function testGetFullNameFromRecord(): void
    {
        $fullName = $this->orcidClient->getFullNameFromRecord($this->orcidRecordResponse);
        $this->assertEquals('Alfred Hitchcock', $fullName);
    }

    public function testCheckRecordHasWorks(): void
    {
        $this->assertFalse($this->orcidClient->recordHasWorks($this->emptyWorksResponse));
        $this->assertTrue($this->orcidClient->recordHasWorks($this->filledWorksResponse));
    }
}
