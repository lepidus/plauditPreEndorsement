import { ref } from "vue";

const { useUrl } = pkp.modules.useUrl;
const { useFetch } = pkp.modules.useFetch;

/**
 * Shared loader for the plugin's endorsements API. Keeps fetch state +
 * `endorsements` list in one place so Vue components don't re-implement it.
 */
export function useEndorsements(submissionId) {
  const endorsements = ref([]);
  const isLoading = ref(true);
  const errorMessage = ref(null);

  const { apiUrl } = useUrl(`endorsements/${submissionId}`);
  const { data, isSuccess, fetch: fetchList } = useFetch(apiUrl);

  async function reload() {
    isLoading.value = true;
    errorMessage.value = null;
    await fetchList();
    if (isSuccess.value) {
      endorsements.value = data.value?.items || [];
    } else {
      errorMessage.value = "Failed to load endorsements";
    }
    isLoading.value = false;
  }

  return { endorsements, isLoading, errorMessage, reload };
}
