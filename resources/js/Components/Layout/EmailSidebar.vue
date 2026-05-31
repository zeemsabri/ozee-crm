<script setup>
import { computed } from 'vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import EmailDetailsContent from '@/Pages/Emails/Inbox/Components/EmailDetailsContent.vue';
import { emailSidebarState, closeEmailDetailSidebar } from '@/Utils/email-sidebar';

const sidebarTitle = computed(() => {
    return emailSidebarState.email?.subject || 'Email Details';
});

const handleDeleted = () => {
    closeEmailDetailSidebar();
};
</script>

<template>
    <RightSidebar
        :show="emailSidebarState.show"
        :title="sidebarTitle"
        @update:show="closeEmailDetailSidebar"
        @close="closeEmailDetailSidebar"
    >
        <template #content>
            <EmailDetailsContent
                v-if="emailSidebarState.email"
                :email="emailSidebarState.email"
                :can-approve-emails="false"
                @deleted="handleDeleted"
            />
        </template>
    </RightSidebar>
</template>