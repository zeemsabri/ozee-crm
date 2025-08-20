/**
 * Ozee Embeddable Bug Reporting Script
 * This script provides a standalone, no-plugin bug reporting tool.
 * It combines the functionality of the original background.js and content.js files,
 * replacing browser extension APIs with standard web APIs.
 */

(async () => {
    // --- Configuration and State Management ---
    // Prevents the script from running multiple times on the same page.
    if (window.hasOzeeEmbed) {
        return;
    }
    window.hasOzeeEmbed = true;

    // The base URL for the backend API.
    const BASE_URL = 'http://localhost:8000';

    // State object to manage the application's current mode.
    const appState = {
        sidebarVisible: false,
        mode: 'idle', // 'idle' or 'selecting'
    };

    // This array is the single source of truth for all on-screen markers and the task list.
    let ozeeReports = [];
    let currentTaskData = {}; // Temporary storage for the task being created.

    // Get the project ID from the embed script URL.
    const getProjectId = () => {
        const urlParams = new URLSearchParams(window.location.search);
        // Look for the project_id parameter in the script's own URL, if provided.
        // This is a simple placeholder for how a project ID would be passed.
        const scriptElement = document.querySelector('script[src*="ozee-embed.js"]');
        if (scriptElement) {
            const scriptUrl = new URL(scriptElement.src);
            return scriptUrl.searchParams.get('project_id');
        }
        return null;
    };

    // Check if a project ID is available.
    const projectId = getProjectId();
    if (!projectId) {
        // We'll show an error in the UI if no project ID is found.
        console.error("OZee Embed: No project_id found in the embed script URL.");
    }


    // --- DOM & SHADOW DOM SETUP ---
    let sidebarShadowContainer = null, sidebarShadowRoot = null;
    let popupShadowContainer = null, popupShadowRoot = null;
    let markerShadowContainer = null, markerShadowRoot = null;
    let selectionOverlay = null;


    // --- UI CREATION FUNCTIONS ---

    /**
     * Creates and injects the sidebar HTML and CSS into its Shadow DOM.
     */
    function createSidebar() {
        if (sidebarShadowContainer) return; // Prevent re-creation

        sidebarShadowContainer = document.createElement('div');
        sidebarShadowContainer.id = 'ozee-sidebar-container';
        document.body.appendChild(sidebarShadowContainer);
        sidebarShadowRoot = sidebarShadowContainer.attachShadow({ mode: 'open' });

        const sidebarHTML = `
            <div id="nav" class="sidebar-nav">
                <!-- Main View for creating tasks -->
                <div id="main-view" class="sidebar-view">
                    <div class="header-container">
                        <div class="logo-container"><a href="https://www.ozeeweb.com.au/" target="_blank" rel="noopener"><img src="https://ozeeweb.com.au/wp-content/uploads/2025/08/Logo._simple_white-1.png" alt="OZee Bug Reporting"></a></div>
                        <div class="tasks-section">
                            <span class="tasks-label">TASKS</span>
                            <div id="show-tasks-btn" class="tasks-button" title="View Tasks"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tasks-icon"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg><span class="task-count">0</span></div>
                        </div>
                        <div class="actions-section"><span id="add-task-btn" class="add-task-btn" title="Add Feedback"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="add-task-icon"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></span></div>
                    </div>
                    <div id="hide-sidebar-btn" class="chevron-container" title="Hide Sidebar"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron-icon"><polyline points="15 18 9 12 15 6"></polyline></svg></div>
                </div>

                <!-- Task List View -->
                <div id="task-list-view" class="sidebar-view">
                    <div class="task-list-header">
                        <div id="back-to-main-btn" class="back-arrow" title="Back"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg></div>
                        <h4 class="task-list-title">Tasks</h4>
                    </div>
                    <ul id="task-list-container"></ul>
                </div>

                <!-- Task Details View -->
                <div id="task-details-view" class="sidebar-view">
                    <div class="task-list-header">
                         <div id="back-to-list-btn" class="back-arrow" title="Back to List"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg></div>
                        <h4 class="task-list-title">Task Details</h4>
                    </div>
                    <div class="task-details-content">
                        <p class="task-description-details"></p>
                        <div id="ozee-screenshots-container" class="screenshot-container-details"></div>
                        <div class="tech-details">
                            <h5>Technical details</h5>
                            <ul class="tech-info-list"></ul>
                        </div>
                    </div>
                </div>

                <!-- Loading View -->
                <div id="loading-view" class="sidebar-view active">
                    <div class="status-message">Loading...</div>
                </div>

                <!-- Error View -->
                <div id="error-view" class="sidebar-view">
                     <div id="error-message-text" class="status-message"></div>
                </div>
            </div>`;
        const sidebarStyleSheet = document.createElement('style');
        sidebarStyleSheet.textContent = `
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
            :host { all: initial; font-family: 'Inter', sans-serif; box-sizing: border-box; }
            *, *:before, *:after { box-sizing: inherit; }
            .sidebar-nav { position: fixed; top: 0; right: 0; height: 100vh; width: 60px; background-color: #212121; color: #fff; z-index: 2147483647; display: flex; flex-direction: column; justify-content: space-between; align-items: center; box-shadow: -4px 0px 8px rgba(0,0,0,0.2); transition: all 0.3s ease-in-out; transform: translateX(100%); overflow: hidden; }
            .sidebar-nav.visible { transform: translateX(0); }
            .sidebar-view { display: none; flex-direction: column; justify-content: flex-start; align-items: center; width: 100%; height: 100%; }
            .sidebar-view.active { display: flex; }
            #main-view { justify-content: space-between; padding: 10px 0; }
            .header-container { display: flex; flex-direction: column; align-items: center; width: 100%; }
            .logo-container { margin-bottom: 20px; cursor: pointer; } .logo-container img { width: 30px; height: auto; }
            .tasks-section { display: flex; flex-direction: column; align-items: center; margin-bottom: 20px; width: 100%; }
            .tasks-label { font-size: 10px; font-weight: 600; color: #a0a0a0; margin-bottom: 5px; }
            .tasks-button { position: relative; display: flex; justify-content: center; align-items: center; width: 40px; height: 40px; border-radius: 50%; background-color: #555; cursor: pointer; transition: background-color 0.2s; }
            .task-count { position: absolute; top: -5px; right: -5px; background-color: #ff3b30; color: #fff; font-size: 10px; font-weight: bold; padding: 2px 5px; border-radius: 10px; }
            .add-task-btn { display: flex; justify-content: center; align-items: center; width: 40px; height: 40px; cursor: pointer; border-radius: 4px; transition: background-color 0.2s;}
            .add-task-btn:hover { background-color: #444; }
            .chevron-container { width: 100%; display: flex; justify-content: center; align-items: center; padding: 10px 0; cursor: pointer; border-top: 1px solid #444; }
            /* Task List & Details Styles */
            .task-list-header { display: flex; align-items: center; justify-content: center; position: relative; width: 100%; padding: 10px 0; border-bottom: 1px solid #444; flex-shrink: 0; }
            .back-arrow { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #a0a0a0; }
            .back-arrow:hover { color: #fff; }
            .task-list-title { margin: 0; font-size: 14px; font-weight: 600; }
            #task-list-container { list-style: none; padding: 0; margin: 0; width: 100%; overflow-y: auto; flex-grow: 1; }
            #task-list-container li { padding: 10px 15px; border-bottom: 1px solid #333; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
            #task-list-container li:hover { background-color: #3a3a3a; }
            .task-details-content { width: 100%; padding: 15px; overflow-y: auto; flex-grow: 1; box-sizing: border-box; }
            .task-description-details { font-size: 14px; margin: 0 0 15px 0; white-space: pre-wrap; word-wrap: break-word; }
            .screenshot-container-details { margin-bottom: 15px; }
            .screenshot-container-details img { max-width: 100%; border-radius: 4px; border: 1px solid #444; margin-bottom: 10px; display: block; }
            .tech-details h5 { margin: 0 0 10px 0; font-size: 13px; color: #a0a0a0; }
            .tech-info-list { list-style: none; padding: 0; margin: 0; font-size: 12px; color: #ccc; }
            .tech-info-list li { margin-bottom: 5px; }
            /* Status View Styles */
            .status-message { color: #a0a0a0; font-size: 14px; text-align: center; padding: 20px; align-self: center; justify-self: center; margin: auto; }
        `;
        sidebarShadowRoot.appendChild(sidebarStyleSheet);
        sidebarShadowRoot.innerHTML += sidebarHTML;

        // Event Listeners
        sidebarShadowRoot.getElementById('add-task-btn').addEventListener('click', startScreenshotSelection);
        sidebarShadowRoot.getElementById('hide-sidebar-btn').addEventListener('click', () => toggleSidebar(false));
        sidebarShadowRoot.getElementById('show-tasks-btn').addEventListener('click', () => switchView('task-list-view'));
        sidebarShadowRoot.getElementById('back-to-main-btn').addEventListener('click', () => switchView('main-view'));
        sidebarShadowRoot.getElementById('back-to-list-btn').addEventListener('click', () => switchView('task-list-view'));
    }

    /**
     * Creates the floating marker button used to initiate feedback.
     */
    function createMarker() {
        if (markerShadowContainer) return;

        markerShadowContainer = document.createElement('div');
        markerShadowContainer.id = 'ozee-marker-container';
        document.body.appendChild(markerShadowContainer);
        markerShadowRoot = markerShadowContainer.attachShadow({ mode: 'open' });

        const markerHTML = `<div id="ozee-marker-btn" title="Add Feedback">+</div>`;
        const markerStyleSheet = document.createElement('style');
        markerStyleSheet.textContent = `
            #ozee-marker-btn {
                position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px;
                background-color: #1a73e8; color: white; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-size: 2em; font-weight: bold; cursor: pointer;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); z-index: 2147483646;
                transition: transform 0.3s ease;
            }
            #ozee-marker-btn:hover {
                transform: scale(1.1);
            }`;
        markerShadowRoot.appendChild(markerStyleSheet);
        markerShadowRoot.innerHTML += markerHTML;

        markerShadowRoot.getElementById('ozee-marker-btn').addEventListener('click', startScreenshotSelection);
    }

    /**
     * Creates and displays the feedback submission popup.
     * @param {string} screenshotDataUrl - The base64 data URL of the screenshot.
     * @param {object} rect - The coordinates for placing the marker.
     */
    function showFeedbackPopup(screenshotDataUrl, rect) {
        showMarkers();
        currentTaskData = {
            screenshot: screenshotDataUrl,
            rect: rect,
        };

        if (!popupShadowContainer) {
            popupShadowContainer = document.createElement('div');
            popupShadowContainer.id = 'ozee-popup-container';
            document.body.appendChild(popupShadowContainer);
            popupShadowRoot = popupShadowContainer.attachShadow({ mode: 'open' });

            const popupHTML = `
                <div class="popup-container">
                    <span class="project-title">Project: OZee Reporting</span>
                    <div id="close-popup-btn" class="close-btn-container" title="Close"><svg class="close-icon" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path d="M14.53 1.47l-13.06 13.06M1.47 1.47l13.06 13.06"></path></svg></div>
                    <div class="task-creation-section"><textarea id="description-input" class="description-input" placeholder="Add description..."></textarea></div>
                    <div class="screenshot-preview-container"><img id="screenshot-preview" alt="Screenshot preview"></div>
                    <div class="create-task-btn-container"><button id="create-task-btn" class="create-task-btn">Create task</button></div>
                </div>`;
            const popupStyleSheet = document.createElement('style');
            popupStyleSheet.textContent = `
                :host { all: initial; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
                .popup-container { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 320px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); background-color: #fff; z-index: 2147483647; display: flex; flex-direction: column; font-size: 14px; color: #262626; padding: 16px; box-sizing: border-box; }
                .project-title { font-weight: 500; font-size: 13px; color: rgba(0, 0, 0, 0.45); text-align: center; margin-bottom: 16px; }
                .close-btn-container { position: absolute; top: 10px; right: 10px; cursor: pointer; }
                .close-icon { width: 16px; height: 16px; stroke: #8c8c8c; stroke-width: 2; }
                .description-input { width: 100%; border: 1px solid #d9d9d9; border-radius: 6px; padding: 8px; font-size: 14px; min-height: 60px; box-sizing: border-box; margin-bottom: 12px;}
                .screenshot-preview-container { margin-bottom: 12px; border-radius: 6px; overflow: hidden; border: 1px solid #d9d9d9; }
                #screenshot-preview { max-width: 100%; display: block; }
                .create-task-btn { width: 100%; border: none; background-color: #1a73e8; color: #fff; cursor: pointer; padding: 8px 16px; border-radius: 6px; font-weight: 500; }
                .create-task-btn:hover { background-color: #1562c2; }
            `;
            popupShadowRoot.appendChild(popupStyleSheet);
            popupShadowRoot.innerHTML += popupHTML;

            popupShadowRoot.getElementById('close-popup-btn').addEventListener('click', () => {
                popupShadowContainer.style.display = 'none';
                toggleSidebar(true);
            });

            popupShadowRoot.getElementById('create-task-btn').addEventListener('click', () => {
                const description = popupShadowRoot.getElementById('description-input').value;
                handleTaskCreation({ ...currentTaskData, description });
            });
        }

        popupShadowRoot.getElementById('screenshot-preview').src = screenshotDataUrl;
        popupShadowRoot.getElementById('description-input').value = '';
        popupShadowContainer.style.display = 'block';
    }

    /**
     * Central function to switch between sidebar views.
     * @param {string} viewId - The ID of the view to show.
     * @param {string|null} message - An optional message for the error view.
     */
    function switchView(viewId, message = null) {
        const sidebarNav = sidebarShadowRoot.getElementById('nav');
        const views = sidebarShadowRoot.querySelectorAll('.sidebar-view');
        views.forEach(view => view.classList.remove('active'));

        const targetView = sidebarShadowRoot.getElementById(viewId);
        if (targetView) {
            targetView.classList.add('active');
        }

        if (viewId === 'error-view' && message) {
            sidebarShadowRoot.getElementById('error-message-text').textContent = message;
        }

        if (viewId === 'main-view' || viewId === 'loading-view' || viewId === 'error-view') {
            sidebarNav.style.width = '60px';
        } else {
            sidebarNav.style.width = '280px';
            if (viewId === 'task-list-view') {
                renderTaskList();
            }
        }
    }

    /**
     * Shows the detailed view for a specific task.
     * @param {object} report - The bug report object to display.
     */
    function showTaskDetails(report) {
        switchView('task-details-view');
        const detailsView = sidebarShadowRoot.getElementById('task-details-view');

        detailsView.querySelector('.task-description-details').textContent = report.description || 'No description provided.';

        const screenshotContainer = detailsView.querySelector('#ozee-screenshots-container');
        screenshotContainer.innerHTML = '';

        if (report.screenshot && Array.isArray(report.screenshot) && report.screenshot.length > 0) {
            report.screenshot.forEach(screenshot => {
                const img = document.createElement('img');
                img.src = screenshot.path_url;
                img.alt = 'OZee Bug Report Screenshot';
                screenshotContainer.appendChild(img);
            });
        }
        else if (report.screenshot && typeof report.screenshot === 'string') {
            const img = document.createElement('img');
            img.src = report.screenshot;
            img.alt = 'OZee Bug Report Screenshot';
            screenshotContainer.appendChild(img);
        }
        else {
            const noScreenshotMessage = document.createElement('p');
            noScreenshotMessage.textContent = 'No screenshots available.';
            screenshotContainer.appendChild(noScreenshotMessage);
        }

        const techInfoList = detailsView.querySelector('.tech-info-list');
        techInfoList.innerHTML = '';

        const userAgent = navigator.userAgent;
        let browser = "Unknown";
        if (userAgent.includes("Firefox")) browser = "Firefox";
        else if (userAgent.includes("Chrome")) browser = "Chrome";
        else if (userAgent.includes("Safari")) browser = "Safari";
        else if (userAgent.includes("Edg")) browser = "Edge";
        else if (userAgent.includes("Opera") || userAgent.includes("OPR")) browser = "Opera";
        else if (userAgent.includes("Trident")) browser = "Internet Explorer";

        const os = navigator.platform;

        const techData = {
            "Browser": `${browser}`,
            "OS": os,
            "Screen Resolution": `${window.screen.width} x ${window.screen.height}`,
            "URL": report.pageUrl || window.location.href
        };

        for (const [key, value] of Object.entries(techData)) {
            const li = document.createElement('li');
            li.innerHTML = `<strong>${key}:</strong> ${value}`;
            techInfoList.appendChild(li);
        }
    }

    /**
     * Renders the list of tasks in the sidebar.
     */
    function renderTaskList() {
        const listContainer = sidebarShadowRoot.getElementById('task-list-container');
        listContainer.innerHTML = '';

        if (ozeeReports.length === 0) {
            const emptyItem = document.createElement('li');
            emptyItem.textContent = 'No tasks yet.';
            emptyItem.style.cursor = 'default';
            emptyItem.style.textAlign = 'center';
            emptyItem.style.color = '#a0a0a0';
            listContainer.appendChild(emptyItem);
            return;
        }

        ozeeReports.forEach((report, index) => {
            const listItem = document.createElement('li');
            listItem.textContent = `#${index + 1}: ${report.description || 'No description'}`;
            listItem.title = report.description;
            listItem.addEventListener('click', () => {
                showTaskDetails(report);
            });
            listContainer.appendChild(listItem);
        });
    }

    /**
     * Central function to handle the creation of a task.
     * Replaces the chrome.runtime.sendMessage call with a direct fetch.
     * @param {object} taskData - The complete data for the new task.
     */
    async function handleTaskCreation(taskData) {
        ozeeReports.push(taskData);
        renderMarkers();
        updateTaskCount();
        popupShadowContainer.style.display = 'none';
        toggleSidebar(true);

        const payload = {
            description: taskData.description,
            screenshot: taskData.screenshot, // html2canvas output is already base64
            pageUrl: window.location.href,
            rect: taskData.rect
        };

        try {
            const response = await fetch(`${BASE_URL}/api/bugs/report`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const errorBody = await response.text();
                throw new Error(`Network response was not ok. Status: ${response.status}. Body: ${errorBody}`);
            }

            const data = await response.json();
            console.log('Task created on backend:', data);

            // Update the local task with data from the backend.
            const backendTask = data;
            const localTask = ozeeReports.find(r => r.rect === taskData.rect && r.description === taskData.description);
            if (localTask) {
                localTask.id = backendTask.id;
                localTask.screenshot = backendTask.screenshot;
            }
        } catch (error) {
            console.error('Failed to save task to backend:', error);
            // Revert the local changes if the backend call fails.
            ozeeReports.pop();
            renderMarkers();
            updateTaskCount();
        }
    }

    /**
     * Clears all existing markers and redraws them from the ozeeReports array.
     */
    function renderMarkers() {
        const existingMarkers = document.querySelectorAll('.ozee-task-marker');
        existingMarkers.forEach(marker => marker.remove());

        ozeeReports.forEach((report, index) => {
            if (report.rect) {
                addPin(report.rect, index);
            }
        });
    }

    /**
     * Adds a single, numbered, and CLICKABLE visual pin to the page.
     * @param {object} rect - The {x, y} coordinates for the pin.
     * @param {number} index - The index of the report in the ozeeReports array.
     */
    function addPin(rect, index) {
        const pinElement = document.createElement('div');
        pinElement.className = 'ozee-task-marker';
        pinElement.textContent = index + 1;

        Object.assign(pinElement.style, {
            position: 'absolute',
            top: `${rect.y}px`,
            left: `${rect.x}px`,
            width: '30px',
            height: '30px',
            backgroundColor: '#f75510',
            color: '#fff',
            borderRadius: '50%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: '1em',
            fontWeight: 'bold',
            border: '2px solid #fff',
            boxShadow: '0 2px 4px rgba(0, 0, 0, 0.2)',
            zIndex: '2147483645',
            transform: 'translate(-50%, -50%)',
            cursor: 'pointer'
        });

        pinElement.addEventListener('click', (event) => {
            event.stopPropagation();
            const reportData = ozeeReports[index];
            if (reportData) {
                toggleSidebar(true);
                showTaskDetails(reportData);
            }
        });

        document.body.appendChild(pinElement);
    }

    /**
     * Helper function to update the task count in the sidebar.
     */
    function updateTaskCount() {
        const taskCountElement = sidebarShadowRoot.querySelector('.task-count');
        if (taskCountElement) {
            taskCountElement.textContent = ozeeReports.length;
        }
    }

    /**
     * Hides all on-screen markers.
     */
    function hideMarkers() {
        const markers = document.querySelectorAll('.ozee-task-marker');
        markers.forEach(marker => marker.style.display = 'none');
    }

    /**
     * Shows all on-screen markers.
     */
    function showMarkers() {
        const markers = document.querySelectorAll('.ozee-task-marker');
        markers.forEach(marker => marker.style.display = 'flex');
    }

    /**
     * Fetches tasks and shows the main UI.
     * Replaces the chrome.runtime.sendMessage call with a direct fetch.
     */
    async function fetchAndShowTasks() {
        const currentPageUrl = window.location.href;

        try {
            const url = new URL(`${BASE_URL}/api/bugs`);
            url.searchParams.append('pageUrl', currentPageUrl);
            const response = await fetch(url.toString());

            if (!response.ok) {
                throw new Error(`Network response was not ok. Status: ${response.status}`);
            }

            const data = await response.json();
            ozeeReports = data;
            renderMarkers();
            updateTaskCount();
            switchView('main-view');

        } catch (error) {
            console.error('Failed to fetch tasks for this URL:', error);
            switchView('error-view', 'Failed to load tasks. Please try again later.');
        }
    }

    // --- STATE & FLOW FUNCTIONS ---

    /**
     * Handles sidebar visibility and project status check.
     * Replaces the chrome.runtime.sendMessage call with a direct fetch.
     */
    async function toggleSidebar(forceVisible = null) {
        const shouldBeVisible = forceVisible !== null ? forceVisible : !appState.sidebarVisible;
        const sidebar = sidebarShadowRoot.querySelector('#nav');

        if (shouldBeVisible) {
            sidebar.classList.add('visible');
            appState.sidebarVisible = true;
            switchView('loading-view');
            createMarker();
            if(markerShadowContainer) markerShadowContainer.style.display = 'block';

            if (!projectId) {
                switchView('error-view', 'This project is inactive. No project ID found.');
                return;
            }

            try {
                const url = new URL(`${BASE_URL}/api/bugs/status`);
                url.searchParams.append('pageUrl', window.location.href);

                const response = await fetch(url.toString());
                const data = await response.json();

                if (response.status === 404) {
                    switchView('error-view', "No matching reporting site found for this URL.");
                } else if (response.status === 422) {
                    switchView('error-view', "Invalid request sent to server (422).");
                } else if (!response.ok) {
                    throw new Error(`HTTP Status: ${response.status}`);
                } else {
                    if (data.exists === true) {
                        fetchAndShowTasks();
                    } else {
                        switchView('error-view', 'This project is inactive.');
                    }
                }
            } catch (error) {
                console.error("Error checking project status:", error);
                switchView('error-view', `Could not connect to the server: ${error.message}`);
            }
        } else {
            sidebar.classList.remove('visible');
            appState.sidebarVisible = false;
            if (markerShadowContainer) markerShadowContainer.style.display = 'none';
        }
    }

    function startScreenshotSelection() {
        appState.mode = 'selecting';
        toggleSidebar(false);

        if (!selectionOverlay) {
            selectionOverlay = document.createElement('div');
            selectionOverlay.id = 'ozee-selection-overlay';
            Object.assign(selectionOverlay.style, {
                position: 'fixed', top: '0', left: '0', width: '100vw', height: '100vh',
                backgroundColor: 'rgba(0, 0, 0, 0.5)', cursor: 'crosshair',
                zIndex: '2147483646'
            });
            document.body.appendChild(selectionOverlay);
            selectionOverlay.addEventListener('mousedown', handleMouseDown);
        }
        selectionOverlay.style.display = 'block';
    }

    function deactivateSelectionMode() {
        appState.mode = 'idle';
        if (selectionOverlay) {
            selectionOverlay.style.display = 'none';
        }
    }

    // --- SCREENSHOT & SELECTION LOGIC ---
    let startX, startY, selectionBox, isDragging;

    function handleMouseDown(e) {
        if (appState.mode !== 'selecting') return;
        e.preventDefault();
        startX = e.clientX;
        startY = e.clientY;
        isDragging = false;

        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    }

    function handleMouseMove(e) {
        if (Math.abs(e.clientX - startX) > 5 || Math.abs(e.clientY - startY) > 5) {
            isDragging = true;
            if (!selectionBox) {
                selectionBox = document.createElement('div');
                Object.assign(selectionBox.style, {
                    position: 'fixed',
                    border: '2px dashed #00a8ff',
                    backgroundColor: 'rgba(0, 168, 255, 0.1)',
                    zIndex: '2147483647'
                });
                document.body.appendChild(selectionBox);
            }

            const width = e.clientX - startX;
            const height = e.clientY - startY;
            selectionBox.style.width = `${Math.abs(width)}px`;
            selectionBox.style.height = `${Math.abs(height)}px`;
            selectionBox.style.left = `${width > 0 ? startX : e.clientX}px`;
            selectionBox.style.top = `${height > 0 ? startY : e.clientY}px`;
        }
    }

    function handleMouseUp(e) {
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
        deactivateSelectionMode();

        if (isDragging && selectionBox) {
            const rect = {
                x: parseInt(selectionBox.style.left, 10),
                y: parseInt(selectionBox.style.top, 10),
                width: parseInt(selectionBox.style.width, 10),
                height: parseInt(selectionBox.style.height, 10)
            };
            document.body.removeChild(selectionBox);
            selectionBox = null;

            const pageRect = {
                x: rect.x + window.scrollX,
                y: rect.y + window.scrollY,
                width: rect.width,
                height: rect.height
            };

            // Use html2canvas to capture and crop the screenshot.
            captureAndCropScreenshot(rect, pageRect);
        } else {
            const clickRect = {
                x: e.pageX,
                y: e.pageY
            };
            // Use html2canvas to capture the full page.
            captureAndShowFullPageScreenshot(clickRect);
        }
    }

    /**
     * Replaces chrome.tabs.captureVisibleTab.
     * Captures a screenshot of the visible page and crops it based on the selected area.
     * Requires the html2canvas library.
     * @param {object} rect - The {x, y, width, height} of the crop area on the visible page.
     * @param {object} pageRect - The {x, y, width, height} of the crop area relative to the whole page.
     */
    async function captureAndCropScreenshot(rect, pageRect) {
        try {
            hideMarkers();
            // html2canvas takes the window body as an element and a options object
            const canvas = await html2canvas(document.body, {
                // Ignore the sidebar, marker, and selection overlay in the screenshot
                ignoreElements: (element) => {
                    return element.id === 'ozee-sidebar-container' ||
                        element.id === 'ozee-marker-container' ||
                        element.id === 'ozee-selection-overlay';
                },
                x: window.scrollX + rect.x,
                y: window.scrollY + rect.y,
                width: rect.width,
                height: rect.height
            });
            showFeedbackPopup(canvas.toDataURL('image/png'), pageRect);
        } catch (error) {
            console.error("Error capturing screenshot with html2canvas:", error);
            showMarkers();
            toggleSidebar(true);
        }
    }

    /**
     * Replaces chrome.tabs.captureVisibleTab.
     * Captures a screenshot of the entire visible page.
     * Requires the html2canvas library.
     * @param {object} rect - The click coordinates to place the marker.
     */
    async function captureAndShowFullPageScreenshot(rect) {
        try {
            hideMarkers();
            const canvas = await html2canvas(document.body, {
                // Ignore the sidebar, marker, and selection overlay in the screenshot
                ignoreElements: (element) => {
                    return element.id === 'ozee-sidebar-container' ||
                        element.id === 'ozee-marker-container' ||
                        element.id === 'ozee-selection-overlay';
                }
            });
            showFeedbackPopup(canvas.toDataURL('image/png'), rect);
        } catch (error) {
            console.error("Error capturing screenshot with html2canvas:", error);
            showMarkers();
            toggleSidebar(true);
        }
    }

    // --- INITIALIZATION ---
    // The main entry point for the embeddable script.
    function initialize() {
        // Load html2canvas library dynamically
        const html2canvasScript = document.createElement('script');
        html2canvasScript.src = 'https://html2canvas.hertzen.com/dist/html2canvas.min.js';
        html2canvasScript.onload = () => {
            console.log('html2canvas loaded successfully.');
            // Create the UI after the library is loaded.
            createSidebar();
            createMarker();
            toggleSidebar(true); // Automatically show the sidebar on load
        };
        html2canvasScript.onerror = () => {
            console.error('Failed to load html2canvas library.');
            // Fallback: show error in the UI.
            createSidebar();
            switchView('error-view', 'Failed to load a required library.');
        };
        document.head.appendChild(html2canvasScript);
    }

    // Start the initialization process
    initialize();

})();
