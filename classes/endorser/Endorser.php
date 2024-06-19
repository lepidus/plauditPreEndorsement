<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\endorser;

class Endorser extends \PKP\core\DataObject
{
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
}
