<?php

/**
 * @file PlauditPreEndorsementSettingsForm.inc.php
 *
 * Copyright (c) 2022 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PlauditPreEndorsementSettingsForm
 * @ingroup plugins_generic_plauditPreEndorsement
 *
 * @brief Form for site admins to modify Plaudit Pre-Endorsement plugin settings
 */


import('lib.pkp.classes.form.Form');
import('plugins.generic.plauditPreEndorsement.classes.OrcidCredentialsValidator');

class PlauditPreEndorsementSettingsForm extends Form
{

    const CONFIG_VARS = array(
        'orcidAPIPath' => 'string',
        'orcidClientId' => 'string',
        'orcidClientSecret' => 'string',
        'plauditAPISecret' => 'string'
    );

    var $contextId;
    var $plugin;
    var $validator;

    function __construct($plugin, $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;
        $orcidValidator = new OrcidCredentialsValidator($plugin);
        $this->validator = $orcidValidator;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));

        if (!$this->plugin->orcidIsGloballyConfigured()) {
            $this->addCheck(new FormValidator($this, 'orcidAPIPath', 'required', 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPathRequired'));
            $this->addCheck(new FormValidatorCustom($this, 'orcidClientId', 'required', 'plugins.generic.plauditPreEndorsement.settings.orcidClientIdError', function ($clientId) {
                return $this->validator->validateClientId($clientId);
            }));
            $this->addCheck(new FormValidatorCustom($this, 'orcidClientSecret', 'required', 'plugins.generic.plauditPreEndorsement.settings.orcidClientSecretError', function ($clientSecret) {
                return $this->validator->validateClientSecret($clientSecret);
            }));
        }
    }

    function initData()
    {
        $contextId = $this->contextId;
        $plugin = &$this->plugin;
        $this->_data = array();
        foreach (self::CONFIG_VARS as $configVar => $type) {
            $this->_data[$configVar] = $plugin->getSetting($contextId, $configVar);
        }
    }

    function readInputData()
    {
        $this->readUserVars(array_keys(self::CONFIG_VARS));
    }

    function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('globallyConfigured', $this->plugin->orcidIsGloballyConfigured());
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('applicationName', Application::get()->getName());
        return parent::fetch($request, $template, $display);
    }

    function execute(...$functionArgs)
    {
        $plugin = &$this->plugin;
        $contextId = $this->contextId;
        foreach (self::CONFIG_VARS as $configVar => $type) {
            if ($configVar === 'orcidAPIPath') {
                $plugin->updateSetting($contextId, $configVar, trim($this->getData($configVar), "\"\';"), $type);
            } else {
                $plugin->updateSetting($contextId, $configVar, $this->getData($configVar), $type);
            }
        }

        parent::execute(...$functionArgs);
    }

    public function _checkPrerequisites()
    {
        $messages = array();

        $clientId = $this->getData('orcidClientId');
        if (!$this->validator->validateClientId($clientId)) {
            $messages[] = __('plugins.generic.plauditPreEndorsement.settings.orcidClientIdError');
        }
        $clientSecret = $this->getData('orcidClientSecret');
        if (!$this->validator->validateClientSecret($clientSecret)) {
            $messages[] = __('plugins.generic.plauditPreEndorsement.settings.orcidClientSecretError');
        }
        if (strlen($clientId) == 0 or strlen($clientSecret) == 0) {
            $this->plugin->setEnabled(false);
        }
        return $messages;
    }
}
