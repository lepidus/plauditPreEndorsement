{**
 * templates/endorsementDeclined.tpl
 *
 * Copyright (c) 2026 SciELO
 * Copyright (c) 2026 Lepidus Tecnologia
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Page template to display ORCID declining information.
 *}
{include file="frontend/components/header.tpl"}

<div class="page page_message">
    {include file="frontend/components/breadcrumbs.tpl" currentTitleKey="plugins.generic.plauditPreEndorsement.endorsementDecline.title"}
    <h2>
        {translate key="plugins.generic.plauditPreEndorsement.endorsementDecline.title"}
    </h2>
    <div class="description">
        {if not isset($errorType)}
            <p>
                {translate key="plugins.generic.plauditPreEndorsement.endorsementDecline.success"}
            </p>
        {else}
            <p>
                {translate key="plugins.generic.plauditPreEndorsement.endorsementDecline.{$errorType|escape}"}
            </p>
            </br>
            {translate key="plugins.generic.plauditPreEndorsement.failure.contact" contactEmail=$contactEmail}
        {/if}
    </div>
</div>

{include file="frontend/components/footer.tpl"}