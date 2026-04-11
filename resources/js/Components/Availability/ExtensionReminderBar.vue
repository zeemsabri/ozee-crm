<script setup>
import { computed, ref } from 'vue';
import { useExtensionStatus } from '@/Composables/useExtensionStatus';

const dismissedUntil = ref(Number(localStorage.getItem('extension_reminder_bar_dismissed_until') || 0));
const { status, loading, shouldShowReminder, reminderReason, refreshStatus } = useExtensionStatus();

const formatTimestamp = (value) => {
    if (!value) {
        return null;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(parsed);
};

const isDismissed = computed(() => dismissedUntil.value > Date.now());
const isVisible = computed(() => shouldShowReminder.value && !isDismissed.value);
const lastSeenLabel = computed(() => {
    return formatTimestamp(
        status.value.extension_version_last_seen_at || status.value.last_status_change || status.value.last_activity || null
    );
});
const actionLabel = computed(() => reminderReason.value === 'outdated_version' ? 'Update Extension' : 'Open Extension');
const lastSeenPrefix = computed(() => {
    if (reminderReason.value === 'outdated_version' || reminderReason.value === 'missing_version') {
        return 'Last version report:';
    }

    return 'Last reported activity:';
});
const reminderTitle = computed(() => {
    if (reminderReason.value === 'outdated_version') {
        return 'Your extension is out of date.';
    }

    if (reminderReason.value === 'missing_version') {
        return 'Extension version could not be verified.';
    }

    return 'Extension is required but you are currently offline.';
});
const reminderMessage = computed(() => {
    if (reminderReason.value === 'outdated_version') {
        const requiredVersion = status.value.required_extension_version;
        const reportedVersion = status.value.reported_extension_version || 'unknown';

        return `Update the extension to version ${requiredVersion} or newer. Current reported version: ${reportedVersion}.`;
    }

    if (reminderReason.value === 'missing_version') {
        const requiredVersion = status.value.required_extension_version;

        return requiredVersion
            ? `Open the extension so CRM can verify that you are on version ${requiredVersion} or newer.`
            : 'Open the extension so CRM can verify your installed version.';
    }

    return 'Turn on the extension so your work time is recorded correctly.';
});

const dismissReminder = () => {
    dismissedUntil.value = Date.now() + (60 * 60 * 1000);
    localStorage.setItem('extension_reminder_bar_dismissed_until', dismissedUntil.value.toString());
};
</script>

<template>
    <div v-if="isVisible" class="border-b border-amber-200 bg-[linear-gradient(90deg,#fff6db_0%,#ffefd0_50%,#fff7e5_100%)]">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold">{{ reminderTitle }}</p>
                    <p class="mt-1 text-amber-900/80">
                        {{ reminderMessage }}
                        <span v-if="lastSeenLabel" class="font-medium">{{ lastSeenPrefix }} {{ lastSeenLabel }}</span>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                <a
                    :href="$page.props.chrome_extension_link || '#'"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center rounded-full bg-amber-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-amber-700"
                >
                    {{ actionLabel }}
                </a>
                <button
                    type="button"
                    @click="refreshStatus({ force: true })"
                    :disabled="loading"
                    class="inline-flex items-center rounded-full border border-amber-300 bg-white px-4 py-2 text-xs font-semibold text-amber-900 transition hover:border-amber-400 hover:bg-amber-50 disabled:opacity-60"
                >
                    {{ loading ? 'Checking...' : 'Refresh status' }}
                </button>
                <button
                    type="button"
                    @click="dismissReminder"
                    class="inline-flex items-center rounded-full px-3 py-2 text-xs font-medium text-amber-900/70 transition hover:bg-white/70 hover:text-amber-950"
                >
                    Remind me later
                </button>
            </div>
        </div>
    </div>
</template>