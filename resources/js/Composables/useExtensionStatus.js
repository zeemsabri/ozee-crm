import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const sharedStatus = ref({
    is_online: false,
    extension_mandatory: false,
    can_bypass: false,
    last_activity: null,
    last_status_change: null,
});
const sharedLoading = ref(false);
const sharedError = ref(null);
let sharedIntervalId = null;
let subscribers = 0;

export function useExtensionStatus(options = {}) {
    const page = usePage();
    const pollMs = options.pollMs ?? 300000;

    const shouldShowReminder = computed(() => {
        return !!page.props.auth?.user && sharedStatus.value.extension_mandatory && !sharedStatus.value.is_online;
    });

    const fetchStatus = async () => {
        if (!page.props.auth?.user) {
            return null;
        }

        sharedLoading.value = true;
        sharedError.value = null;

        try {
            const response = await window.axios.get('/api/me/status');
            sharedStatus.value = response.data;
            return response.data;
        } catch (error) {
            sharedError.value = error;
            return null;
        } finally {
            sharedLoading.value = false;
        }
    };

    const startPolling = () => {
        if (sharedIntervalId || pollMs <= 0) {
            return;
        }

        sharedIntervalId = window.setInterval(() => {
            fetchStatus();
        }, pollMs);
    };

    const stopPolling = () => {
        if (!sharedIntervalId || subscribers > 0) {
            return;
        }

        window.clearInterval(sharedIntervalId);
        sharedIntervalId = null;
    };

    onMounted(async () => {
        subscribers += 1;
        await fetchStatus();
        startPolling();
    });

    onBeforeUnmount(() => {
        subscribers = Math.max(subscribers - 1, 0);
        stopPolling();
    });

    return {
        status: sharedStatus,
        loading: sharedLoading,
        error: sharedError,
        shouldShowReminder,
        refreshStatus: fetchStatus,
        startPolling,
    };
}