<template>
  <div class="submissionWizard__reviewPanel">
    <div class="submissionWizard__reviewPanel__header">
      <h3 :id="headingId">{{ title }}</h3>
      <pkp-button
        :aria-describedby="headingId"
        class="submissionWizard__reviewPanel__edit"
        @click="onEdit"
      >
        {{ t("common.edit") }}
      </pkp-button>
    </div>
    <div class="submissionWizard__reviewPanel__body">
      <div v-if="isLoading" class="submissionWizard__reviewPanel__item">
        <PkpSpinner />
      </div>
      <template v-else>
        <div
          v-for="endorsement in endorsements"
          :key="endorsement.id"
          class="submissionWizard__reviewPanel__item"
        >
          <strong :title="endorsement.name">{{ truncate(endorsement.name) }}</strong>
          —
          <span :title="endorsement.email">{{ truncate(endorsement.email) }}</span>
        </div>
        <div v-if="endorsements.length === 0" class="submissionWizard__reviewPanel__item">
          {{ t("common.none") }}
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from "vue";
import { truncate } from "../utils/truncate.js";
import { useEndorsements } from "../composables/useEndorsements.js";

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

const props = defineProps({
  submissionId: {
    type: Number,
    required: true,
  },
  title: {
    type: String,
    default: "",
  },
  stepId: {
    type: String,
    default: "details",
  },
});

const emit = defineEmits(["edit-step"]);

const headingId = `review-plauditPreEndorsement-${props.submissionId}`;

const { endorsements, isLoading, reload } = useEndorsements(props.submissionId);

function onEdit() {
  emit("edit-step", props.stepId);
}

onMounted(() => {
  reload();
});
</script>
