<?php

/**
 * @file plugins/generic/plaudit/PlauditPreEndorsementPlugin.inc.php 
 *
 * Copyright (c) 2022 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class PlauditPreEndorsementPlugin
 * @ingroup plugins_generic_plauditPreEndorsement
 * @brief Plaudit Pre-Endorsement Plugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class PlauditPreEndorsementPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'addEndorserFieldToStep3'));

            HookRegistry::register('submissionsubmitstep3form::readuservars', array($this, 'allowStep3FormToReadOurFields'));
            HookRegistry::register('submissionsubmitstep3form::execute', array($this, 'step3SaveOurFieldsInDatabase'));
            HookRegistry::register('Schema::get::publication', array($this, 'addOurFieldsToPublicationSchema'));
        }

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.plauditPreEndorsement.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.plauditPreEndorsement.description');
    }

    public function addEndorserFieldToStep3($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $output .= $smarty->fetch($this->getTemplateResource('endorserField.tpl'));
        return false;
    }

    public function allowStep3FormToReadOurFields($hookName, $params)
    {
        $formFields = &$params[1];
        $ourFields = ['endorserEmail'];

        $formFields = array_merge($formFields, $ourFields);
    }

    public function step3SaveOurFieldsInDatabase($hookName, $params)
    {
        $step3Form = $params[0];
        $publication = $step3Form->submission->getCurrentPublication();
        $endorserEmail = $step3Form->getData('endorserEmail');

        $publication->setData('endorserEmail', $endorserEmail);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }

    public function addOurFieldsToPublicationSchema($hookName, $params)
    {
        $schema = &$params[0];

        $schema->properties->{'endorserEmail'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }
}
