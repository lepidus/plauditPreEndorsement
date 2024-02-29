<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\components\forms;

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;
use APP\publication\Publication;

class EndorserForm extends FormComponent
{
    public $id = 'endorserForm';
    public $method = 'PUT';

    public function __construct(string $action, Publication $publication)
    {
        $this->action = $action;

        $this->addField(new FieldText('endorserName', [
            'label' => __('plugins.generic.plauditPreEndorsement.endorserName'),
            'value' => $publication->getData('endorserName'),
        ]));

        $this->addField(new FieldText('endorserEmail', [
            'label' => __('plugins.generic.plauditPreEndorsement.endorserEmail'),
            'value' => $publication->getData('endorserEmail'),
        ]));
    }
}
