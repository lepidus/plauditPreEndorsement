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
}
