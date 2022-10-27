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

define('ORCID_URL', 'https://orcid.org/');
define('ORCID_URL_SANDBOX', 'https://sandbox.orcid.org/');
define('ORCID_API_URL_PUBLIC', 'https://pub.orcid.org/');
define('ORCID_API_URL_PUBLIC_SANDBOX', 'https://pub.sandbox.orcid.org/');
define('ORCID_API_URL_MEMBER', 'https://api.orcid.org/');
define('ORCID_API_URL_MEMBER_SANDBOX', 'https://api.sandbox.orcid.org/');
define('ORCID_API_SCOPE_PUBLIC', '/authenticate');
define('ORCID_API_SCOPE_MEMBER', '/activities/update');

class PlauditPreEndorsementPlugin extends GenericPlugin
{
    const HANDLER_COMPONENT = 'plugins.generic.plauditPreEndorsement.controllers.PlauditPreEndorsementHandler';
    
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'addEndorserFieldsToStep3'));

            HookRegistry::register('submissionsubmitstep3form::readuservars', array($this, 'allowStep3FormToReadOurFields'));
            HookRegistry::register('submissionsubmitstep3form::execute', array($this, 'step3SaveEndorserEmail'));
            HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'step4SendEmailToEndorser'));
            HookRegistry::register('Schema::get::publication', array($this, 'addOurFieldsToPublicationSchema'));
            HookRegistry::register('Template::Workflow::Publication', array($this, 'addEndorserFieldsToWorkflow'));
            HookRegistry::register('LoadComponentHandler', array($this, 'setupPlauditPreEndorsementHandler'));
        }

        return $success;
    }

    public function setupPlauditPreEndorsementHandler($hookName, $params)
    {
        $component = &$params[0];
        if ($component == self::HANDLER_COMPONENT) {
            return true;
        }
        return false;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.plauditPreEndorsement.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.plauditPreEndorsement.description');
    }

    public function addEndorserFieldsToStep3($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $submissionId = $smarty->smarty->get_template_vars('submissionId');
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $smarty->assign('endorserName', $publication->getData('endorserName'));
        $smarty->assign('endorserEmail', $publication->getData('endorserEmail'));

        $output .= $smarty->fetch($this->getTemplateResource('endorserFieldStep3.tpl'));
        return false;
    }

    public function allowStep3FormToReadOurFields($hookName, $params)
    {
        $formFields = &$params[1];
        $ourFields = ['endorserName', 'endorserEmail'];

        $formFields = array_merge($formFields, $ourFields);
    }

    public function step3SaveEndorserEmail($hookName, $params)
    {
        $step3Form = $params[0];
        $publication = $step3Form->submission->getCurrentPublication();
        $endorserName = $step3Form->getData('endorserName');
        $endorserEmail = $step3Form->getData('endorserEmail');

        $publication->setData('endorserName', $endorserName);
        $publication->setData('endorserEmail', $endorserEmail);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }
    
    public function step4SendEmailToEndorser($hookName, $params)
    {
        $step4Form = $params[0];
        $publication = $step4Form->submission->getCurrentPublication();

        if(!is_null($publication->getData('endorserEmail'))) {
            $this->sendEmailToEndorser($publication);
        }
    }

    public function addOurFieldsToPublicationSchema($hookName, $params)
    {
        $schema = &$params[0];

        $schema->properties->{'endorserName'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        $schema->properties->{'endorserEmail'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        $schema->properties->{'endorserEmailToken'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    function addEndorserFieldsToWorkflow($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $submission = $smarty->get_template_vars('submission');
        $publication = $submission->getCurrentPublication();
        $smarty->assign('submissionId', $submission->getId());
        $smarty->assign('endorserName', $publication->getData('endorserName'));
        $smarty->assign('endorserEmail', $publication->getData('endorserEmail'));

        $output .= sprintf(
            '<tab id="screeningInfo" label="%s">%s</tab>',
            __('plugins.generic.plauditPreEndorsement.preEndorsement'),
            $smarty->fetch($this->getTemplateResource('endorserFieldWorkflow.tpl'))
        );
    }

    public function sendEmailToEndorser($publication)
    {
        $request = PKPApplication::get()->getRequest();
        $context = $request->getContext();
        $endorserName = $publication->getData('endorserName');
        $endorserEmail = $publication->getData('endorserEmail');

        if (!is_null($context) && !is_null($endorserEmail)) {
            $emailTemplate = 'ORCID_REQUEST_ENDORSER_AUTHORIZATION';
            $email = $this->getMailTemplate($emailTemplate, $context);

            $email->setFrom($context->getData('contactEmail'), $context->getData('contactName'));
            $email->setRecipients([['name' => $endorserName, 'email' => $endorserEmail]]);

            $endorserEmailToken = md5(microtime() . $endorserEmail);
            $oauthUrl = $this->buildOAuthUrl(['token' => $endorserEmailToken, 'state' => $publication->getId()]);

            $email->sendWithParams([
                'orcidOauthUrl' => $oauthUrl,
                'endorserName' => htmlspecialchars($endorserName),
                'preprintTitle' => htmlspecialchars($publication->getLocalizedTitle()),
            ]);

            $publication->setData('endorserEmailToken', $endorserEmailToken);
			$publicationDao = DAORegistry::getDAO('PublicationDAO');
			$publicationDao->updateObject($publication);
			
        }
    }

    function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . '/emailTemplates.xml';
    }

    private function getMailTemplate($emailKey, $context = null)
    {
        import('lib.pkp.classes.mail.MailTemplate');
        return new MailTemplate($emailKey, null, $context, false);
    }

    function orcidIsGloballyConfigured()
    {
        $apiUrl = Config::getVar('orcid', 'api_url');
        $clientId = Config::getVar('orcid', 'client_id');
        $clientSecret = Config::getVar('orcid', 'client_secret');
        return isset($apiUrl) && trim($apiUrl) && isset($clientId) && trim($clientId) &&
            isset($clientSecret) && trim($clientSecret);
    }

    function getActions($request, $actionArgs) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			array(
				new LinkAction(
					'settings',
					new AjaxModal($router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')), $this->getDisplayName()),
					__('manager.plugins.settings'),
					null
				),
			),
			parent::getActions($request, $actionArgs)
		);
	}

    function manage($args, $request) {
		$context = $request->getContext();
		$contextId = ($context == null) ? 0 : $context->getId();

		switch ($request->getUserVar('verb')) {
			case 'settings':
				$templateMgr = TemplateManager::getManager();
				$templateMgr->registerPlugin('function', 'plugin_url', array($this, 'smartyPluginUrl'));
				$apiOptions = [
					ORCID_API_URL_PUBLIC => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.public',
					ORCID_API_URL_PUBLIC_SANDBOX => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.publicSandbox',
					ORCID_API_URL_MEMBER => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.member',
					ORCID_API_URL_MEMBER_SANDBOX => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.memberSandbox'
				];
				$templateMgr->assign('orcidApiUrls', $apiOptions);

				$this->import('PlauditPreEndorsementSettingsForm');
				$form = new PlauditPreEndorsementSettingsForm($this, $contextId);
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

    function buildOAuthUrl($redirectParams) {
		$request = PKPApplication::get()->getRequest();
		$context = $request->getContext();
		assert($context != null);
		$contextId = $context->getId();

		if ($this->isMemberApiEnabled($contextId)) {
			$scope = ORCID_API_SCOPE_MEMBER;
		} else {
			$scope = ORCID_API_SCOPE_PUBLIC;
		}
		
        $redirectUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::HANDLER_COMPONENT,
			'orcidVerify', null, $redirectParams);

		return $this->getOauthPath() . 'authorize?' . http_build_query(
				array(
					'client_id' => $this->getSetting($contextId, 'orcidClientId'),
					'response_type' => 'code',
					'scope' => $scope,
					'redirect_uri' => $redirectUrl)
			);
	}

    public function isMemberApiEnabled($contextId) {
		$apiUrl = $this->getSetting($contextId, 'orcidProfileAPIPath');
		if ($apiUrl === ORCID_API_URL_MEMBER || $apiUrl === ORCID_API_URL_MEMBER_SANDBOX) {
			return true;
		} else {
			return false;
		}
	}

    function getOauthPath() {
		return $this->getOrcidUrl() . 'oauth/';
	}

    function getOrcidUrl() {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = ($context == null) ? 0 : $context->getId();

		$apiPath = $this->getSetting($contextId, 'orcidProfileAPIPath');
		if ($apiPath == ORCID_API_URL_PUBLIC || $apiPath == ORCID_API_URL_MEMBER) {
			return ORCID_URL;
		} else {
			return ORCID_URL_SANDBOX;
		}
	}
}
