<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\components\listPanel;

use APP\core\Application;
use APP\submission\Submission;
use APP\plugins\generic\plauditPreEndorsement\classes\components\forms\EndorsementForm;
use PKP\components\listPanels\ListPanel;

class EndorsersListPanel extends ListPanel
{
    private $submission;

    public function __construct(
        string $id,
        string $title,
        Submission $submission,
        array $items = []
    ) {
        parent::__construct($id, $title);
        $this->submission = $submission;
        $this->items = $items;
    }

    public function getConfig()
    {
        $config = parent::getConfig();

        $config = array_merge(
            $config,
            [
                'form' => $this->getEndorsementForm()->getConfig()
            ]
        );

        return $config;
    }

    private function getEndorsementForm()
    {
        $publication = $this->submission->getCurrentPublication();

        return new EndorsementForm(
            $this->getPublicationUrl(),
            $publication,
        );
    }

    private function getPublicationUrl()
    {
        $publication = $this->submission->getCurrentPublication();

        return Application::get()->getRequest()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_API,
            Application::get()->getRequest()->getContext()->getPath(),
            'submissions/' . $this->submission->getId() . '/publications/' . $publication->getId()
        );
    }
}
