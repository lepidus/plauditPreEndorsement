<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\settings;

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class Actions
{
    private $plugin;

    public function __construct(&$plugin)
    {
        $this->plugin = &$plugin;
    }

    public function execute($request, $actionArgs, $parentActions)
    {
        if (!$this->plugin->getEnabled()) {
            return $parentActions;
        }

        $router = $request->getRouter();
        $settingsLinkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->plugin->getName(), 'category' => 'generic']),
                $this->plugin->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        return array_merge([$settingsLinkAction], $parentActions);
    }
}
