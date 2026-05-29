<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'create',
    },
    flow: {
        type: Object,
        default: null,
    },
    projects: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
    approvableTypes: {
        type: Array,
        default: () => [],
    },
});

const isEditMode = computed(() => props.mode === 'edit');

const initialSteps = (() => {
    if (!props.flow?.steps?.length) {
        return [
            {
                approver_type: 'role',
                approver_role_id: '',
                approver_user_id: '',
                label: '',
            },
        ];
    }

    return [...props.flow.steps]
        .sort((a, b) => a.step_order - b.step_order)
        .map((step) => ({
            approver_type: step.approver_type || 'role',
            approver_role_id: step.approver_role_id ?? '',
            approver_user_id: step.approver_user_id ?? '',
            label: step.label ?? '',
        }));
})();

const form = useForm({
    name: props.flow?.name || '',
    approvable_type: props.flow?.approvable_type || props.approvableTypes?.[0]?.value || '',
    project_id: props.flow?.project_id ?? '',
    is_active: props.flow?.is_active ?? true,
    is_default: props.flow?.is_default ?? false,
    resync_pending: false,
    steps: initialSteps,
});

const stepError = (index, field) => {
    return form.errors[`steps.${index}.${field}`] || null;
};

const addStep = () => {
    form.steps.push({
        approver_type: 'role',
        approver_role_id: '',
        approver_user_id: '',
        label: '',
    });
};

const removeStep = (index) => {
    if (form.steps.length === 1) {
        return;
    }

    form.steps.splice(index, 1);
};

const moveStepUp = (index) => {
    if (index === 0) {
        return;
    }

    const temp = form.steps[index - 1];
    form.steps[index - 1] = form.steps[index];
    form.steps[index] = temp;
};

const moveStepDown = (index) => {
    if (index >= form.steps.length - 1) {
        return;
    }

    const temp = form.steps[index + 1];
    form.steps[index + 1] = form.steps[index];
    form.steps[index] = temp;
};

const onApproverTypeChange = (step) => {
    if (step.approver_type === 'role') {
        step.approver_user_id = '';
        return;
    }

    step.approver_role_id = '';
};

const submit = () => {
    if (isEditMode.value) {
        form.put(route('admin.approval-flows.update', props.flow.id));
        return;
    }

    form.post(route('admin.approval-flows.store'));
};
</script>

<template>
    <Head :title="isEditMode ? 'Edit Approval Flow' : 'Create Approval Flow'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ isEditMode ? 'Edit Approval Flow' : 'Create Approval Flow' }}
                </h2>
                <Link :href="route('admin.approval-flows.index')">
                    <SecondaryButton>Back to Flows</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form class="p-6 md:p-8 space-y-8" @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="name" value="Flow Name" />
                                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="approvable_type" value="Approvable Type" />
                                <select
                                    id="approvable_type"
                                    v-model="form.approvable_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="" disabled>Select model type</option>
                                    <option v-for="item in approvableTypes" :key="item.value" :value="item.value">
                                        {{ item.label }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.approvable_type" />
                            </div>

                            <div>
                                <InputLabel for="project_id" value="Project (Optional)" />
                                <select
                                    id="project_id"
                                    v-model="form.project_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Global (all projects)</option>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.project_id" />
                            </div>

                            <div class="flex flex-col gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-6">
                                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span>Flow is active</span>
                                </label>

                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span>Set as default for this type/project</span>
                                </label>

                                <label v-if="isEditMode" class="inline-flex items-center gap-2 text-sm text-amber-700 mt-1">
                                    <input v-model="form.resync_pending" type="checkbox" class="rounded border-gray-300 text-amber-600 shadow-sm focus:ring-amber-500" />
                                    <span>Re-sync pending bills that haven't started approval yet</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t pt-8">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Approval Steps</h3>
                                    <p class="text-sm text-gray-500">Configure approvers in execution order.</p>
                                </div>
                                <SecondaryButton type="button" @click="addStep">Add Step</SecondaryButton>
                            </div>

                            <InputError class="mb-3" :message="form.errors.steps" />

                            <div class="space-y-4">
                                <div
                                    v-for="(step, index) in form.steps"
                                    :key="index"
                                    class="rounded-lg border border-gray-200 p-4"
                                >
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="font-medium text-gray-900">Step {{ index + 1 }}</h4>
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded border px-2 py-1 text-xs text-gray-700 disabled:opacity-50"
                                                :disabled="index === 0"
                                                @click="moveStepUp(index)"
                                            >
                                                Move Up
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded border px-2 py-1 text-xs text-gray-700 disabled:opacity-50"
                                                :disabled="index === form.steps.length - 1"
                                                @click="moveStepDown(index)"
                                            >
                                                Move Down
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 disabled:opacity-50"
                                                :disabled="form.steps.length === 1"
                                                @click="removeStep(index)"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <InputLabel :for="`approver_type_${index}`" value="Approver Type" />
                                            <select
                                                :id="`approver_type_${index}`"
                                                v-model="step.approver_type"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                @change="onApproverTypeChange(step)"
                                            >
                                                <option value="role">Role</option>
                                                <option value="user">User</option>
                                            </select>
                                            <InputError class="mt-2" :message="stepError(index, 'approver_type')" />
                                        </div>

                                        <div v-if="step.approver_type === 'role'">
                                            <InputLabel :for="`approver_role_${index}`" value="Approver Role" />
                                            <select
                                                :id="`approver_role_${index}`"
                                                v-model="step.approver_role_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">Select role</option>
                                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                                    {{ role.name }}
                                                </option>
                                            </select>
                                            <InputError class="mt-2" :message="stepError(index, 'approver_role_id')" />
                                        </div>

                                        <div v-else>
                                            <InputLabel :for="`approver_user_${index}`" value="Approver User" />
                                            <select
                                                :id="`approver_user_${index}`"
                                                v-model="step.approver_user_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">Select user</option>
                                                <option v-for="user in users" :key="user.id" :value="user.id">
                                                    {{ user.name }}
                                                </option>
                                            </select>
                                            <InputError class="mt-2" :message="stepError(index, 'approver_user_id')" />
                                        </div>

                                        <div>
                                            <InputLabel :for="`label_${index}`" value="Step Label (Optional)" />
                                            <TextInput
                                                :id="`label_${index}`"
                                                v-model="step.label"
                                                class="mt-1 block w-full"
                                                placeholder="Example: Finance approval"
                                            />
                                            <InputError class="mt-2" :message="stepError(index, 'label')" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Link :href="route('admin.approval-flows.index')">
                                <SecondaryButton type="button">Cancel</SecondaryButton>
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ isEditMode ? 'Update Flow' : 'Create Flow' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
