<div
    v-if="errors.endorsement"
    class="submissionWizard__reviewPanel__item"
>
    <template>
        <notification
            v-for="(error, i) in errors.endorsement"
            :key="i"
            type="warning"
        >
            <icon icon="exclamation-triangle"></icon>
            {{ error }}
        </notification>
    </template>
</div>