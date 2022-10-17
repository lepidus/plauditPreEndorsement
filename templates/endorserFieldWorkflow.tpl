{*
  * Copyright (c) 2022 Lepidus Tecnologia
  * Copyright (c) 2022 SciELO
  * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
  *
  *}

<link rel="stylesheet" type="text/css" href="/plugins/generic/plauditPreEndorsement/styles/endorserWorkflowStyleSheet.css">
{capture assign=updateEndorserEmail}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.plauditPreEndorsement.controllers.PlauditPreEndorsementHandler" op="updateEndorserEmail" escape=false}{/capture}

<div class="pkp_form" id="updateEndorserEmailForm">
    <div id="endorserEmailWorkflowDiv">
        <label class="label">{translate key="plugins.generic.plauditPreEndorsement.endorserEmail"}</label>
        {fbvElement type="email" name="endorserEmailWorkflow" id="endorserEmailWorkflow" value=$endorserEmail maxlength="90" size=$fbvStyles.size.MEDIUM}
    </div>

    <div class="formButtons">
        <button id="updateEndorserEmailSubmit" type="button" class="pkp_button submitFormButton">{translate key="common.save"}</button>
    </div>
</div>

<script>
    function updateSuccess(){ldelim}
        alert("{translate key="form.saved"}");
    {rdelim}

    async function makeSubmit(e){ldelim}
        $.post(
            "{$updateEndorserEmail}",
            {ldelim}
                submissionId: {$submissionId},
                endorserEmail: $('input[name=endorserEmailWorkflow]').val(),
            {rdelim},
            updateSuccess()
        );
    {rdelim}

    $(function(){ldelim}
        $('#updateEndorserEmailSubmit').click(makeSubmit);
    {rdelim});
</script>
