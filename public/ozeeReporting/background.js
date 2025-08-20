// Define the base URL for the backend API.
const BASE_URL = 'https://crm.ozeeweb.com.au';

// Listens for the extension icon click.
chrome.action.onClicked.addListener((tab) => {
    // Sends a message to the content script in the active tab to toggle the sidebar.
    if (tab.id) {
        chrome.tabs.sendMessage(tab.id, { action: "toggleSidebar" });
    }
});

// Listener for messages from content scripts.
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    // Handles the request to capture a screenshot.
    if (request.action === "captureScreenshot") {
        chrome.tabs.captureVisibleTab(null, { format: "png" }, (dataUrl) => {
            if (chrome.runtime.lastError) {
                console.error("Error capturing tab:", chrome.runtime.lastError.message);
                sendResponse({ error: chrome.runtime.lastError.message });
                return;
            }
            sendResponse({ dataUrl: dataUrl });
        });
        return true; // Indicates an asynchronous response.
    }
    // Handles the request to save a new task by sending it to the backend.
    else if (request.action === "createTask") {
        const newTask = request.payload;

        fetch(`${BASE_URL}/api/bugs/report`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(newTask),
        })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Network response was not ok. Status: ${response.status}. Body: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Task created on backend:', data);
                sendResponse({ status: "success", message: "Task created successfully!", data: data });
            })
            .catch(error => {
                console.error('Failed to create task:', error);
                sendResponse({ status: "error", message: `Failed to create task: ${error.message}` });
            });

        return true; // Required for asynchronous sendResponse.
    }
    // Handles the request to fetch tasks for a specific URL.
    else if (request.action === "getTasks") {
        const pageUrl = request.payload?.pageUrl;
        if (!pageUrl) {
            sendResponse({ status: "error", message: "pageUrl is required to fetch tasks." });
            return;
        }

        const url = new URL(`${BASE_URL}/api/bugs`);
        url.searchParams.append('pageUrl', pageUrl);

        fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Network response was not ok. Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                sendResponse({ status: "success", data: data });
            })
            .catch(error => {
                console.error('Failed to fetch tasks:', error);
                sendResponse({ status: "error", message: "Failed to fetch tasks." });
            });

        return true;
    }
    // REFINED: Handles the request to check the project status with detailed error handling.
    else if (request.action === "checkProjectStatus") {
        const pageUrl = request.payload?.pageUrl;
        if (!pageUrl) {
            sendResponse({ status: "error", message: "pageUrl is required to check status." });
            return true;
        }
        const url = new URL(`${BASE_URL}/api/bugs/status`);
        url.searchParams.append('pageUrl', pageUrl);

        fetch(url.toString(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
            .then(response => {
                if (response.status === 404) {
                    // Handle "Not Found" specifically.
                    sendResponse({ status: "error", message: "No matching reporting site found for this URL." });
                    return null; // Stop the promise chain
                }
                if (response.status === 422) {
                    // Handle validation errors.
                    sendResponse({ status: "error", message: "Invalid request sent to server (422)." });
                    return null;
                }
                if (!response.ok) {
                    // Handle other server-side errors.
                    throw new Error(`HTTP Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data === null) return; // Already handled error response.

                // Check the 'exists' field from the API response.
                if (data.exists === true) {
                    sendResponse({ status: "success", projectStatus: "active" });
                } else {
                    sendResponse({ status: "success", projectStatus: "inactive" });
                }
            })
            .catch(error => {
                console.error("Error checking project status:", error);
                sendResponse({ status: "error", message: "Could not connect to the server to check project status." });
            });
        return true;
    }
});
