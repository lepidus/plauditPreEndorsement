{**
 * templates/settingsForm.tpl
 *
 * Copyright (c) 2022 - 2024 SciELO
 * Copyright (c) 2022 - 2024 Lepidus Tecnologia
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* Plaudit Pre-Endorsement plugin settings
*
*}

<script>
$(function() {ldelim}
    $('#plauditPreEndorsementSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

    $('#showCredentialsFields').click(function(e) {ldelim}
        e.preventDefault();
        $('#credentialsFields').slideToggle(300);
    {rdelim});
{rdelim});
</script>
<link rel="stylesheet" type="text/css" href="/plugins/generic/plauditPreEndorsement/styles/endorserSettingsForm.css">
<form class="pkp_form" id="plauditPreEndorsementSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
    <div id="plauditPreEndorsementSettings">
        <p id="description">
            {translate key="plugins.generic.plauditPreEndorsement.settings.description" }
        </p>

        {csrf}
        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="orcidProfileSettingsFormNotification"}
        {fbvFormArea id="orcidApiSettings" title="plugins.generic.plauditPreEndorsement.settings.title"}
            {fbvFormSection}
                {if $hasCredentials}
                    <p class="pkpNotification pkpNotification--success">
                        {translate key="plugins.generic.plauditPreEndorsement.settings.credentialsRegistered"}
                        <a href="#" id="showCredentialsFields">{translate key="plugins.generic.plauditPreEndorsement.settings.clickHere"}</a>.
                    </p>
                    <p><span class="formRequired">{translate key="plugins.generic.plauditPreEndorsement.settings.securityNotice"}</span></p>
                {/if}
                <div id="credentialsFields" {if $hasCredentials}style="display:none;"{/if}>
                    {if $globallyConfigured}
                        <p>
                            {translate key="plugins.generic.plauditPreEndorsement.settings.globallyconfigured"}
                        </p>
                    {/if}
                    {fbvElement id="orcidAPIPath" class="orcidAPIPath" type="select" translate="true" from=$orcidApiUrls selected=$orcidAPIPath required="true" label="plugins.generic.plauditPreEndorsement.settings.orcidAPIPath" disabled=$globallyConfigured}
                    {fbvElement type="text" id="orcidClientId" class="orcidClientId" value=$orcidClientId required="true" label="plugins.generic.plauditPreEndorsement.settings.orcidClientId" maxlength="40" size=$fbvStyles.size.MEDIUM disabled=$globallyConfigured}
                    {if $globallyConfigured}
                        <p>
                            {translate key="plugins.generic.plauditPreEndorsement.settings.orcidClientSecret"}: <i>{translate key="plugins.generic.plauditPreEndorsement.settings.hidden"}</i>
                        </p>
                    {else}
                        {fbvElement type="text" id="orcidClientSecret" class="orcidClientSecret" value=$orcidClientSecret required="true" label="plugins.generic.plauditPreEndorsement.settings.orcidClientSecret" maxlength="40" size=$fbvStyles.size.MEDIUM disabled=$globallyConfigured}
                    {/if}
                    {fbvElement type="text" id="plauditAPISecret" class="plauditAPISecret" value=$plauditAPISecret required="true" label="plugins.generic.plauditPreEndorsement.settings.plauditAPISecret" size=$fbvStyles.size.MEDIUM}
                    <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
                </div>
            {/fbvFormSection}
        {/fbvFormArea}
        {fbvFormButtons}
    </div>
</form>