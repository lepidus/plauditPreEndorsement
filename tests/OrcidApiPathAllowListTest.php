<?php

import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');
import('plugins.generic.plauditPreEndorsement.classes.OrcidCredentialsValidator');
import('plugins.generic.plauditPreEndorsement.classes.OrcidClient');

use PHPUnit\Framework\TestCase;

final class OrcidApiPathAllowListTest extends TestCase
{
    public function testOfficialOrcidApiPathsAreAccepted(): void
    {
        $validator = new OrcidCredentialsValidator();
        $officialPaths = [
            ENDORSEMENT_ORCID_API_URL_PUBLIC,
            ENDORSEMENT_ORCID_API_URL_PUBLIC_SANDBOX,
            ENDORSEMENT_ORCID_API_URL_MEMBER,
            ENDORSEMENT_ORCID_API_URL_MEMBER_SANDBOX,
        ];

        foreach ($officialPaths as $apiPath) {
            $this->assertTrue($validator->validateApiPath($apiPath), $apiPath);
        }
    }

    public function testOtherApiPathsAreRejected(): void
    {
        $validator = new OrcidCredentialsValidator();
        $invalidPaths = [
            'http://127.0.0.1/',
            'http://localhost:8080/',
            'https://pub.orcid.org.attacker.example/',
            'https://attacker.example/https://pub.orcid.org/',
            'http://pub.orcid.org/',
            ' https://pub.orcid.org/',
            '',
            null,
            ['https://pub.orcid.org/'],
        ];

        foreach ($invalidPaths as $apiPath) {
            $this->assertFalse($validator->validateApiPath($apiPath), var_export($apiPath, true));
        }
    }

    public function testOrcidClientRefusesStoredApiPathOutsideAllowList(): void
    {
        $plugin = $this->createMock(PlauditPreEndorsementPlugin::class);
        $plugin->method('getSetting')->willReturnMap([
            [1, 'orcidAPIPath', 'http://127.0.0.1/'],
            [1, 'orcidClientId', 'APP-0000000000000000'],
            [1, 'orcidClientSecret', 'secret'],
        ]);
        $orcidClient = new OrcidClient($plugin, 1);

        $this->expectException(InvalidArgumentException::class);
        $orcidClient->requestOrcid('code');
    }
}
