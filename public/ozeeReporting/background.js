// Define the base URL for the backend API.
const BASE_URL = 'http://localhost:8000';

// A simple in-memory cache for project status to avoid redundant API calls.
const projectStatusCache = {};

// Function to fetch tasks and project status from the backend.
async function fetchTasksAndStatus(pageUrl) {
    // Check project status first.
    let statusResponse = await fetch(`${BASE_URL}/api/bugs/status?pageUrl=${encodeURIComponent(pageUrl)}`);
    if (!statusResponse.ok) {
        if (statusResponse.status === 404) {
            return { error: "No matching reporting site found for this URL." };
        }
        if (statusResponse.status === 422) {
            return { error: "Invalid request sent to server (422)." };
        }
        throw new Error(`HTTP Status: ${statusResponse.status}`);
    }

    const statusData = await statusResponse.json();
    if (statusData.exists === false) {
        return { projectStatus: 'inactive' };
    }

    // Fetch tasks if the project is active.
    let tasksResponse = await fetch(`${BASE_URL}/api/bugs?pageUrl=${encodeURIComponent(pageUrl)}`);
    if (!tasksResponse.ok) {
        throw new Error(`HTTP Status: ${tasksResponse.status}`);
    }
    const tasksData = await tasksResponse.json();

    // Store the tasks in chrome.storage.local for content.js to access.
    await chrome.storage.local.set({ [pageUrl]: { tasks: tasksData, status: 'active' } });

    // Also update the in-memory cache.
    projectStatusCache[pageUrl] = 'active';

    return { projectStatus: 'active', tasks: tasksData };
}

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
        const pageUrl = newTask.pageUrl;

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
            .then(async data => {
                console.log('Task created on backend:', data);

                // Re-fetch all tasks for this URL to update the local cache
                // and notify content.js about the new task.
                const updatedTasks = await fetchTasksAndStatus(pageUrl);

                sendResponse({ status: "success", message: "Task created successfully!", data: updatedTasks });
            })
            .catch(error => {
                console.error('Failed to create task:', error);
                sendResponse({ status: "error", message: `Failed to create task: ${error.message}` });
            });

        return true; // Required for asynchronous sendResponse.
    }
    // REFACTORED: Handles the request to get tasks. Now it checks the local cache first.
    else if (request.action === "getTasks") {
        const pageUrl = request.payload?.pageUrl;
        if (!pageUrl) {
            sendResponse({ status: "error", message: "pageUrl is required to fetch tasks." });
            return;
        }

        chrome.storage.local.get(pageUrl, async (result) => {
            if (result[pageUrl]) {
                // If tasks exist in cache, send them immediately.
                sendResponse({ status: "success", data: result[pageUrl].tasks, projectStatus: result[pageUrl].status });
            } else {
                // If not in cache, fetch them from the backend.
                try {
                    const fetchResult = await fetchTasksAndStatus(pageUrl);
                    if (fetchResult.error) {
                        sendResponse({ status: "error", message: fetchResult.error });
                    } else {
                        sendResponse({ status: "success", data: fetchResult.tasks || [], projectStatus: fetchResult.projectStatus });
                    }
                } catch (error) {
                    sendResponse({ status: "error", message: `Failed to fetch tasks: ${error.message}` });
                }
            }
        });
        return true;
    }
    // REFACTORED: Handles the request to check project status. Now it checks the local cache first.
    else if (request.action === "checkProjectStatus") {
        const pageUrl = request.payload?.pageUrl;
        if (!pageUrl) {
            sendResponse({ status: "error", message: "pageUrl is required to check status." });
            return true;
        }

        chrome.storage.local.get(pageUrl, async (result) => {
            if (result[pageUrl] && result[pageUrl].status === 'active') {
                sendResponse({ status: "success", projectStatus: "active" });
            } else if (result[pageUrl] && result[pageUrl].status === 'inactive') {
                sendResponse({ status: "success", projectStatus: "inactive" });
            } else {
                try {
                    const fetchResult = await fetchTasksAndStatus(pageUrl);
                    if (fetchResult.error) {
                        sendResponse({ status: "error", message: fetchResult.error });
                    } else {
                        sendResponse({ status: "success", projectStatus: fetchResult.projectStatus });
                    }
                } catch (error) {
                    sendResponse({ status: "error", message: `Could not connect to the server: ${error.message}` });
                }
            }
        });

        return true;
    }
    // NEW: Handles the request to refresh tasks from the backend.
    else if (request.action === "refreshTasks") {
        const pageUrl = request.payload?.pageUrl;
        if (!pageUrl) {
            sendResponse({ status: "error", message: "pageUrl is required to refresh tasks." });
            return true;
        }

        fetchTasksAndStatus(pageUrl)
            .then(fetchResult => {
                if (fetchResult.error) {
                    sendResponse({ status: "error", message: fetchResult.error });
                } else {
                    sendResponse({ status: "success", data: fetchResult.tasks || [], projectStatus: fetchResult.projectStatus });
                }
            })
            .catch(error => {
                sendResponse({ status: "error", message: `Failed to refresh tasks: ${error.message}` });
            });

        return true;
    }
});
