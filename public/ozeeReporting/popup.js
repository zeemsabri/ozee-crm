document.addEventListener('DOMContentLoaded', () => {
    const startFeedbackBtn = document.getElementById('startFeedbackBtn');
    const statusDiv = document.getElementById('status');

    if (startFeedbackBtn) {
        startFeedbackBtn.addEventListener('click', async () => {
            // Show a status message and hide the button to prevent multiple clicks.
            startFeedbackBtn.classList.add('hidden');
            if(statusDiv) {
                statusDiv.classList.remove('hidden');
            }

            // Get the currently active tab in the current window.
            let [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

            // Send a message to the content script of the active tab to start feedback mode.
            if (tab.id) {
                chrome.tabs.sendMessage(tab.id, { action: "startFeedback" });
            }
        });
    }
});
