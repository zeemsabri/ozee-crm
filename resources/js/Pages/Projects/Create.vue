<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProjectCreateBasicInfo from '@/Components/ProjectForm/ProjectCreateBasicInfo.vue';
import {useGlobalPermissions, usePermissions} from '@/Directives/permissions';
import { computed } from 'vue';
const { canDo, canView } = usePermissions();

const { permissions: globalPermissions, loading: globalPermissionsLoading } = useGlobalPermissions();
const canCreateProject = canDo('create_projects');

// Note: Router-level permission middleware now handles access control
// No need for client-side redirect

const props = defineProps({
    statusOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) }, // Inertia errors
});

// Handle successful project creation (optional, as ProjectCreateBasicInfo handles redirect)
const handleProjectCreated = (projectId) => {
    console.log(`New project created with ID: ${projectId}`);
    // The ProjectCreateBasicInfo component already handles navigation to the edit page.
    // This function can be used for any additional logic on the Create page itself.
};

// Handle closing the form (e.g., navigate back to projects index)
const handleCloseForm = () => {
    router.visit(route('projects.index'));
};
</script>

<template>
    <Head title="Create New Project" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create New Project
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <ProjectCreateBasicInfo
                        :statusOptions="statusOptions"
                        :sourceOptions="sourceOptions"
                        :errors="props.errors"
                        :canManageProjects="canCreateProject"
                        @projectCreated="handleProjectCreated"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
