<template>
  <div class="endorsementWizardSection">
    <div class="endorsementWizardActions">
      <pkp-button @click="openAddModal">
        {{ t("common.add") }}
      </pkp-button>
    </div>

    <div v-if="isLoading" class="endorsementWizardLoading">
      <PkpSpinner />
    </div>

    <div v-else>
      <table v-if="endorsements.length > 0" class="endorsementWizardTable">
        <thead>
          <tr>
            <th>{{ t("plugins.generic.plauditPreEndorsement.endorserName") }}</th>
            <th>{{ t("plugins.generic.plauditPreEndorsement.emailColumnName") }}</th>
            <th>{{ t("common.moreActions") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="endorsement in endorsements" :key="endorsement.id">
            <td>{{ endorsement.name }}</td>
            <td>{{ endorsement.email }}</td>
            <td class="endorsementWizardItemActions">
              <pkp-button @click="openEditModal(endorsement)">
                {{ t("common.edit") }}
              </pkp-button>
              <pkp-button :is-warnable="true" @click="confirmDelete(endorsement)">
                {{ t("common.delete") }}
              </pkp-button>
            </td>
          </tr>
        </tbody>
      </table>

      <p v-else class="endorsementWizardEmpty">
        {{ t("common.none") }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import EndorsementFormModal from "./EndorsementFormModal.vue";

const { useLocalize } = pkp.modules.useLocalize;
const { useUrl } = pkp.modules.useUrl;
const { useFetch } = pkp.modules.useFetch;
const { useModal } = pkp.modules.useModal;

const { t } = useLocalize();
const { openDialog, openSideModal } = useModal();

const props = defineProps({
  submissionId: {
    type: Number,
    required: true,
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

function openAddModal() {
  openSideModal(EndorsementFormModal, {
    submissionId: props.submissionId,
    onSaved: () => loadEndorsements(),
  });
}

function openEditModal(endorsement) {
  openSideModal(EndorsementFormModal, {
    submissionId: props.submissionId,
    endorsementId: endorsement.id,
    initialName: endorsement.name,
    initialEmail: endorsement.email,
    onSaved: () => loadEndorsements(),
  });
}

function confirmDelete(endorsement) {
  openDialog({
    title: t("common.delete"),
    message: t("plugins.generic.plauditPreEndorsement.removalConfirmationMessage"),
    actions: [
      {
        label: t("common.yes"),
        isPrimary: true,
        callback: async (close) => {
          const { apiUrl: deleteUrl } = useUrl(
            `endorsements/${props.submissionId}/${endorsement.id}`
          );
          const { fetch: fetchDelete } = useFetch(deleteUrl, { method: "DELETE" });
          await fetchDelete();
          loadEndorsements();
          close();
        },
      },
      {
        label: t("common.no"),
        isWarnable: true,
        callback: (close) => close(),
      },
    ],
  });
}

onMounted(() => {
  loadEndorsements();
});
</script>

<style scoped>
.endorsementWizardSection {
  margin-top: 1rem;
}

.endorsementWizardActions {
  margin-bottom: 1rem;
}

.endorsementWizardLoading {
  display: flex;
  justify-content: center;
  padding: 1rem;
}

.endorsementWizardTable {
  width: 100%;
  border-collapse: collapse;
}

.endorsementWizardTable th,
.endorsementWizardTable td {
  text-align: left;
  padding: 0.5rem;
  border-bottom: 1px solid #ddd;
}

.endorsementWizardTable th {
  font-weight: 600;
  background: #f9f9f9;
}

.endorsementWizardItemActions {
  display: flex;
  gap: 0.5rem;
}

.endorsementWizardEmpty {
  color: #999;
  font-style: italic;
}
</style>
