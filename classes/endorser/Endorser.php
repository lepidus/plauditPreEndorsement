<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\endorser;

class Endorser extends \PKP\core\DataObject
{
    public function getId(): int
    {
        return $this->getData("id");
    }

    public function getPublicationId(): int
    {
        return $this->getData("publicationId");
    }

    public function setPublicationId(int $publicationId)
    {
        $this->setData("publicationId", $publicationId);
    }

    public function getName(): string
    {
        return $this->getData("name");
    }

    public function setName(string $name)
    {
        $this->setData("name", $name);
    }

    public function getEmail(): string
    {
        return $this->getData("email");
    }

    public function setEmail(string $email)
    {
        $this->setData("email", $email);
    }

    public function getStatus(): int
    {
        return $this->getData("status");
    }

    public function setStatus(int $status)
    {
        $this->setData("status", $status);
    }

    public function getOrcid(): string
    {
        return $this->getData("orcid");
    }

    public function setOrcid(string $orcid)
    {
        $this->setData("orcid", $orcid);
    }

    public function getEmailToken(): string
    {
        return $this->getData("emailToken");
    }

    public function setEmailToken(string $emailToken)
    {
        $this->setData("emailToken", $emailToken);
    }

    public function getEmailCount(): int
    {
        return $this->getData("emailCount");
    }

    public function setEmailCount(int $emailCount)
    {
        $this->setData("emailCount", $emailCount);
    }
}
