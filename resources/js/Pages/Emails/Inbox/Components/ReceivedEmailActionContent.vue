<script setup>
import { reactive, computed, ref, watch } from 'vue';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: Object, required: true },
});

const emit = defineEmits(['submitted', 'error']);

const form = useForm({
    subject: '',
    body: '',
});

const loading = ref(false);
const isSubmitting = ref(false);

const fetchEmailDetails = async () => {
    if (!props.email?.id) return;
    loading.value = true;
    try {
        const response = await axios.get(`/api/emails/${props.email.id}`);
        form.subject = response.data.subject;
        form.body = response.data.body_html || response.data.body;
    } catch (error) {
        console.error('Failed to fetch email details:', error);
        emit('error', error);
    } finally {
        loading.value = false;
    }
};

const approveEmail = async () => {
    isSubmitting.value = true;
    try {
        const payload = {
            subject: form.subject,
            body: form.body,
        };
        await axios.post(`/api/emails/${props.email.id}/edit-and-approve`, payload);
        emit('submitted');
    } catch (error) {
        console.error('Error approving email:', error);
        emit('error', error);
    } finally {
        isSubmitting.value = false;
    }
};

const rejectEmail = async () => {
    isSubmitting.value = true;
    try {
        await axios.post(`/api/emails/${props.email.id}/reject`, { rejection_reason: 'Rejected by user.' });
        emit('submitted');
    } catch (error) {
        console.error('Error rejecting email:', error);
        emit('error', error);
    } finally {
        isSubmitting.value = false;
    }
};

watch(() => props.email, (newEmail) => {
    if (newEmail) {
        fetchEmailDetails();
    }
}, { immediate: true });
</script>

<template>
    <div v-if="loading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
    </div>
    <div v-else class="p-4 space-y-6">
        <form @submit.prevent>
            <div class="mb-4">
                <InputLabel for="subject" value="Subject" />
                <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="form.subject" required />
                <InputError :message="form.errors.subject" class="mt-2" />
            </div>
            <div class="mb-6">
                <InputLabel for="body" value="Email Body" class="sr-only" />
                <EmailEditor id="body" v-model="form.body" placeholder="Edit the email here..." height="300px" />
                <InputError :message="form.errors.body" class="mt-2" />
            </div>
            <div class="flex items-center justify-end space-x-2">
                <PrimaryButton @click="approveEmail" :class="{ 'opacity-25': isSubmitting }" :disabled="isSubmitting">
                    Approve
                </PrimaryButton>
                <SecondaryButton @click="rejectEmail" :class="{ 'opacity-25': isSubmitting }" :disabled="isSubmitting">
                    Reject
                </SecondaryButton>
            </div>
        </form>
    </div>
</template>
