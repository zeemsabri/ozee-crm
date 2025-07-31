<script setup>
import { ref, onMounted, watch, computed } from 'vue'; // Added 'computed'
import SelectDropdown from '@/Components/SelectDropdown.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue'; // Import PrimaryButton

const props = defineProps({
    projectId: {
        type: [String, Number],
        required: true,
    },
    canCreateSeoReports: { // New prop for permission to create reports
        type: Boolean,
        default: false,
    },
});

const emits = defineEmits(['openCreateSeoReportModal']); // Emit to parent to open modal

const availableMonths = ref([]);
const selectedMonth = ref('');
const seoReportData = ref(null);
const isLoadingMonths = ref(true);
const isLoadingReport = ref(false);
const errorMonths = ref(null);
const errorReport = ref(null);
const structuralDataError = ref(null); // New state for structural validation errors

// Function to fetch available months for SEO reports
const fetchAvailableMonths = async () => {
    isLoadingMonths.value = true;
    errorMonths.value = null;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/seo-reports/available-months`);
        availableMonths.value = response.data.map(month => ({
            value: month,
            label: new Date(month + '-01').toLocaleString('en-US', { year: 'numeric', month: 'long' })
        }));

        // Automatically select the most recent month if available
        if (availableMonths.value.length > 0) {
            selectedMonth.value = availableMonths.value[0].value;
        }
    } catch (err) {
        console.error('Error fetching available months:', err);
        errorMonths.value = 'Failed to load available report months.';
    } finally {
        isLoadingMonths.value = false;
    }
};

// Function to validate the structure of the fetched SEO report data
const validateSeoReportStructure = (data) => {
    const requiredTopLevelKeys = [
        "clientName", "reportingPeriod", "authorityScoreValue",
        "totalClicksValue", "totalImpressionsValue", "averagePositionValue",
        "totalBacklinksValue", "clicksImpressions", "trafficSources",
        "deviceUsage", "countryPerformance", "coreVitals", "topQueries",
        "keywordRankings", "backlinks", "zeroClickKeywords"
    ];

    const missingKeys = requiredTopLevelKeys.filter(key => !(key in data));

    if (missingKeys.length > 0) {
        return `Report data is missing expected top-level keys: ${missingKeys.join(', ')}. Please ensure the JSON structure is correct.`;
    }

    // Add more specific checks if needed, e.g., for nested objects or array types
    if (!data.clicksImpressions || !Array.isArray(data.clicksImpressions.labels) || !Array.isArray(data.clicksImpressions.clicks)) {
        return 'clicksImpressions data is malformed.';
    }
    if (!data.trafficSources || !Array.isArray(data.trafficSources.labels) || !Array.isArray(data.trafficSources.sessions)) {
        return 'trafficSources data is malformed.';
    }
    // ... add more checks as necessary for other complex objects

    return null; // No errors
};


// Function to fetch a specific SEO report for the selected month
const fetchSeoReport = async () => {
    if (!selectedMonth.value) {
        seoReportData.value = null;
        structuralDataError.value = null; // Clear previous errors
        return;
    }

    isLoadingReport.value = true;
    errorReport.value = null;
    structuralDataError.value = null; // Clear previous errors
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/seo-reports/${selectedMonth.value}`);
        const data = response.data;

        const validationError = validateSeoReportStructure(data);
        if (validationError) {
            structuralDataError.value = validationError;
            seoReportData.value = null; // Don't display malformed data
        } else {
            seoReportData.value = data;
        }
    } catch (err) {
        console.error('Error fetching SEO report:', err);
        errorReport.value = 'Failed to load SEO report for the selected month.';
        seoReportData.value = null;
        structuralDataError.value = null;
    } finally {
        isLoadingReport.value = false;
    }
};

// Handler for opening the create/edit modal
const openCreateEditModal = (reportToEdit = null) => {
    emits('openCreateSeoReportModal', reportToEdit);
};

// Handler for when a report is saved (created or updated)
const handleReportSaved = () => {
    fetchAvailableMonths();
    if (selectedMonth.value) {
        fetchSeoReport();
    }
};

// Computed properties for displaying data
const clientName = computed(() => seoReportData.value?.clientName || 'N/A');
const reportingPeriod = computed(() => seoReportData.value?.reportingPeriod || 'N/A');
const authorityScore = computed(() => seoReportData.value?.authorityScoreValue || 'N/A');
const totalClicks = computed(() => seoReportData.value?.totalClicksValue || 'N/A');
const totalImpressions = computed(() => seoReportData.value?.totalImpressionsValue || 'N/A');
const averagePosition = computed(() => seoReportData.value?.averagePositionValue || 'N/A');
const totalBacklinks = computed(() => seoReportData.value?.totalBacklinksValue || 'N/A');

