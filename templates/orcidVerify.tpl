{**
 * templates/orcidVerify.tpl
 *
 * Copyright (c) 2022 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Page template to display ORCID verification success or failure.
 *}
{include file="frontend/components/header.tpl"}

<div class="page page_message">
    {include file="frontend/components/breadcrumbs.tpl" currentTitleKey="plugins.generic.plauditPreEndorsement.verify.title"}
    <h2>
        {translate key="plugins.generic.plauditPreEndorsement.verify.title"}
    </h2>
    <div class="description">
        {if not isset($errorType)}
            <p>
                <span class="orcid"><a href="{$orcid|escape}" target="_blank">{$orcid|escape}</a></span>
            </p>
            <div class="orcid-success">
                {translate key="plugins.generic.plauditPreEndorsement.verify.success"}
            </div>
        {else}
            <div class="orcid-failure">
                {if isset($orcidAPIError)}
                    {$orcidAPIError|escape}
                {/if}    
                
                {translate key="plugins.generic.plauditPreEndorsement.verify.{$errorType|escape}"}
            </div>
            {translate key="plugins.generic.plauditPreEndorsement.failure.contact"}
        {/if}
    </div>
</div>

{include file="frontend/components/footer.tpl"}