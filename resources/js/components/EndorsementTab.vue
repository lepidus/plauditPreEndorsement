<template>
  <div class="endorsementTab">
    <div class="endorsementTabHeader">
      <h2>{{ t("plugins.generic.plauditPreEndorsement.preEndorsement") }}</h2>
      <p>{{ t("plugins.generic.plauditPreEndorsement.endorsement.description") }}</p>
    </div>

    <div v-if="isLoading" class="endorsementTabLoading">
      <PkpSpinner />
    </div>

    <div v-else-if="error" class="endorsementTabError">
      <p>{{ error }}</p>
    </div>

    <div v-else class="endorsementTabContent">
      <div class="endorsementTabActions">
        <pkp-button @click="openAddModal">
          {{ t("common.add") }}
        </pkp-button>
      </div>

      <table v-if="endorsements.length > 0" class="endorsementTable">
        <thead>
          <tr>
            <th>{{ t("plugins.generic.plauditPreEndorsement.endorserName") }}</th>
            <th>{{ t("plugins.generic.plauditPreEndorsement.emailColumnName") }}</th>
            <th>{{ t("plugins.generic.plauditPreEndorsement.endorsementStatus") }}</th>
            <th>{{ t("common.moreActions") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="endorsement in endorsements" :key="endorsement.id">
            <td>
              <span :title="endorsement.name">{{ truncate(endorsement.name) }}</span>
              <span v-if="endorsement.orcid" class="endorsementOrcid">
                <a :href="endorsement.orcid" target="_blank">{{ endorsement.orcid }}</a>
              </span>
            </td>
            <td>
              <span :title="endorsement.email">{{ truncate(endorsement.email) }}</span>
              <span v-if="endorsement.emailCount" class="endorsementEmailCount">
                ({{ endorsement.emailCount }})
              </span>
            </td>
            <td>
              <span :class="getStatusClass(endorsement.status)">
                {{ getStatusLabel(endorsement.status) }}
              </span>
            </td>
            <td class="endorsementActions">
              <pkp-button :is-warnable="false" @click="openEditModal(endorsement)">
                {{ t("common.edit") }}
              </pkp-button>
              <pkp-button :is-warnable="true" @click="confirmDelete(endorsement)">
                {{ t("common.delete") }}
              </pkp-button>
              <pkp-button
                v-if="canSendManually(endorsement)"
                @click="sendManually(endorsement)"
              >
                {{ t("plugins.generic.plauditPreEndorsement.sendEndorsementToPlaudit") }}
              </pkp-button>
            </td>
          </tr>
        </tbody>
      </table>

      <p v-else class="endorsementTabEmpty">
        {{ t("common.none") }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import EndorsementFormModal from "./EndorsementFormModal.vue";

const { useLocalize } = pkp.modules.useLocalize;
const { useUrl } = pkp.modules.useUrl;
const { useFetch } = pkp.modules.useFetch;
const { useModal } = pkp.modules.useModal;

const { t } = useLocalize();
const { openDialog, openSideModal } = useModal();

const MAX_DISPLAY_LENGTH = 40;
function truncate(value) {
  if (!value) return "";
  const str = String(value);
  return str.length > MAX_DISPLAY_LENGTH
    ? str.substring(0, MAX_DISPLAY_LENGTH) + "…"
    : str;
}

const STATUS_NOT_CONFIRMED = 0;
const STATUS_CONFIRMED = 1;
const STATUS_DENIED = 2;
const STATUS_COMPLETED = 3;
const STATUS_COULDNT_COMPLETE = 4;

const props = defineProps({
  submission: {
    type: Object,
    required: true,
  },
});

const endorsements = ref([]);
const isLoading = ref(true);
const error = ref(null);

const { apiUrl } = useUrl(`endorsements/${props.submission.id}`);
const { data, fetch: fetchEndorsements } = useFetch(apiUrl);

async function loadEndorsements() {
  isLoading.value = true;
  error.value = null;
  try {
    await fetchEndorsements();
    endorsements.value = data.value?.items || [];
  } catch (e) {
    error.value = "Failed to load endorsements";
  } finally {
    isLoading.value = false;
  }
}

function getStatusLabel(status) {
  const labels = {
    [STATUS_NOT_CONFIRMED]: t("plugins.generic.plauditPreEndorsement.endorsementNotConfirmed"),
    [STATUS_CONFIRMED]: t("plugins.generic.plauditPreEndorsement.endorsementConfirmed"),
    [STATUS_DENIED]: t("plugins.generic.plauditPreEndorsement.endorsementDeclined"),
    [STATUS_COMPLETED]: t("plugins.generic.plauditPreEndorsement.endorsementCompleted"),
    [STATUS_COULDNT_COMPLETE]: t("plugins.generic.plauditPreEndorsement.endorsementCouldntComplete"),
  };
  return labels[status] || t("common.unknownError");
}

function getStatusClass(status) {
  const classes = {
    [STATUS_NOT_CONFIRMED]: "endorsementBadge endorsementBadgeNotConfirmed",
    [STATUS_CONFIRMED]: "endorsementBadge endorsementBadgeConfirmed",
    [STATUS_DENIED]: "endorsementBadge endorsementBadgeDenied",
    [STATUS_COMPLETED]: "endorsementBadge endorsementBadgeCompleted",
    [STATUS_COULDNT_COMPLETE]: "endorsementBadge endorsementBadgeCouldntComplete",
  };
  return classes[status] || "endorsementBadge";
}

function canSendManually(endorsement) {
  return (
    endorsement.status === STATUS_CONFIRMED ||
    endorsement.status === STATUS_COULDNT_COMPLETE
  );
}

function openAddModal() {
  openSideModal(EndorsementFormModal, {
    submissionId: props.submission.id,
    onSaved: () => loadEndorsements(),
  });
}

function openEditModal(endorsement) {
  openSideModal(EndorsementFormModal, {
    submissionId: props.submission.id,
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
          await deleteEndorsement(endorsement);
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

async function deleteEndorsement(endorsement) {
  const { apiUrl: deleteUrl } = useUrl(
    `endorsements/${props.submission.id}/${endorsement.id}`
  );
  const { fetch: fetchDelete } = useFetch(deleteUrl, { method: "DELETE" });
  await fetchDelete();
  loadEndorsements();
}

async function sendManually(endorsement) {
  const { apiUrl: sendUrl } = useUrl(
    `endorsements/${props.submission.id}/${endorsement.id}/send`
  );
  const { fetch: fetchSend } = useFetch(sendUrl, { method: "POST" });
  await fetchSend();
  loadEndorsements();
}

onMounted(() => {
  loadEndorsements();
});

watch(
  () => props.submission.id,
  () => loadEndorsements()
);
</script>

<style scoped>
.endorsementTab {
  padding: 1rem;
}

.endorsementTabHeader {
  margin-bottom: 1.5rem;
}

.endorsementTabHeader h2 {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
}

.endorsementTabHeader p {
  margin: 0;
  color: #666;
}

.endorsementTabLoading {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.endorsementTabError {
  color: #d00;
  padding: 1rem;
  background: #fee;
  border-radius: 4px;
}

.endorsementTabActions {
  margin-bottom: 1rem;
}

.endorsementTable {
  width: 100%;
  border-collapse: collapse;
}

.endorsementTable th,
.endorsementTable td {
  text-align: left;
  padding: 0.75rem;
  border-bottom: 1px solid #ddd;
}

.endorsementTable th {
  font-weight: 600;
  background: #f9f9f9;
}

.endorsementOrcid {
  display: block;
  font-size: 0.85em;
  color: #666;
}

.endorsementEmailCount {
  font-size: 0.85em;
  color: #999;
}

.endorsementActions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.endorsementBadge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 3px;
  font-size: 0.85em;
  font-weight: 500;
}

.endorsementBadgeNotConfirmed {
  background: #fff3cd;
  color: #856404;
}

.endorsementBadgeConfirmed {
  background: #d4edda;
  color: #155724;
}

.endorsementBadgeDenied {
  background: #f8d7da;
  color: #721c24;
}

.endorsementBadgeCompleted {
  background: #cce5ff;
  color: #004085;
}

.endorsementBadgeCouldntComplete {
  background: #f8d7da;
  color: #721c24;
}

.endorsementTabEmpty {
  color: #999;
  font-style: italic;
}
</style>
