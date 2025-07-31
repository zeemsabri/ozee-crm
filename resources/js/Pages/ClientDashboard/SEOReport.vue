<script setup>
import { ref, onMounted, nextTick, computed, watch, onUnmounted } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    initialAuthToken: {
        type: String,
        required: true,
    },
    projectId: {
        type: [String, Number],
        required: true,
    },
});

// Reactive state for report data
const reportData = ref(null);
const isLoading = ref(true); // For fetching the specific month's report
const apiError = ref(null); // For errors fetching specific month's report

// Month selection states
const availableMonths = ref([]);
const selectedMonth = ref('');
const isLoadingMonths = ref(true); // For fetching the list of available months
const errorMonths = ref(null); // For errors fetching available months
const hasNoReportsAtAll = ref(false); // True if no reports are found for the project

// Refs for canvas elements
const searchVisibilityCanvas = ref(null);
const trafficChannelCanvas = ref(null);
const deviceUsageCanvas = ref(null);
const countryClicksCanvas = ref(null);
const mobileCoreVitalsCanvas = ref(null);
const desktopCoreVitalsCanvas = ref(null);

// Function to fetch available months for SEO reports from API
const fetchAvailableMonths = async () => {
    isLoadingMonths.value = true;
    errorMonths.value = null;
    hasNoReportsAtAll.value = false;
    try {
        const response = await fetch(`/api/client-api/projects/${props.projectId}/seo-reports/available-months`, {
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMessage = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Failed to load available report months.');
            throw new Error(errorMessage);
        }

        availableMonths.value = data.map(month => ({
            value: month,
            label: new Date(month + '-01').toLocaleString('en-US', { year: 'numeric', month: 'long' })
        }));

        // Automatically select the most recent month if available
        if (availableMonths.value.length > 0) {
            selectedMonth.value = availableMonths.value[0].value;
        } else {
            hasNoReportsAtAll.value = true; // No reports found for this client
        }

    } catch (err) {
        console.error('Error fetching available months:', err);
        errorMonths.value = err.message || 'Failed to load available report months.';
        hasNoReportsAtAll.value = true; // Treat API error here as no reports found for now
    } finally {
        isLoadingMonths.value = false;
    }
};

// Fetch report data from API for the selected month
const fetchReportData = async () => {
    isLoading.value = true;
    apiError.value = null;
    reportData.value = null; // Clear previous data

    if (!selectedMonth.value) {
        isLoading.value = false;
        return;
    }

    try {
        const response = await fetch(`/api/client-api/projects/${props.projectId}/seo-reports/${selectedMonth.value}`, {
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (response.status === 404) {
            // Specific handling for 404 Not Found, meaning no report for this month
            reportData.value = null; // Ensure reportData is null
            isLoading.value = false;
            return;
        }

        const data = await response.json();

        if (!response.ok) {
            const errorMessage = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Failed to fetch SEO report.');
            throw new Error(errorMessage);
        }

        reportData.value = data;
        // Re-initialize charts after data is fetched and DOM is updated
        nextTick(() => {
            // Give a small delay to ensure canvas elements are fully rendered
            setTimeout(() => {
                initializeCharts();
            }, 50); // Small delay
        });

    } catch (err) {
        console.error("Error fetching SEO report:", err);
        apiError.value = err.message || 'An unexpected error occurred while fetching the SEO report.';
        reportData.value = null; // Ensure reportData is null on error
    } finally {
        isLoading.value = false;
    }
};

// Computed property for recommendations (now depends on fetched reportData)
const recommendations = computed(() => {
    if (!reportData.value) return [];
    const rData = reportData.value; // Shorthand for easier access
    return [
        {
            number: 1,
            heading: 'Improve Page Performance (Core Web Vitals)',
            description: `Prioritize technical improvements to boost mobile and desktop performance scores (currently ${rData.coreVitals?.mobile?.scores?.[0] || 0}/100 and ${rData.coreVitals?.desktop?.scores?.[0] || 0}/100). A faster site enhances user experience and is a direct Google ranking factor.`
        },
        {
            number: 2,
            heading: 'Optimize High-Impression, Low-CTR Queries',
            description: `Rewrite meta titles and descriptions for keywords like "${rData.zeroClickKeywords?.[0]?.query || 'N/A'}" (${rData.zeroClickKeywords?.[0]?.impressions || 'N/A'} impressions, ${rData.zeroClickKeywords?.[0]?.ctr || 'N/A'}% CTR) to be more compelling and improve click-through rates.`
        },
        {
            number: 3,
            heading: 'Conduct a Comprehensive Backlink Audit',
            description: `Investigate the reported ${rData.totalBacklinksValue || 'N/A'} backlinks to identify and disavow low-quality or spammy links. Focus on acquiring high-quality, relevant backlinks from authoritative sites in the digital marketing niche.`
        },
        {
            number: 4,
            heading: 'Optimize Conversion Paths for Organic Traffic',
            description: `While organic search drives good engagement, the low number of "Key events" suggests a need to optimize conversion funnels. Ensure clear calls to action and intuitive user journeys from organic landing pages to desired actions (e.g., lead form submissions).`
        }
    ];
});

// Chart colors (updated to CRM theme)
const chartColors = {
    main: '#4f46e5', // Indigo-600
    secondary: '#3b82f6', // Blue-600
    accent: '#6b7280', // Gray-500
    light: '#e5e7eb', // Gray-200
    ge90: '#22c55e', // Green-500
    ge50: '#f59e0b', // Yellow-500
    lt50: '#ef4444' // Red-500
};

// Global Chart.js options
const globalChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                font: {
                    family: 'Inter',
                    size: 12
                },
                color: chartColors.accent
            }
        },
        tooltip: {
            titleFont: { family: 'Inter' },
            bodyFont: { family: 'Inter' }
        }
    },
    scales: {
        x: {
            ticks: { font: { family: 'Inter' }, color: chartColors.accent },
            grid: { color: chartColors.light + '33' },
            title: { display: false }
        },
        y: {
            ticks: { font: { family: 'Inter' }, color: chartColors.accent },
            grid: { color: chartColors.light + '33' },
            title: { display: false }
        }
    }
};

