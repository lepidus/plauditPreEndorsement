{*
  * Copyright (c) 2022 Lepidus Tecnologia
  * Copyright (c) 2022 SciELO
  * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
  *
  *}

<link rel="stylesheet" type="text/css" href="/plugins/generic/plauditPreEndorsement/styles/endorserWorkflowStyleSheet.css">
{capture assign=updateEndorserEmail}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.plauditPreEndorsement.controllers.PlauditPreEndorsementHandler" op="updateEndorser" escape=false}{/capture}

<div class="pkp_form" id="updateEndorserForm">
    <div class="endorserFieldDiv">
        <label class="label">{translate key="plugins.generic.plauditPreEndorsement.endorserName"}</label>
        {if not $confirmedEndorsement}
            {fbvElement type="text" name="endorserNameWorkflow" id="endorserNameWorkflow" value=$endorserName maxlength="90" size=$fbvStyles.size.MEDIUM}
        {else}
            <span>{$endorserName}</span>
        {/if}
    </div>

    <div class="endorserFieldDiv">
        <label class="label">{translate key="plugins.generic.plauditPreEndorsement.endorserEmail"}</label>
        {if not $endorserEmail}
            {fbvElement type="email" name="endorserEmailWorkflow" id="endorserEmailWorkflow" value=$endorserEmail maxlength="90" size=$fbvStyles.size.MEDIUM}
        {else}
            <span>{$endorserEmail}</span>
        {/if}
    </div>

    {if $endorserOrcid}
        <div class="endorserFieldDiv">
            <label class="label">{translate key="plugins.generic.plauditPreEndorsement.endorserOrcid"}</label>
            <span class="orcid"><a href="{$endorserOrcid|escape}" target="_blank">{$endorserOrcid|escape}</a></span>
        </div>
    {/if}

    <span>
        {if $confirmedEndorsement}
            <div id="endorsementConfirmed">{translate key="plugins.generic.plauditPreEndorsement.endorsementConfirmed"}</div>
        {else}
            <div id="endorsementNotConfirmed">{translate key="plugins.generic.plauditPreEndorsement.endorsementNotConfirmed"}</div>
        {/if}
    </span>

    {if not $confirmedEndorsement}
        <div class="formButtons">
            <button id="updateEndorserSubmit" type="button" class="pkp_button submitFormButton">{translate key="common.save"}</button>
        </div>
    {/if}
</div>

{if not $confirmedEndorsement}
<script>
    function updateSuccess(){ldelim}
        alert("{translate key="form.saved"}");
    {rdelim}

    async function makeSubmit(e){ldelim}
        $.post(
            "{$updateEndorserEmail}",
            {ldelim}
                submissionId: {$submissionId},
                endorserName: $('input[name=endorserNameWorkflow]').val(),
                endorserEmail: $('input[name=endorserEmailWorkflow]').val()
            {rdelim},
            updateSuccess()
        );
    {rdelim}

    $(function(){ldelim}
        $('#updateEndorserSubmit').click(makeSubmit);
    {rdelim});
</script>
{/if}