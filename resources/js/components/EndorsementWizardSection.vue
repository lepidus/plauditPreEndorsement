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
            <td :title="endorsement.name" class="endorsementWizardTruncate">{{ truncate(endorsement.name) }}</td>
            <td :title="endorsement.email" class="endorsementWizardTruncate">{{ truncate(endorsement.email) }}</td>
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
import { onMounted } from "vue";
import EndorsementFormModal from "./EndorsementFormModal.vue";
import { truncate } from "../utils/truncate.js";
import { useEndorsements } from "../composables/useEndorsements.js";

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

const { endorsements, isLoading, reload } = useEndorsements(props.submissionId);

function openAddModal() {
  openSideModal(EndorsementFormModal, {
    submissionId: props.submissionId,
    onSaved: () => reload(),
  });
}

function openEditModal(endorsement) {
  openSideModal(EndorsementFormModal, {
    submissionId: props.submissionId,
    endorsementId: endorsement.id,
    initialName: endorsement.name,
    initialEmail: endorsement.email,
    onSaved: () => reload(),
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
          reload();
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
  reload();
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
  border-bottom: 1px solid var(--pkpColor-border, #ddd);
}

.endorsementWizardTable td.endorsementWizardTruncate {
  max-width: 20rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  word-break: break-all;
}

.endorsementWizardTable th {
  font-weight: 600;
  background: var(--pkpColor-tableHeader, #f9f9f9);
}

.endorsementWizardItemActions {
  display: flex;
  gap: 0.5rem;
}

.endorsementWizardEmpty {
  color: var(--pkpColor-description, #999);
  font-style: italic;
}
</style>
