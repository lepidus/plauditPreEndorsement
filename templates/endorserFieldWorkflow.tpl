{*
  * Copyright (c) 2022 - 2024 SciELO
  * Copyright (c) 2022 - 2024 Lepidus Tecnologia
  * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
  *
  *}

<link rel="stylesheet" type="text/css" href="/plugins/generic/plauditPreEndorsement/styles/endorserWorkflowStyleSheet.css">

<div class="pkp_form" id="updateEndorserForm">
    <list-panel v-bind="components.endorsers" @set="set">
        <pkp-header slot="header">
            <h2>
                {{ components.endorsers.title }}
            </h2>
            <template slot="actions">
                <pkp-button @click="$modal.show('template')">
                    {translate key="common.add"}
                </pkp-button>
            </template>
        </pkp-header>
    </list-panel>
    <modal
        :close-label="__('common.close')"
        name="template"
        :title="'{translate key="manager.emails.addEmail"}'"
    >
        <pkp-form
            v-bind="components.endorsers.form"
            @set="set"
        ></pkp-form>
    </modal>
</div>