// Function to get core web vital bar color
const getCoreVitalsColor = (score) => {
    if (score >= 90) return chartColors.ge90;
    if (score >= 50) return chartColors.ge50;
    return chartColors.lt50;
};

// Chart instances storage
let searchVisibilityChartInstance = null;
let trafficChannelChartInstance = null;
let deviceUsageChartInstance = null;
let countryClicksChartInstance = null;
let mobileCoreVitalsChartInstance = null;
let desktopCoreVitalsChartInstance = null;

// Function to destroy existing charts
const destroyCharts = () => {
    if (searchVisibilityChartInstance) searchVisibilityChartInstance.destroy();
    if (trafficChannelChartInstance) trafficChannelChartInstance.destroy();
    if (deviceUsageChartInstance) deviceUsageChartInstance.destroy();
    if (countryClicksChartInstance) countryClicksChartInstance.destroy();
    if (mobileCoreVitalsChartInstance) mobileCoreVitalsChartInstance.destroy();
    if (desktopCoreVitalsChartInstance) desktopCoreVitalsChartInstance.destroy();

    searchVisibilityChartInstance = null;
    trafficChannelChartInstance = null;
    deviceUsageChartInstance = null;
    countryClicksChartInstance = null;
    mobileCoreVitalsChartInstance = null;
    desktopCoreVitalsChartInstance = null;
};

