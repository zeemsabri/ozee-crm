<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProjectForm from '@/Components/ProjectForm.vue';
import { useGlobalPermissions, usePermissions } from '@/Directives/permissions';

// Fetch global permissions (used by ProjectForm internally for permission checks)
const { permissions: globalPermissions, loading: globalPermissionsLoading } = useGlobalPermissions();
const { canDo } = usePermissions();
const canCreateProject = canDo('create_projects');

// Note: Router-level permission middleware now handles access control
// No need for client-side redirect

// Define props passed from the Inertia controller
const props = defineProps({
    // project prop now only needs to contain the ID for the initial fetch
    project: {
        type: Object, // Should contain { id: number, name: string }
        required: true,
    },
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    clientRoleOptions: { type: Array, default: () => [] },
    userRoleOptions: { type: Array, default: () => [] },
    paymentTypeOptions: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) }, // Inertia errors
});

// Reactive state for the project name for the header (fetched from initial prop)
const projectName = ref(props.project?.name || 'Loading...');

// Watch for changes in the incoming 'project' prop to update the project name in the header
watch(() => props.project, (newProject) => {
    if (newProject && newProject.name) {
        projectName.value = newProject.name;
    }
}, { immediate: true }); // Immediate ensures it runs on initial load

// Handle closing the form (e.g., navigate back to projects index)
const handleCloseForm = () => {
    router.visit(route('projects.index')); // Assuming you have a projects.index route
};

// No need for isLoadingPage or currentProjectData here, as ProjectForm and its children
// will handle their own loading states for their respective data.
</script>

<template>
    <Head :title="`Edit Project: ${projectName}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Project: <span class="text-indigo-600">{{ projectName }}</span>
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div >
                    <!-- ProjectForm now receives just the projectId and other options -->
                    <ProjectForm
                        :projectId="project.id"
                        :statusOptions="statusOptions"
                        :departmentOptions="departmentOptions"
                        :sourceOptions="sourceOptions"
                        :clientRoleOptions="clientRoleOptions"
                        :userRoleOptions="userRoleOptions"
                        :paymentTypeOptions="paymentTypeOptions"
                        :errors="props.errors"
                        @close="handleCloseForm"
                        :isSaving="false"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
