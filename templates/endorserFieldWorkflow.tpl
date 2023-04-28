{*
  * Copyright (c) 2022 Lepidus Tecnologia
  * Copyright (c) 2022 SciELO
  * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
  *
  *}

<link rel="stylesheet" type="text/css" href="/plugins/generic/plauditPreEndorsement/styles/endorserWorkflowStyleSheet.css">

<div class="pkp_form" id="updateEndorserForm">
    <div class="endorserFieldDiv">
        <label class="label">{translate key="plugins.generic.plauditPreEndorsement.endorserName"}</label>
        {if $canEditEndorsement}
            {fbvElement type="text" name="endorserNameWorkflow" id="endorserNameWorkflow" value=$endorserName maxlength="90" size=$fbvStyles.size.MEDIUM}
        {else}
            <span>{$endorserName|escape}</span>
        {/if}
    </div>

    <div class="endorserFieldDiv">
        <label class="label">{translate key="plugins.generic.plauditPreEndorsement.endorserEmail"}</label>
        {if $canEditEndorsement}
            {fbvElement type="email" name="endorserEmailWorkflow" id="endorserEmailWorkflow" value=$endorserEmail maxlength="90" size=$fbvStyles.size.MEDIUM}
        {else}
            <span>{$endorserEmail|escape}</span>
        {/if}
    </div>

    {if $endorserOrcid}
        <div class="endorserFieldDiv">
            <label class="label">{translate key="plugins.generic.plauditPreEndorsement.endorserOrcid"}</label>
            <span class="orcid"><a href="{$endorserOrcid|escape}" target="_blank">{$endorserOrcid|escape}</a></span>
        </div>
    {/if}

    {if !is_null($endorsementStatus)}
        <span>
            <div id="endorsement{$endorsementStatusSuffix}">{translate key="plugins.generic.plauditPreEndorsement.endorsement{$endorsementStatusSuffix}"}</div>
        </span>
    {/if}

    {if isset($endorserEmailCount)}
        <span>
            {if $endorserEmailCount == 1}
                <div id="endorserEmailCount">{translate key="plugins.generic.plauditPreEndorsement.endorserEmailCount.one"}</div>
            {else}
                <div id="endorserEmailCount">{translate key="plugins.generic.plauditPreEndorsement.endorserEmailCount.many" numEmails=$endorserEmailCount}</div>
            {/if}
        </span>
    {/if}

    <div class="formButtons">
        {if $canRemoveEndorsement}
            <button id="removeEndorsementSubmit" type="button" class="pkp_button submitFormButton">{translate key="plugins.generic.plauditPreEndorsement.removeEndorsement"}</button>
        {/if}
        
        {if $canEditEndorsement}
            <button id="updateEndorserSubmit" type="button" class="pkp_button submitFormButton">{translate key="common.save"}</button>
        {/if}

        {if $canSendEndorsementManually}
            <button id="sendEndorsementManuallySubmit" type="button" class="pkp_button submitFormButton">{translate key="plugins.generic.plauditPreEndorsement.sendEndorsementToPlaudit"}</button>
        {/if}
    </div>
</div>

{if $canEditEndorsement}
<script>
    function updateEndorsementSuccess(){ldelim}
        alert("{translate key="form.saved"}");
    {rdelim}

    async function requestUpdateEndorsement(e){ldelim}
        $.post(
            "{$updateEndorserUrl}",
            {ldelim}
                submissionId: {$submissionId},
                endorserName: $('input[name=endorserNameWorkflow]').val(),
                endorserEmail: $('input[name=endorserEmailWorkflow]').val()
            {rdelim},
            updateEndorsementSuccess()
        );
    {rdelim}

    $(function(){ldelim}
        $('#updateEndorserSubmit').click(requestUpdateEndorsement);
    {rdelim});
</script>
{/if}

{if $canSendEndorsementManually}
<script>
    function sendEndorsementManuallySuccess(){ldelim}
        alert("{translate key="form.saved"}");
    {rdelim}

    async function requestSendEndorsementManually(e){ldelim}
        $.post(
            "{$sendEndorsementManuallyUrl}",
            {ldelim}
                submissionId: {$submissionId}
            {rdelim},
            sendEndorsementManuallySuccess()
        );
    {rdelim}

    $(function(){ldelim}
        $('#sendEndorsementManuallySubmit').click(requestSendEndorsementManually);
    {rdelim});
</script>
{/if}

{if $canRemoveEndorsement}
<script>
    async function requestRemoveEndorsement(e){ldelim}
        $.post(
            "{$removeEndorsementUrl}",
            {ldelim}
                submissionId: {$submissionId}
            {rdelim}
        );
    {rdelim}

    function confirmEndorsementRemoval(){ldelim}
        let removalConfirmed = confirm("{translate key="plugins.generic.plauditPreEndorsement.removalConfirmationMessage"}");
        if(removalConfirmed) {ldelim}
            requestRemoveEndorsement();
        {rdelim}
    {rdelim}
    
    $(function(){ldelim}
        $('#removeEndorsementSubmit').click(confirmEndorsementRemoval);
    {rdelim});
</script>
{/if}