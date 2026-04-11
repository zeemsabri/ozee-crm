import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const sharedStatus = ref({
    is_online: false,
    extension_mandatory: false,
    can_bypass: false,
    last_activity: null,
    last_status_change: null,
    reported_extension_version: null,
    required_extension_version: null,
    extension_version_last_seen_at: null,
    extension_version_checked_at: null,
    extension_version_missing: false,
    extension_version_outdated: false,
    extension_reminder_reason: null,
});
const sharedLoading = ref(false);
const sharedError = ref(null);
let sharedIntervalId = null;
let subscribers = 0;

export function useExtensionStatus(options = {}) {
    const page = usePage();
    const pollMs = options.pollMs ?? 300000;

    const reminderReason = computed(() => {
        if (!page.props.auth?.user) {
            return null;
        }

        if (sharedStatus.value.extension_reminder_reason) {
            return sharedStatus.value.extension_reminder_reason;
        }

        if (sharedStatus.value.extension_mandatory && !sharedStatus.value.is_online) {
            return 'offline';
        }

        return null;
    });

    const shouldShowReminder = computed(() => {
        return !!reminderReason.value;
    });

    const fetchStatus = async (options = {}) => {
        if (!page.props.auth?.user) {
            return null;
        }

        sharedLoading.value = true;
        sharedError.value = null;

        try {
            const response = await window.axios.get('/api/me/status', {
                params: options.force ? { force: 1 } : {},
            });
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
        reminderReason,
        refreshStatus: fetchStatus,
        startPolling,
    };
}