// Function to initialize charts
const initializeCharts = () => {
    destroyCharts(); // Destroy existing charts before creating new ones

    if (!reportData.value) return; // Don't initialize if no data

    // Helper for safe data access
    const safeData = (obj, path, defaultValue) => {
        const parts = path.split('.');
        let current = obj;
        for (let i = 0; i < parts.length; i++) {
            if (current === null || typeof current !== 'object' || !current.hasOwnProperty(parts[i])) {
                return defaultValue;
            }
            current = current[parts[i]];
        }
        return current;
    };

    // Search Visibility Trend
    if (searchVisibilityCanvas.value) {
        searchVisibilityChartInstance = new Chart(searchVisibilityCanvas.value, {
            type: 'line',
            data: {
                labels: safeData(reportData.value, 'clicksImpressions.labels', []),
                datasets: [
                    {
                        label: 'Clicks',
                        data: safeData(reportData.value, 'clicksImpressions.clicks', []),
                        borderColor: chartColors.main,
                        backgroundColor: chartColors.main + '33',
                        fill: false,
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Impressions',
                        data: safeData(reportData.value, 'clicksImpressions.impressions', []),
                        borderColor: chartColors.accent,
                        backgroundColor: chartColors.accent + '33',
                        fill: true,
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                ...globalChartOptions,
                scales: {
                    ...globalChartOptions.scales,
                    y: {
                        ...globalChartOptions.scales.y,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Clicks',
                            font: { family: 'Inter' }
                        }
                    },
                    y1: {
                        ...globalChartOptions.scales.y,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: {
                            display: true,
                            text: 'Impressions',
                            font: { family: 'Inter' }
                        }
                    }
                }
            }
        });
    }


    if (trafficChannelCanvas.value) {
        // Traffic by Channel
        const trafficColors = safeData(reportData.value, 'trafficSources.colors', ['#4f46e5', '#3b82f6', '#6b7280', '#c8b496', '#22c55e', '#ef4444']);
        trafficChannelChartInstance = new Chart(trafficChannelCanvas.value, {
            type: 'doughnut',
            data: {
                labels: safeData(reportData.value, 'trafficSources.labels', []),
                datasets: [{
                    data: safeData(reportData.value, 'trafficSources.sessions', []),
                    backgroundColor: trafficColors.slice(0, safeData(reportData.value, 'trafficSources.labels', []).length),
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                ...globalChartOptions,
                plugins: {
                    ...globalChartOptions.plugins,
                    legend: {
                        ...globalChartOptions.plugins.legend,
                        position: 'right'
                    }
                }
            }
        });
    }


    if (deviceUsageCanvas.value) {
        // Device Usage
        const deviceColors = safeData(reportData.value, 'deviceUsage.colors', ['#4f46e5', '#3b82f6', '#6b7280', '#c8b496', '#22c55e', '#ef4444']);
        deviceUsageChartInstance = new Chart(deviceUsageCanvas.value, {
            type: 'doughnut',
            data: {
                labels: safeData(reportData.value, 'deviceUsage.labels', []),
                datasets: [{
                    data: safeData(reportData.value, 'deviceUsage.clicks', []),
                    backgroundColor: deviceColors.slice(0, safeData(reportData.value, 'deviceUsage.labels', []).length),
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                ...globalChartOptions,
                plugins: {
                    ...globalChartOptions.plugins,
                    legend: {
                        ...globalChartOptions.plugins.legend,
                        position: 'right'
                    }
                }
            }
        });
    }


    if (countryClicksCanvas.value) {
        // Top 10 Countries by Clicks
        countryClicksChartInstance = new Chart(countryClicksCanvas.value, {
            type: 'bar',
            data: {
                labels: safeData(reportData.value, 'countryPerformance.labels', []),
                datasets: [{
                    data: safeData(reportData.value, 'countryPerformance.clicks', []),
                    backgroundColor: chartColors.main,
                    borderRadius: 4
                }]
            },
            options: {
                ...globalChartOptions,
                indexAxis: 'y',
                plugins: {
                    ...globalChartOptions.plugins,
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ...globalChartOptions.scales.x,
                        beginAtZero: true
                    },
                    y: {
                        ...globalChartOptions.scales.y,
                        grid: { display: false }
                    }
                }
            }
        });
    }


    if (mobileCoreVitalsCanvas.value) {
        // Core Web Vitals - Mobile
        const mobileScores = safeData(reportData.value, 'coreVitals.mobile.scores', [0, 0, 0, 0]);
        mobileCoreVitalsChartInstance = new Chart(mobileCoreVitalsCanvas.value, {
            type: 'bar',
            data: {
                labels: safeData(reportData.value, 'coreVitals.mobile.labels', ["Performance", "Accessibility", "Best Practices", "SEO"]),
                datasets: [{
                    data: mobileScores,
                    backgroundColor: mobileScores.map(getCoreVitalsColor),
                    borderRadius: 4
                }]
            },
            options: {
                ...globalChartOptions,
                indexAxis: 'y',
                plugins: {
                    ...globalChartOptions.plugins,
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ...globalChartOptions.scales.x,
                        max: 100,
                        beginAtZero: true
                    },
                    y: {
                        ...globalChartOptions.scales.y,
                        grid: { display: false }
                    }
                }
            }
        });
    }


    if (desktopCoreVitalsCanvas.value) {
        // Core Web Vitals - Desktop
        const desktopScores = safeData(reportData.value, 'coreVitals.desktop.scores', [0, 0, 0, 0]);
        desktopCoreVitalsChartInstance = new Chart(desktopCoreVitalsCanvas.value, {
            type: 'bar',
            data: {
                labels: safeData(reportData.value, 'coreVitals.desktop.labels', ["Performance", "Accessibility", "Best Practices", "SEO"]),
                datasets: [{
                    data: desktopScores,
                    backgroundColor: desktopScores.map(getCoreVitalsColor),
                    borderRadius: 4
                }]
            },
            options: {
                ...globalChartOptions,
                indexAxis: 'y',
                plugins: {
                    ...globalChartOptions.plugins,
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ...globalChartOptions.scales.x,
                        max: 100,
                        beginAtZero: true
                    },
                    y: {
                        ...globalChartOptions.scales.y,
                        grid: { display: false }
                    }
                }
            }
        });
    }
};

// Table sorting logic
const sortState = ref({}); // { tableName: { column: 'clicks', direction: 'asc' } }

const sortTable = (tableName, column) => {
    if (!reportData.value || !reportData.value[tableName]) return;

    const currentSort = sortState.value[tableName];
    let direction = 'asc';

    if (currentSort && currentSort.column === column) {
        direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    }

    sortState.value[tableName] = { column, direction };

    // Perform the sort on a copy of the array
    const dataArray = reportData.value[tableName];

    const sortedData = [...dataArray].sort((a, b) => {
        const valA = a[column];
        const valB = b[column];

        if (typeof valA === 'string' && typeof valB === 'string') {
            return direction === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else {
            return direction === 'asc' ? valA - valB : valB - valA;
        }
    });
    reportData.value[tableName] = sortedData; // Update the reactive data
};

// Smooth scrolling
const smoothScroll = (event, targetId) => {
    event.preventDefault();
    const targetElement = document.getElementById(targetId);
    if (targetElement) {
        targetElement.scrollIntoView({ behavior: 'smooth' });
    }
};

onMounted(async () => {
    await fetchAvailableMonths();
    // Only fetch report data if months are available and a month is selected
    if (availableMonths.value.length > 0 && selectedMonth.value) {
        fetchReportData();
    }
});

// Watch for changes in selectedMonth to refetch data
watch(selectedMonth, (newMonth, oldMonth) => {
    if (newMonth !== oldMonth && newMonth) {
        fetchReportData();
    } else if (!newMonth) {
        // If selectedMonth becomes empty (e.g., if all options are removed or cleared)
        reportData.value = null;
        destroyCharts();
        isLoading.value = false;
        apiError.value = null;
    }
}, { flush: 'post' }); // 'post' ensures the DOM is updated before watch effect runs

// Cleanup charts on component unmount
onUnmounted(() => {
    destroyCharts();
});
</script>

<template>
    <div class="font-inter antialiased bg-gray-100 text-gray-800 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="bg-white rounded-xl shadow-lg p-4 mb-6 flex flex-col sm:flex-row items-center justify-between">
            <div class="flex items-center mb-4 sm:mb-0">
                <h1 class="font-bold text-2xl sm:text-3xl text-gray-900 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-line-chart mr-3 text-indigo-600"><path d="M3 3v18h18"/><path d="m18 6-6 6-4-4-3 3"/></svg>
                    SEO Performance Report
                </h1>
            </div>
            <div class="flex items-center space-x-4">
                <label for="month-select" class="text-gray-700 font-medium">Select Month:</label>
                <select id="month-select" v-model="selectedMonth"
                        class="block w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                        :disabled="isLoadingMonths || errorMonths || hasNoReportsAtAll">
                    <option v-if="availableMonths.length === 0" value="" disabled>No reports available</option>
                    <option v-for="month in availableMonths" :key="month.value" :value="month.value">
                        {{ month.label }}
                    </option>
                </select>
            </div>
        </header>

        <!-- Loading, Error, or No Data States -->
        <div v-if="isLoadingMonths" class="text-center text-gray-600 py-12 bg-white rounded-xl shadow-md">
            <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <p>Loading available report months...</p>
        </div>

        <div v-else-if="errorMonths" class="text-center text-red-600 py-12 bg-red-50 rounded-xl shadow-md border border-red-200">
            <p class="font-semibold mb-2 text-xl">Error loading available months:</p>
            <p class="text-lg">{{ errorMonths }}</p>
            <p class="mt-4 text-sm">Please try again or contact support if the issue persists.</p>
        </div>

        <div v-else-if="hasNoReportsAtAll" class="text-center text-gray-700 py-12 bg-white rounded-xl shadow-md border border-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-x mx-auto mb-4 text-gray-400">
                <path d="M10 20H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.88l.82 1.12h6.59a2 2 0 0 1 2 2v1.5" />
                <path d="M17 22l5-5" />
                <path d="m17 17 5 5" />
            </svg>
            <p class="text-xl font-semibold mb-3">No SEO Reports Found for Your Project.</p>
            <p class="text-base leading-relaxed max-w-lg mx-auto">
                It looks like there are no SEO performance reports available for this project yet.
                If you are expecting reports, please ensure your SEO services are active.
            </p>
            <p class="text-base mt-2 mb-5 leading-relaxed max-w-lg mx-auto">
                Interested in boosting your online visibility? Learn more about our comprehensive SEO services!
            </p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4 mt-6">
                <a href="mailto:info@ozeeweb.com.au" class="bg-indigo-600 text-white py-2 px-6 rounded-lg font-semibold hover:bg-indigo-700 transition-colors duration-200 shadow-md flex items-center justify-center w-full sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                    </svg>
                    info@ozeeweb.com.au
                </a>
                <a href="tel:+61456639389" class="bg-gray-200 text-gray-800 py-2 px-6 rounded-lg font-semibold hover:bg-gray-300 transition-colors duration-200 shadow-md flex items-center justify-center w-full sm:w-auto">
                    +61 456 639 389
                </a>
            </div>
        </div>

        <div v-else-if="isLoading" class="text-center text-gray-600 py-12 bg-white rounded-xl shadow-md">
            <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <p>Loading SEO report for {{ selectedMonth ? availableMonths.find(m => m.value === selectedMonth)?.label : 'selected month' }}...</p>
        </div>

        <div v-else-if="apiError" class="text-center text-red-600 py-12 bg-red-50 rounded-xl shadow-md border border-red-200">
            <p class="font-semibold mb-2 text-xl">Error loading report for {{ selectedMonth ? availableMonths.find(m => m.value === selectedMonth)?.label : 'selected month' }}:</p>
            <p class="text-lg">{{ apiError }}</p>
            <p class="mt-4 text-sm">Please try again or contact support if the issue persists.</p>
        </div>

        <div v-else-if="!reportData && availableMonths.length > 0" class="text-center text-gray-700 py-12 bg-white rounded-xl shadow-md border border-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-x mx-auto mb-4 text-gray-400"><path d="M8 2v4"/><path d="M16 2v4"/><path d="M21 13V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M3 10h18"/><path d="m17 22 5-5"/><path d="m17 17 5 5"/></svg>
            <p class="text-xl font-semibold mb-3">No Report Available for {{ selectedMonth ? availableMonths.find(m => m.value === selectedMonth)?.label : 'this month' }}.</p>
            <p class="text-base leading-relaxed max-w-lg mx-auto">
                While SEO services are active for this client, the report for
                <span class="font-semibold">{{ selectedMonth ? availableMonths.find(m => m.value === selectedMonth)?.label : 'this month' }}</span>
                is not yet available or has not been generated.
            </p>
            <p class="text-base mt-2 leading-relaxed max-w-lg mx-auto">
                Please check back later or select a different month from the dropdown.
            </p>
        </div>

        <!-- Main Content (rendered only if reportData is successfully loaded) -->
        <main v-else class="max-w-7xl mx-auto py-8">

            <!-- Navigation Links -->
            <nav class="mb-8 hidden md:block">
                <ul class="flex justify-center space-x-6 bg-white p-3 rounded-xl shadow-md border border-gray-200">
                    <li><a href="#overview" @click="smoothScroll($event, 'overview')"
                           class="text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">Overview</a>
                    </li>
                    <li><a href="#audience" @click="smoothScroll($event, 'audience')"
                           class="text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">Audience</a>
                    </li>
                    <li><a href="#performance" @click="smoothScroll($event, 'performance')"
                           class="text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">Performance</a>
                    </li>
                    <li><a href="#seo-foundation" @click="smoothScroll($event, 'seo-foundation')"
                           class="text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">SEO
                        Foundation</a></li>
                    <li><a href="#opportunities" @click="smoothScroll($event, 'opportunities')"
                           class="text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">Opportunities</a>
                    </li>
                </ul>
            </nav>

            <!-- Section: Performance Overview -->
            <section id="overview" class="mb-12 py-8 bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-gauge mr-3 text-indigo-600"><path d="m12 14 4-4"/><path d="M3.34 19A8 8 0 1 1 21 12h-4"/><path d="M7.17 12.96 10.61 9.5"/></svg>
                    Performance Overview
                </h2>
                <p class="text-gray-700 mb-8 leading-relaxed">This section provides a high-level summary of <span class="font-semibold">{{ reportData.clientName || 'your' }}</span>
                    website's SEO performance over the last <span class="font-semibold">{{ reportData.reportingPeriod || 'period' }}</span>, combining key metrics from Google
                    Search Console and SEMrush. It offers a quick snapshot of overall visibility, authority, and user engagement.
                </p>

                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
                    <div class="bg-indigo-50 p-6 rounded-xl shadow-sm border border-indigo-200 flex flex-col items-start">
                        <div class="flex items-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-award text-indigo-600 mr-2"><circle cx="12" cy="8" r="6"/><path d="M15.47 12.23 17 22l-5-3-5 3 1.53-9.77"/></svg>
                            <p class="text-sm text-gray-700 font-medium">Authority Score (SEMrush)</p>
                        </div>
                        <p class="text-3xl font-bold text-indigo-700">{{ reportData.authorityScoreValue }}</p>
                    </div>
                    <div class="bg-blue-50 p-6 rounded-xl shadow-sm border border-blue-200 flex flex-col items-start">
                        <div class="flex items-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mouse-pointer-click text-blue-600 mr-2"><path d="m9 9 5 5"/><path d="M13.5 13.5 19 19"/><path d="M11 7H7a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4"/><path d="M17 11V7a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h4"/></svg>
                            <p class="text-sm text-gray-700 font-medium">Total Clicks (GSC)</p>
                        </div>
                        <p class="text-3xl font-bold text-blue-700">{{ reportData.totalClicksValue }}</p>
                    </div>
                    <div class="bg-green-50 p-6 rounded-xl shadow-sm border border-green-200 flex flex-col items-start">
                        <div class="flex items-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye text-green-600 mr-2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <p class="text-sm text-gray-700 font-medium">Total Impressions (GSC)</p>
                        </div>
                        <p class="text-3xl font-bold text-green-700">{{ reportData.totalImpressionsValue }}</p>
                    </div>
                    <div class="bg-yellow-50 p-6 rounded-xl shadow-sm border border-yellow-200 flex flex-col items-start">
                        <div class="flex items-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up text-yellow-700 mr-2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                            <p class="text-sm text-gray-700 font-medium">Average Position (GSC)</p>
                        </div>
                        <p class="text-3xl font-bold text-yellow-700">{{ reportData.averagePositionValue }}</p>
                    </div>
                </div>

                <!-- Chart: Search Visibility Trend -->
                <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                    <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity mr-2 text-gray-600"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        Search Visibility Trend
                    </h3>
                    <p class="text-gray-600 mb-4 leading-relaxed">This chart shows the daily clicks and impressions from Google Search Console,
                        illustrating how search visibility fluctuated throughout the reporting period. Hover over the points to see
                        data for a specific day.</p>
                    <div class="chart-container relative w-full max-w-4xl mx-auto h-96 md:h-[400px]">
                        <canvas ref="searchVisibilityCanvas"></canvas>
                    </div>
                </div>
            </section>

            <!-- Section: Audience Insights -->
            <section id="audience" class="mb-12 py-8 bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-round mr-3 text-indigo-600"><path d="M18 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Audience Insights
                </h2>
                <p class="text-gray-700 mb-8 leading-relaxed">Understanding who your visitors are and how they find you is crucial. This section
                    breaks down the website's traffic by its source, the devices users are on, and their geographic location.</p>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Chart: Traffic by Channel -->
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pie-chart mr-2 text-gray-600"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                            Traffic by Channel (GA4 Sessions)
                        </h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">This chart shows the distribution of user sessions across different primary
                            channels, indicating how users are primarily arriving at the website.</p>
                        <div class="chart-container relative w-full max-w-2xl mx-auto h-80 max-h-[35vh] md:h-96">
                            <canvas ref="trafficChannelCanvas"></canvas>
                        </div>
                    </div>

                    <!-- Chart: Device Usage -->
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-smartphone mr-2 text-gray-600"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                            Device Usage (GSC Clicks)
                        </h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">This breakdown shows which devices visitors use to access the site, based on
                            clicks from Google Search Console. A significant mobile user base means a seamless mobile experience is
                            critical.</p>
                        <div class="chart-container relative w-full max-w-2xl mx-auto h-80 max-h-[35vh] md:h-96">
                            <canvas ref="deviceUsageCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Chart: Top 10 Countries by Clicks -->
                <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200 mt-8">
                    <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin mr-2 text-gray-600"><path d="M12 12.7a1 1 0 1 1 0-1.4c.5-.4 1.2-.7 2-.7 1.7 0 3 1.3 3 3s-1.3 3-3 3c-.8 0-1.5-.3-2-.7l-4.5 4.5a1 1 0 0 1-1.4-1.4L10.6 14.3c-.4-.5-.7-1.2-.7-2z"/><path d="M12 22s8-4 8-10c0-4.4-3.6-8-8-8s-8 3.6-8 8c0 6 8 10 8 10z"/></svg>
                        Top 10 Countries by Clicks (GSC)
                    </h3>
                    <p class="text-gray-600 mb-4 leading-relaxed">This chart highlights the primary geographic locations from which users are
                        clicking on search results leading to the website.</p>
                    <div class="chart-container relative w-full max-w-4xl mx-auto h-96 md:h-[400px]">
                        <canvas ref="countryClicksCanvas"></canvas>
                    </div>
                </div>
            </section>

            <!-- Section: Performance Deep Dive -->
            <section id="performance" class="mb-12 py-8 bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart-2 mr-3 text-indigo-600"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
                    Performance Deep Dive
                </h2>
                <p class="text-gray-700 mb-8 leading-relaxed">Let's look closer at what's working and areas for improvement. This section covers
                    the top search queries driving traffic and the technical performance of the site, which impacts user
                    experience and rankings.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Chart: Core Web Vitals - Mobile -->
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-smartphone mr-2 text-gray-600"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                            Mobile Performance (Core Web Vitals)
                        </h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">These scores measure the website's loading speed, interactivity, and visual
                            stability. Google uses these as a key ranking factor. The lower performance scores (especially mobile) are
                            critical areas for improvement.</p>
                        <div class="chart-container relative w-full max-w-2xl mx-auto h-64 max-h-[25vh]">
                            <canvas ref="mobileCoreVitalsCanvas"></canvas>
                        </div>
                    </div>

                    <!-- Chart: Core Web Vitals - Desktop -->
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-monitor mr-2 text-gray-600"><rect width="20" height="14" x="2" y="3" rx="2"/><path d="M12 17v4"/><path d="M8 21h8"/></svg>
                            Desktop Performance (Core Web Vitals)
                        </h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">These scores measure the website's loading speed, interactivity, and visual
                            stability. Google uses these as a key ranking factor. The lower performance scores (especially mobile) are
                            critical areas for improvement.</p>
                        <div class="chart-container relative w-full max-w-2xl mx-auto h-64 max-h-[25vh]">
                            <canvas ref="desktopCoreVitalsCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Table: Top Search Queries -->
                <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                    <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search mr-2 text-gray-600"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        Top Search Queries (GSC)
                    </h3>
                    <p class="text-gray-600 mb-4 leading-relaxed">These are the top search terms that are currently bringing visitors to the
                        website, along with their clicks, impressions, CTR, and average position. Click column headers to sort.</p>
                    <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 table-sortable">
                            <thead class="bg-gray-100">
                            <tr>
                                <th @click="sortTable('topQueries', 'query')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider cursor-pointer hover:bg-gray-200 rounded-tl-lg">
                                    Query
                                    <span v-if="sortState.topQueries?.column === 'query'"
                                          class="ml-1">{{ sortState.topQueries.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th @click="sortTable('topQueries', 'clicks')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider cursor-pointer hover:bg-gray-200">
                                    Clicks
                                    <span v-if="sortState.topQueries?.column === 'clicks'"
                                          class="ml-1">{{ sortState.topQueries.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th @click="sortTable('topQueries', 'impressions')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider cursor-pointer hover:bg-gray-200">
                                    Impressions
                                    <span v-if="sortState.topQueries?.column === 'impressions'"
                                          class="ml-1">{{ sortState.topQueries.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th @click="sortTable('topQueries', 'ctr')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider cursor-pointer hover:bg-gray-200">
                                    CTR
                                    <span v-if="sortState.topQueries?.column === 'ctr'"
                                          class="ml-1">{{ sortState.topQueries.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th @click="sortTable('topQueries', 'position')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider cursor-pointer hover:bg-gray-200 rounded-tr-lg">
                                    Position
                                    <span v-if="sortState.topQueries?.column === 'position'"
                                          class="ml-1">{{ sortState.topQueries.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(item, index) in reportData.topQueries" :key="index">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.query }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.clicks }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.impressions }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.ctr }}%</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.position }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Section: SEO Foundation -->
            <section id="seo-foundation" class="mb-12 py-8 bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building mr-3 text-indigo-600"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9.5 16h5"/><path d="M9.5 12h5"/><path d="M9.5 8h5"/></svg>
                    SEO Foundation
                </h2>
                <p class="text-gray-700 mb-8 leading-relaxed">This section highlights the foundational elements of your SEO strategy: the
                    keywords your website is ranking for and the backlinks (links from other websites) that contribute to your
                    site's authority.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Table: Top Keyword Rankings -->
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key mr-2 text-gray-600"><path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.778-7.778z"/><path d="m15.5 5.5 3.5 3.5L14 14 9 9l5-5Z"/></svg>
                            Top Keyword Rankings (SEMrush)
                        </h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">These are the specific keywords for which your website is currently appearing
                            in Google's search results, along with their top ranking position. Higher rankings mean more visibility
                            and potential clicks, especially for local terms.</p>
                        <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider rounded-tl-lg">Keyword
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider rounded-tr-lg">Ranking
                                    </th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(item, index) in reportData.keywordRankings" :key="index">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.keyword }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.ranking }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Table: Backlinks -->
                    <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link mr-2 text-gray-600"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07L9.4 6.6A2 2 0 0 1 8.07 8z"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.41-1.41A2 2 0 0 1 15.93 16z"/></svg>
                            Backlinks (SEMrush Sample)
                        </h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">Backlinks are links from other websites to yours. They act as 'votes of
                            confidence' and are crucial for building your website's authority and improving its search engine ranking.
                            Here are a few examples of recent backlinks.</p>
                        <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider rounded-tl-lg">Backlink
                                        URL
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider rounded-tr-lg">Target Site
                                    </th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(item, index) in reportData.backlinks" :key="index">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a :href="item.url" target="_blank" class="text-blue-600 hover:underline">{{ item.url }}</a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.target }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="bg-amber-100 text-amber-800 p-4 rounded-xl mt-4 text-sm border border-amber-200">
                            <p class="font-semibold mb-2">Important Note:</p>
                            <p>The total backlink number reported by SEMrush ({{ reportData.totalBacklinksValue }}) is unusually
                                high relative to the domain's authority score. Many of these links are likely from low-quality sources
                                like user profiles, which offer minimal SEO value. A comprehensive backlink audit is recommended to
                                assess quality.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section: Opportunities & Recommendations -->
            <section id="opportunities" class="py-8 bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lightbulb mr-3 text-indigo-600"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 6c0 1.8.7 3.3 1.5 4.5 1 .8 1.7 2.5 1.5 3.5"/><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 14v8"/></svg>
                    Opportunities & Recommendations
                </h2>
                <p class="text-gray-700 mb-8 leading-relaxed">Based on the data, here are the biggest opportunities for growth. Focusing on
                    these areas will provide the most impact on improving organic visibility and driving conversions.</p>

                <!-- Table: High-Impression, Low-CTR Queries -->
                <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                    <h3 class="text-xl font-semibold text-gray-900 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down-circle mr-2 text-gray-600"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="m12 16 4-4-4-4"/></svg>
                        High-Impression, Low-CTR Queries (Opportunity)
                    </h3>
                    <p class="text-gray-600 mb-4 leading-relaxed">These are valuable keywords for which the website is appearing in search
                        results but getting a low click-through rate. Optimizing content and search snippets for these terms can
                        significantly increase relevant traffic. Click column headers to sort.</p>
                    <div class="overflow-x-auto rounded-lg shadow-sm border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 table-sortable">
                            <thead class="bg-gray-100">
                            <tr>
                                <th @click="sortTable('zeroClickKeywords', 'query')"
                                    class="px-6 py-3 text-left text-xs font-medium bg-yellow-100 text-yellow-800 uppercase tracking-wider cursor-pointer hover:bg-yellow-200 rounded-tl-lg">
                                    Query
                                    <span v-if="sortState.zeroClickKeywords?.column === 'query'"
                                          class="ml-1">{{ sortState.zeroClickKeywords.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th @click="sortTable('zeroClickKeywords', 'impressions')"
                                    class="px-6 py-3 text-left text-xs font-medium bg-yellow-100 text-yellow-800 uppercase tracking-wider cursor-pointer hover:bg-yellow-200">
                                    Impressions
                                    <span v-if="sortState.zeroClickKeywords?.column === 'impressions'"
                                          class="ml-1">{{ sortState.zeroClickKeywords.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th @click="sortTable('zeroClickKeywords', 'ctr')"
                                    class="px-6 py-3 text-left text-xs font-medium bg-yellow-100 text-yellow-800 uppercase tracking-wider cursor-pointer hover:bg-yellow-200">
                                    CTR
                                    <span v-if="sortState.zeroClickKeywords?.column === 'ctr'"
                                          class="ml-1">{{ sortState.zeroClickKeywords.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                                <th @click="sortTable('zeroClickKeywords', 'position')"
                                    class="px-6 py-3 text-left text-xs font-medium bg-yellow-100 text-yellow-800 uppercase tracking-wider cursor-pointer hover:bg-yellow-200 rounded-tr-lg">
                                    Position
                                    <span v-if="sortState.zeroClickKeywords?.column === 'position'"
                                          class="ml-1">{{ sortState.zeroClickKeywords.direction === 'asc' ? '▲' : '▼' }}</span>
                                </th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(item, index) in reportData.zeroClickKeywords" :key="index">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.query }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.impressions }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.ctr }}%</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.position }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- List: Key Recommendations -->
                <div class="bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-ordered mr-2 text-gray-600"><line x1="10" x2="17" y1="6" y2="6"/><line x1="10" x2="17" y1="12" y2="12"/><line x1="10" x2="17" y1="18" y2="18"/><path d="M2 6h.01"/><path d="M2 12h.01"/><path d="M2 18h.01"/></svg>
                        Key Recommendations
                    </h3>
                    <ol class="space-y-6">
                        <li v-for="rec in recommendations" :key="rec.number" class="flex items-start">
                            <span class="text-indigo-600 font-bold text-xl mr-3 flex-shrink-0">{{ rec.number }}.</span>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 mb-1">{{ rec.heading }}</h4>
                                <p class="text-gray-700 leading-relaxed">{{ rec.description }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
/* Custom CSS for chart containers */
.chart-container {
    position: relative;
    width: 100%;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
    height: 350px;
    /* Base height */
    max-height: 40vh;
}

@media (min-width: 768px) {
    .chart-container {
        height: 400px;
    }
}

/* Specific chart container height adjustments */
#trafficChannelChart+.chart-container,
#deviceUsageChart+.chart-container {
    height: 80vh;
    max-height: 35vh;
}

#mobileCoreVitalsChart+.chart-container,
#desktopCoreVitalsChart+.chart-container {
    height: 64vh;
    max-height: 25vh;
}

/* Table sorting header styles */
.table-sortable th {
    cursor: pointer;
    transition: background-color 0.1s ease-in-out;
}

.table-sortable th:hover {
    background-color: #e5e7eb;
    /* gray-200 */
}

/* Specific yellow header for opportunities table */
#opportunities .table-sortable th {
    background-color: #fef3c7;
    /* yellow-100 */
    color: #b45309;
    /* amber-800 */
}

#opportunities .table-sortable th:hover {
    background-color: #fde68a;
    /* yellow-200 */
}
</style>
