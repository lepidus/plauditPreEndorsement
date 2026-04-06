<template>
  <div class="endorsementWizardReview">
    <h3>{{ title }}</h3>

    <div v-if="isLoading" class="endorsementReviewLoading">
      <PkpSpinner />
    </div>

    <div v-else>
      <ul v-if="endorsements.length > 0" class="endorsementReviewList">
        <li v-for="endorsement in endorsements" :key="endorsement.id">
          <strong>{{ endorsement.name }}</strong> — {{ endorsement.email }}
        </li>
      </ul>
      <p v-else class="endorsementReviewEmpty">
        {{ t("common.none") }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";

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
});

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

onMounted(() => {
  loadEndorsements();
});
</script>

<style scoped>
.endorsementWizardReview {
  margin: 1rem 0;
}

.endorsementWizardReview h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1rem;
  font-weight: 600;
}

.endorsementReviewLoading {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.endorsementReviewList {
  margin: 0;
  padding-left: 1.5rem;
}

.endorsementReviewList li {
  margin: 0.25rem 0;
}

.endorsementReviewEmpty {
  color: #999;
  font-style: italic;
  margin: 0;
}
</style>
