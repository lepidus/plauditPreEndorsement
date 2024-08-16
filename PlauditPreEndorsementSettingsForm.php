<?php

/**
 * @file PlauditPreEndorsementSettingsForm.inc.php
 *
 * Copyright (c) 2022 - 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PlauditPreEndorsementSettingsForm
 * @ingroup plugins_generic_plauditPreEndorsement
 *
 * @brief Form for site admins to modify Plaudit Pre-Endorsement plugin settings
 */

namespace APP\plugins\generic\plauditPreEndorsement;

use PKP\form\Form;
use APP\template\TemplateManager;
use APP\core\Application;
use PKP\config\Config;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorCustom;
use APP\plugins\generic\plauditPreEndorsement\classes\api\APIKeyEncryption;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidCredentialsValidator;

class PlauditPreEndorsementSettingsForm extends Form
{
    public const CONFIG_VARS = array(
        'orcidAPIPath' => 'string',
        'orcidClientId' => 'string',
        'orcidClientSecret' => 'string',
        'plauditAPISecret' => 'string'
    );

    public $contextId;
    public $plugin;
    public $validator;

    public function __construct($plugin, $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;
        $orcidValidator = new OrcidCredentialsValidator($plugin);
        $this->validator = $orcidValidator;
        $template = APIKeyEncryption::secretConfigExists() ? 'settingsForm.tpl' : 'tokenError.tpl';
        parent::__construct($plugin->getTemplateResource($template));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));

        if (!$this->orcidIsGloballyConfigured()) {
            $this->addCheck(new FormValidator($this, 'orcidAPIPath', 'required', 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPathRequired'));
            $this->addCheck(new FormValidatorCustom($this, 'orcidClientId', 'required', 'plugins.generic.plauditPreEndorsement.settings.orcidClientIdError', function ($clientId) {
                return $this->validator->validateClientId($clientId);
            }));
            $this->addCheck(new FormValidatorCustom($this, 'orcidClientSecret', 'required', 'plugins.generic.plauditPreEndorsement.settings.orcidClientSecretError', function ($clientSecret) {
                return $this->validator->validateClientSecret($clientSecret);
            }));
        }
    }

    public function initData()
    {
        $contextId = $this->contextId;
        $plugin = &$this->plugin;
        $this->_data = array();
        foreach (self::CONFIG_VARS as $configVar => $type) {
            if ($configVar === 'orcidAPIPath') {
                $this->_data[$configVar] = $plugin->getSetting($contextId, $configVar);
            } else {
                $configValue = APIKeyEncryption::decryptString($plugin->getSetting($contextId, $configVar));
                $this->_data[$configVar] = $configValue;
            }
        }
    }

    public function readInputData()
    {
        $this->readUserVars(array_keys(self::CONFIG_VARS));
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('globallyConfigured', $this->orcidIsGloballyConfigured());
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('applicationName', Application::get()->getName());
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $plugin = &$this->plugin;
        $contextId = $this->contextId;
        foreach (self::CONFIG_VARS as $configVar => $type) {
            if ($configVar === 'orcidAPIPath') {
                $orcidAPIPath = trim($this->getData($configVar), "\"\';");
                $plugin->updateSetting(
                    $contextId,
                    $configVar,
                    $orcidAPIPath,
                    $type
                );
            } else {
                $plugin->updateSetting(
                    $contextId,
                    $configVar,
                    APIKeyEncryption::encryptString($this->getData($configVar)),
                    $type
                );
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

    public function orcidIsGloballyConfigured(): bool
    {
        $apiUrl = Config::getVar('orcid', 'api_url');
        $clientId = Config::getVar('orcid', 'client_id');
        $clientSecret = Config::getVar('orcid', 'client_secret');
        return isset($apiUrl) && trim($apiUrl) && isset($clientId) && trim($clientId) &&
            isset($clientSecret) && trim($clientSecret);
    }
}