const clicksImpressionsData = computed(() => seoReportData.value?.clicksImpressions || { labels: [], clicks: [], impressions: [] });
const trafficSourcesData = computed(() => seoReportData.value?.trafficSources || { labels: [], sessions: [], colors: [] });
const deviceUsageData = computed(() => seoReportData.value?.deviceUsage || { labels: [], clicks: [], colors: [] });
const countryPerformanceData = computed(() => seoReportData.value?.countryPerformance || { labels: [], clicks: [] });
const coreVitalsData = computed(() => seoReportData.value?.coreVitals || { mobile: {}, desktop: {} });
const topQueriesData = computed(() => seoReportData.value?.topQueries || []);
const keywordRankingsData = computed(() => seoReportData.value?.keywordRankings || []);


// Fetch available months when component mounts or projectId changes
onMounted(() => {
    fetchAvailableMonths();
});

watch(() => props.projectId, () => {
    fetchAvailableMonths();
});

// Watch selectedMonth to fetch the specific report
watch(selectedMonth, (newMonth) => {
    if (newMonth) {
        fetchSeoReport();
    } else {
        seoReportData.value = null;
        structuralDataError.value = null;
    }
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md min-h-[calc(100vh-6rem)]">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-900">SEO Reports</h4>
            <PrimaryButton
                v-if="canCreateSeoReports"
                @click="openCreateEditModal()"
                class="bg-indigo-600 hover:bg-indigo-700 transition-colors"
            >
                Add New Report
            </PrimaryButton>
        </div>

        <!-- Month Selection Filter -->
        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
            <div class="flex items-center gap-4">
                <div class="flex-1 min-w-[200px]">
                    <InputLabel for="seo-report-month-filter" value="Select Report Month" />
                    <SelectDropdown
                        id="seo-report-month-filter"
                        v-model="selectedMonth"
                        :options="availableMonths"
                        value-key="value"
                        label-key="label"
                        placeholder="Select a month"
                        :disabled="isLoadingMonths || availableMonths.length === 0"
                        class="mt-1"
                    />
                </div>
            </div>
            <div v-if="errorMonths" class="text-red-600 text-sm mt-2">{{ errorMonths }}</div>
            <div v-if="isLoadingMonths" class="text-gray-600 text-sm mt-2 animate-pulse">Loading available months...</div>
            <div v-if="!isLoadingMonths && availableMonths.length === 0" class="text-gray-500 text-sm mt-2">No SEO reports available for this project.</div>
        </div>

        <!-- SEO Report Display Area -->
        <div class="mt-6">
            <div class="flex justify-between items-center mb-3">
                <h5 class="text-md font-medium text-gray-800">
                    <span v-if="selectedMonth">Report for {{ new Date(selectedMonth + '-01').toLocaleString('en-US', { year: 'numeric', month: 'long' }) }}</span>
                    <span v-else>Select a month to view report</span>
                </h5>
                <PrimaryButton
                    v-if="canCreateSeoReports && seoReportData && selectedMonth"
                    @click="openCreateEditModal({ report_date: selectedMonth, data: JSON.stringify(seoReportData, null, 2) })"
                    class="bg-blue-500 hover:bg-blue-600 transition-colors text-sm py-1 px-3"
                >
                    Edit Report
                </PrimaryButton>
            </div>

            <div v-if="isLoadingReport" class="text-center text-gray-600 text-sm animate-pulse py-8">
                Loading SEO report data...
            </div>

            <div v-else-if="errorReport" class="text-center py-8">
                <p class="text-red-600 text-sm font-medium">{{ errorReport }}</p>
            </div>

            <div v-else-if="structuralDataError" class="text-center py-8">
                <p class="text-red-600 text-sm font-medium">Data Structure Error: {{ structuralDataError }}</p>
                <p class="text-gray-500 text-sm mt-2">Please ensure the JSON data conforms to the expected format.</p>
            </div>

            <div v-else-if="seoReportData" class="space-y-8">
                <!-- Overview Metrics -->
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="flex flex-col items-center p-3 bg-white rounded-md shadow-xs">
                        <p class="text-sm text-gray-500">Client Name</p>
                        <p class="text-lg font-semibold text-gray-900">{{ clientName }}</p>
                    </div>
                    <div class="flex flex-col items-center p-3 bg-white rounded-md shadow-xs">
                        <p class="text-sm text-gray-500">Reporting Period</p>
                        <p class="text-lg font-semibold text-gray-900">{{ reportingPeriod }}</p>
                    </div>
                    <div class="flex flex-col items-center p-3 bg-white rounded-md shadow-xs">
                        <p class="text-sm text-gray-500">Authority Score</p>
                        <p class="text-lg font-semibold text-gray-900">{{ authorityScore }}</p>
                    </div>
                    <div class="flex flex-col items-center p-3 bg-white rounded-md shadow-xs">
                        <p class="text-sm text-gray-500">Total Clicks</p>
                        <p class="text-lg font-semibold text-gray-900">{{ totalClicks }}</p>
                    </div>
                    <div class="flex flex-col items-center p-3 bg-white rounded-md shadow-xs">
                        <p class="text-sm text-gray-500">Total Impressions</p>
                        <p class="text-lg font-semibold text-gray-900">{{ totalImpressions }}</p>
                    </div>
                    <div class="flex flex-col items-center p-3 bg-white rounded-md shadow-xs">
                        <p class="text-sm text-gray-500">Average Position</p>
                        <p class="text-lg font-semibold text-gray-900">{{ averagePosition }}</p>
                    </div>
                    <div class="flex flex-col items-center p-3 bg-white rounded-md shadow-xs">
                        <p class="text-sm text-gray-500">Total Backlinks</p>
                        <p class="text-lg font-semibold text-gray-900">{{ totalBacklinks }}</p>
                    </div>
                </div>

                <!-- Clicks & Impressions Trend (Table for simplicity) -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Clicks & Impressions Trend</h5>
                    <div class="overflow-x-auto max-h-60">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Impressions</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(label, index) in clicksImpressionsData.labels" :key="index">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ label }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ clicksImpressionsData.clicks[index] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ clicksImpressionsData.impressions[index] }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!clicksImpressionsData.labels.length" class="text-gray-500 text-sm py-2">No clicks and impressions data available.</p>
                </div>

                <!-- Top Queries -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Top Queries</h5>
                    <div class="overflow-x-auto max-h-60">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Query</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Impressions</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CTR</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="query in topQueriesData" :key="query.query">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ query.query }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ query.clicks }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ query.impressions }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ query.ctr }}%</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ query.position }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!topQueriesData.length" class="text-gray-500 text-sm py-2">No top queries data available.</p>
                </div>

                <!-- Keyword Rankings -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Keyword Rankings</h5>
                    <div class="overflow-x-auto max-h-60">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keyword</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ranking</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="keyword in keywordRankingsData" :key="keyword.keyword">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ keyword.keyword }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ keyword.ranking }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!keywordRankingsData.length" class="text-gray-500 text-sm py-2">No keyword rankings data available.</p>
                </div>

                <!-- Traffic Sources -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Traffic Sources</h5>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(label, index) in trafficSourcesData.labels" :key="label">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ label }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ trafficSourcesData.sessions[index] }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!trafficSourcesData.labels.length" class="text-gray-500 text-sm py-2">No traffic sources data available.</p>
                </div>

                <!-- Device Usage -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Device Usage</h5>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(label, index) in deviceUsageData.labels" :key="label">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ label }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ deviceUsageData.clicks[index] }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!deviceUsageData.labels.length" class="text-gray-500 text-sm py-2">No device usage data available.</p>
                </div>

                <!-- Country Performance -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Country Performance (Top 10)</h5>
                    <div class="overflow-x-auto max-h-60">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="country in countryPerformanceData.labels" :key="country">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ country }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ countryPerformanceData.clicks[countryPerformanceData.labels.indexOf(country)] }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!countryPerformanceData.labels.length" class="text-gray-500 text-sm py-2">No country performance data available.</p>
                </div>

                <!-- Core Vitals -->
                <div class="bg-white p-6 rounded-lg shadow-sm grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Core Vitals (Mobile)</h5>
                        <div v-if="coreVitalsData.mobile && coreVitalsData.mobile.labels && coreVitalsData.mobile.labels.length" class="space-y-2">
                            <div v-for="(label, index) in coreVitalsData.mobile.labels" :key="label" class="flex justify-between items-center text-sm text-gray-700">
                                <span>{{ label }}:</span>
                                <span class="font-medium">{{ coreVitalsData.mobile.scores[index] }}</span>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 text-sm py-2">No mobile core vitals data available.</p>
                    </div>
                    <div>
                        <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Core Vitals (Desktop)</h5>
                        <div v-if="coreVitalsData.desktop && coreVitalsData.desktop.labels && coreVitalsData.desktop.labels.length" class="space-y-2">
                            <div v-for="(label, index) in coreVitalsData.desktop.labels" :key="label" class="flex justify-between items-center text-sm text-gray-700">
                                <span>{{ label }}:</span>
                                <span class="font-medium">{{ coreVitalsData.desktop.scores[index] }}</span>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 text-sm py-2">No desktop core vitals data available.</p>
                    </div>
                </div>

            </div>

            <div v-else class="text-center py-8 text-gray-500">
                No report data to display. Please select a month from the dropdown.
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
