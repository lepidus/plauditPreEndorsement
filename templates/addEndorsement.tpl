<script>
    $(function() {ldelim}
        $('#endorsementForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<div id="AddEndorsementForm">

    {capture assign=actionUrl}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.plauditPreEndorsement.controllers.grid.EndorsementGridHandler" op="updateEndorser" submissionId=$submissionId escape=false}{/capture}
    <form class="pkp_form" id="endorsementForm" method="post" action="{$actionUrl}">
        {csrf}
        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="OASwitchboardSettingsFormNotification"}
        <input type="hidden" name="rowId" value="{$rowId|escape}" />
        {fbvFormArea id="endorsementForm"}
            {fbvFormSection label="plugins.generic.plauditPreEndorsement.endorserName" required=true}
                {fbvElement type="text" id="endorserName" value=$endorserName|escape size=$fbvStyles.size.MEDIUM}
            {/fbvFormSection}

            {fbvFormSection label="plugins.generic.plauditPreEndorsement.endorserEmail" required=true}
                {fbvElement type="text" id="endorserEmail" value=$endorserEmail|escape size=$fbvStyles.size.MEDIUM}
            {/fbvFormSection}

            {fbvFormButtons submitText="common.save"}

        {/fbvFormArea}
    </form>
</div>