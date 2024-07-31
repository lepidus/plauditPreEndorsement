

<link rel="stylesheet" type="text/css" href="/plugins/generic/plauditPreEndorsement/styles/endorserWorkflowStyleSheet.css">

<div class="pkpWorkflow__contributors" id="endorsermentForm" style="padding-bottom: 2rem;">
    {capture assign=endorsersGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.plauditPreEndorsement.controllers.grid.EndorsementGridHandler" op="fetchGrid" submissionId=$submission->getId() escape=false}{/capture}
    {load_url_in_div id="endorsersGridContainer"|uniqid url=$endorsersGridUrl inVueEl=true}
</div>
