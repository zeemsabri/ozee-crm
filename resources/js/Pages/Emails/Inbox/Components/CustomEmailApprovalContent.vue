<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import EmailEditor from '@/Components/EmailEditor.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
  email: { type: Object, required: true },
});

const emit = defineEmits(['submitted', 'error']);

const loading = ref(false);
const isSubmitting = ref(false);
const form = ref({ subject: '', body: '' });

const fetchEmailDetails = async () => {
  if (!props.email?.id) return;
  loading.value = true;
  try {
    // Use preview endpoint that returns rendered subject/body_html/body
    const { data } = await axios.get(`/api/emails/${props.email.id}`);
    form.value.subject = data.subject;
    form.value.body = data.body_html || data.body;
  } catch (e) {
    console.error('Failed to load email content', e);
    emit('error', e);
  } finally {
    loading.value = false;
  }
};

const approveEmail = async () => {
  isSubmitting.value = true;
  try {
    await axios.post(`/api/emails/${props.email.id}/edit-and-approve`, {
      subject: form.value.subject,
      body: form.value.body,
      composition_type: 'custom',
    });
    emit('submitted');
  } catch (e) {
    console.error('Approve custom email failed', e);
    emit('error', e);
  } finally {
    isSubmitting.value = false;
  }
};

const rejectEmail = async () => {
  isSubmitting.value = true;
  try {
    await axios.post(`/api/emails/${props.email.id}/reject`, { rejection_reason: 'Rejected by approver.' });
    emit('submitted');
  } catch (e) {
    console.error('Reject custom email failed', e);
    emit('error', e);
  } finally {
    isSubmitting.value = false;
  }
};

watch(() => props.email, (nv) => { if (nv) fetchEmailDetails(); }, { immediate: true });
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
        <InputError :message="''" class="mt-2" />
      </div>
      <div class="mb-6">
        <InputLabel for="body" value="Email Body" class="sr-only" />
        <EmailEditor id="body" v-model="form.body" placeholder="Edit the email here..." height="300px" />
        <InputError :message="''" class="mt-2" />
      </div>
      <div class="flex items-center justify-end space-x-2">
        <PrimaryButton @click="approveEmail" :class="{ 'opacity-25': isSubmitting }" :disabled="isSubmitting">
          Approve & Send
        </PrimaryButton>
        <SecondaryButton @click="rejectEmail" :class="{ 'opacity-25': isSubmitting }" :disabled="isSubmitting">
          Reject
        </SecondaryButton>
      </div>
    </form>
  </div>
</template>
