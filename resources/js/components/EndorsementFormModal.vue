<template>
  <PkpSideModalBody>
    <template #title>
      {{
        endorsementId
          ? t("plugins.generic.plauditPreEndorsement.editEndorsement")
          : t("plugins.generic.plauditPreEndorsement.addEndorsement")
      }}
    </template>
    <PkpSideModalLayoutBasic>
      <div class="endorsementFormModal">
        <div class="pkpFormField pkpFormField--text">
          <div class="pkpFormField__heading">
            <label class="pkpFormFieldLabel" for="endorserName">
              {{ t("plugins.generic.plauditPreEndorsement.endorserName") }}
              <span class="pkpFormFieldLabel__required">*</span>
            </label>
          </div>
          <div class="pkpFormField__control">
            <input
              id="endorserName"
              v-model="name"
              type="text"
              class="pkpFormField__input pkpFormField--text__input"
              :aria-invalid="!!errors.name"
              required
            />
          </div>
          <div v-if="errors.name" class="pkpFormFieldError">
            {{ errors.name[0] }}
          </div>
        </div>

        <div class="pkpFormField pkpFormField--text">
          <div class="pkpFormField__heading">
            <label class="pkpFormFieldLabel" for="endorserEmail">
              {{ t("plugins.generic.plauditPreEndorsement.emailColumnName") }}
              <span class="pkpFormFieldLabel__required">*</span>
            </label>
          </div>
          <div class="pkpFormField__control">
            <input
              id="endorserEmail"
              v-model="email"
              type="email"
              class="pkpFormField__input pkpFormField--text__input"
              :aria-invalid="!!errors.email"
              required
            />
          </div>
          <div v-if="errors.email" class="pkpFormFieldError">
            {{ errors.email[0] }}
          </div>
        </div>

        <div class="endorsementFormActions">
          <pkp-button :is-primary="true" :disabled="isSaving" @click="submitForm">
            {{ t("common.save") }}
          </pkp-button>
          <pkp-button @click="closeModal">
            {{ t("common.cancel") }}
          </pkp-button>
        </div>
      </div>
    </PkpSideModalLayoutBasic>
  </PkpSideModalBody>
</template>

<script setup>
import { ref, inject } from "vue";

const { useLocalize } = pkp.modules.useLocalize;
const { useUrl } = pkp.modules.useUrl;
const { useFetch } = pkp.modules.useFetch;

const { t } = useLocalize();
const closeModal = inject("closeModal");

const props = defineProps({
  submissionId: {
    type: Number,
    required: true,
  },
  endorsementId: {
    type: Number,
    default: null,
  },
  initialName: {
    type: String,
    default: "",
  },
  initialEmail: {
    type: String,
    default: "",
  },
  onSaved: {
    type: Function,
    default: () => {},
  },
});

const name = ref(props.initialName);
const email = ref(props.initialEmail);
const errors = ref({});
const isSaving = ref(false);

async function submitForm() {
  errors.value = {};
  isSaving.value = true;

  const isEdit = !!props.endorsementId;
  const urlPath = isEdit
    ? `endorsements/${props.submissionId}/${props.endorsementId}`
    : `endorsements/${props.submissionId}`;

  const { apiUrl } = useUrl(urlPath);
  const {
    isSuccess,
    validationError,
    fetch: fetchSave,
  } = useFetch(apiUrl, {
    method: isEdit ? "PUT" : "POST",
    body: { name: name.value, email: email.value },
    expectValidationError: true,
  });

  await fetchSave();

  if (validationError.value?.errors) {
    errors.value = validationError.value.errors;
    isSaving.value = false;
    return;
  }

  if (isSuccess.value) {
    props.onSaved();
    closeModal();
  }
  isSaving.value = false;
}
</script>

<style scoped>
.endorsementFormModal {
  padding: 1rem;
}

.endorsementFormModal .pkpFormField {
  margin-bottom: 1.5rem;
}

.pkpFormFieldError {
  color: var(--pkpColor-error, #d00);
  font-size: 0.85rem;
  margin-top: 0.25rem;
}

.endorsementFormActions {
  display: flex;
  gap: 0.5rem;
  margin-top: 2rem;
}
</style>
