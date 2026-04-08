<template>
  <div class="submissionWizard__reviewPanel">
    <div class="submissionWizard__reviewPanel__header">
      <h3 :id="headingId">{{ title }}</h3>
      <pkp-button
        :aria-describedby="headingId"
        class="submissionWizard__reviewPanel__edit"
        @click="editStep"
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
          v-if="endorsements.length > 0"
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
import { ref, onMounted, getCurrentInstance } from "vue";

const { useLocalize } = pkp.modules.useLocalize;
const { useUrl } = pkp.modules.useUrl;
const { useFetch } = pkp.modules.useFetch;

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

const headingId = `review-plauditPreEndorsement-${props.submissionId}`;

const MAX_DISPLAY_LENGTH = 40;
function truncate(value) {
  if (!value) return "";
  const str = String(value);
  return str.length > MAX_DISPLAY_LENGTH
    ? str.substring(0, MAX_DISPLAY_LENGTH) + "…"
    : str;
}

const endorsements = ref([]);
const isLoading = ref(true);

const { apiUrl } = useUrl(`endorsements/${props.submissionId}`);
const { data, fetch: fetchEndorsements } = useFetch(apiUrl);

async function loadEndorsements() {
  isLoading.value = true;
  try {
    await fetchEndorsements();
    endorsements.value = data.value?.items || [];
  } catch (e) {
    // silent
  } finally {
    isLoading.value = false;
  }
}

const instance = getCurrentInstance();
function editStep() {
  let parent = instance && instance.proxy ? instance.proxy.$parent : null;
  while (parent) {
    if (typeof parent.openStep === "function") {
      parent.openStep(props.stepId);
      return;
    }
    parent = parent.$parent;
  }
}

onMounted(() => {
  loadEndorsements();
});
</script>
