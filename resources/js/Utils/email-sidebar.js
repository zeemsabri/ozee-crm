import { reactive } from 'vue';

const emailSidebarState = reactive({
    show: false,
    email: null,
});

const openEmailDetailSidebar = (emailId, emailData = {}) => {
    const parsedId = Number(emailId);
    if (!parsedId || Number.isNaN(parsedId)) {
        return;
    }

    emailSidebarState.email = {
        id: parsedId,
        ...emailData,
    };
    emailSidebarState.show = true;
};

const closeEmailDetailSidebar = () => {
    emailSidebarState.show = false;
    emailSidebarState.email = null;
};

export {
    emailSidebarState,
    openEmailDetailSidebar,
    closeEmailDetailSidebar,
};