<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests\helpers;

use APP\server\Server;
use APP\publication\Publication;
use PKP\user\User;
use PKP\plugins\Hook;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

trait TestHelperTrait
{
    private function createServerMock()
    {
        $server = $this->getMockBuilder(Server::class)
            ->onlyMethods(['getId'])
            ->getMock();

        $server->expects($this->any())
            ->method('getId')
            ->will($this->returnValue(1));
        $server->setName('server-title', "en");
        $server->setData('publisherInstitution', 'server-publisher');
        $server->setPrimaryLocale("en");
        $server->setPath('server-path');
        $server->setId(1);

        return $server->getId();
    }

    private function createPublicationMock($mockPublicationId = null)
    {
        $publicationId = isset($mockPublicationId) ? $mockPublicationId : 1;
        $publication = $this->getMockBuilder(Publication::class)
            ->onlyMethods(['getId'])
            ->getMock();

        $publication->expects($this->any())
            ->method('getId')
            ->will($this->returnValue($publicationId));

        return $publication->getId();
    }

    private function addSchemaFile(string $schemaName): void
    {
        Hook::add(
            'Schema::get::' . $schemaName,
            function (string $hookName, array $args) use ($schemaName) {
                $schema = &$args[0];

                $schemaFile = sprintf(
                    '%s/plugins/generic/plauditPreEndorsement/schemas/%s.json',
                    BASE_SYS_DIR,
                    $schemaName
                );
                if (file_exists($schemaFile)) {
                    $schema = json_decode(file_get_contents($schemaFile));
                    if (!$schema) {
                        throw new \Exception(
                            'Schema failed to decode. This usually means it is invalid JSON. Requested: '
                            . $schemaFile
                            . '. Last JSON error: '
                            . json_last_error()
                        );
                    }
                }
                return true;
            }
        );
    }

    private function createEndorsementDataObject($contextId, $publicationId)
    {
        $endorsement = $this->endorsementDAO->newDataObject();
        $endorsement->setContextId($contextId);
        $endorsement->setPublicationId($publicationId);
        $endorsement->setName("DummyEndorsement");
        $endorsement->setEmail("DummyEndorsement@mailinator.com.br");

        return $endorsement;
    }
}
