<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

// --- Props from Laravel/Inertia ---
const props = defineProps({
    magicToken: String,
    project: Object,
    clientEmail: String,
});

// --- App State ---
const currentPage = ref('dashboard'); // Can be 'dashboard' or 'document'
const selectedDocument = ref(null);
const documents = ref([]); // This will hold the documents for review
const isLoading = ref(false);
const message = ref('');

// --- Data Fetching ---
// This function will fetch documents for the client to review.
// It needs a corresponding API endpoint on your Laravel backend.
const fetchDocuments = async () => {
    isLoading.value = true;
    message.value = '';
    try {
        // IMPORTANT: You will need to create this API route in routes/api.php
        // It should validate the token and return the documents for that project.
        const response = await axios.get(`/api/client-portal/${props.magicToken}/documents`);
        documents.value = response.data;
    } catch (error) {
        console.error("Error fetching documents:", error);
        message.value = 'Error: Could not load documents. Please try again later.';
        // For now, let's add some placeholder data for UI development
        documents.value = [
            { id: 1, title: 'Q3 Blog Post Draft', status: 'Pending Review' },
            { id: 2, title: 'New Website Mockup', status: 'Approved' },
        ];
    } finally {
        isLoading.value = false;
    }
};

// Fetch documents when the component is mounted
onMounted(() => {
    // We call fetchDocuments() here.
    fetchDocuments();
    console.log('Client Dashboard mounted with props:', props);
});


// --- Navigation ---
const handleSelectDocument = (doc) => {
    selectedDocument.value = doc;
    currentPage.value = 'document';
};

const handleBackToDashboard = () => {
    selectedDocument.value = null;
    currentPage.value = 'dashboard';
    message.value = ''; // Clear any messages
};

</script>

<template>
    <div class="min-h-screen bg-gray-100 flex flex-col items-center p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-5xl">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 v-if="project" class="text-4xl font-extrabold text-indigo-700">
                    {{ project.name }}
                </h1>
                <p class="text-lg text-gray-600">Client Portal</p>
                <p v-if="clientEmail" class="text-sm text-gray-500 mt-2">
                    Viewing as: <span class="font-medium">{{ clientEmail }}</span>
                </p>
            </div>

            <!-- Message Area -->
            <div v-if="message" :class="['p-3 mb-4 rounded-md text-center w-full', message.startsWith('Error') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700']">
                {{ message }}
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="text-center text-gray-700 p-10">
                <p class="animate-pulse">Loading documents...</p>
            </div>

            <!-- Conditional Rendering of Views -->
            <div v-else>
                <!-- Dashboard View (List of documents) -->
                <div v-if="currentPage === 'dashboard'">
                    <div class="bg-white rounded-lg shadow-xl p-6 sm:p-8">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">Documents for Your Review</h2>

                        <div v-if="documents.length > 0" class="space-y-4">
                            <!-- Loop through documents -->
                            <div v-for="doc in documents" :key="doc.id" class="flex items-center justify-between p-4 rounded-md bg-gray-50 border hover:bg-gray-100 transition-colors">
                                <div>
                                    <h3 class="text-lg font-semibold text-indigo-600">{{ doc.title }}</h3>
                                    <p class="text-sm">Status:
                                        <span :class="{'text-orange-500': doc.status === 'Pending Review', 'text-green-600': doc.status === 'Approved'}">{{ doc.status }}</span>
                                    </p>
                                </div>
                                <button @click="handleSelectDocument(doc)" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                                    Review & Comment
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-gray-500 text-center py-8">
                            <p>There are currently no documents for you to review.</p>
                        </div>
                    </div>
                </div>

                <!-- Document Detail View -->
                <div v-if="currentPage === 'document' && selectedDocument">
                    <div class="bg-white rounded-lg shadow-xl p-6 sm:p-8">
                        <button @click="handleBackToDashboard" class="mb-6 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-indigo-700 bg-indigo-100 hover:bg-indigo-200">
                            &larr; Back to Dashboard
                        </button>
                        <h2 class="text-3xl font-bold text-gray-800 mb-4">{{ selectedDocument.title }}</h2>
                        <!-- Placeholder for document embed and comments -->
                        <div class="bg-gray-200 h-96 rounded-md flex items-center justify-center border">
                            <p class="text-gray-500">[ Document embed would go here ]</p>
                        </div>
                        <div class="mt-8">
                            <h3 class="text-xl font-semibold">Comments</h3>
                            <!-- Comments section placeholder -->
                            <div class="mt-4 text-gray-500 p-4 bg-gray-50 rounded-md border">[ Comments section would go here ]</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
