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
    const BASE_URL = 'https://crm.ozeeweb.com.au';

    // Define a color map for task statuses
    const STATUS_COLORS = {
        'To Do': '#8c95a0',      // Muted Blue/Gray
        'In Progress': '#1a73e8', // Blue
        'Paused': '#ffc107',      // Orange/Yellow
        'Blocked': '#dc3545',     // Red
        'Done': '#28a745',        // Green
        'Archived': '#6c757d',    // Dark Gray
    };

    // State object to manage the application's current mode.
    const appState = {
        sidebarVisible: false,
        mode: 'idle', // 'idle' or 'selecting'
        currentFilter: 'To Do', // 'To Do', 'In Progress', 'Done', 'All'
        showAllMarkers: false, // New state variable for marker filtering
        mouseDownCoords: null, // New state variable to store mousedown coordinates
        isDragging: false // New state variable to track if a drag occurred
    };

    // This array is the single source of truth for all on-screen markers and the task list.
    let ozeeReports = [];
    let currentTaskData = {}; // Temporary storage for the task being created.

    // No longer need to get project_id from the URL as it's not being used.

    // --- Session-Based Reporting Logic ---
    function startOzeeSession(email) {
        localStorage.setItem('ozeeReportingSession', 'true');
        if (email) {
            localStorage.setItem('ozeeUserEmail', email);
        }
    }

    function endOzeeSession() {
        localStorage.removeItem('ozeeReportingSession');
        localStorage.removeItem('ozeeUserEmail');
        toggleSidebar(false);
    }

    function isOzeeSessionActive() {
        return localStorage.getItem('ozeeReportingSession') === 'true';
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
                        <div class="actions-section">
                            <span id="add-task-btn" class="add-task-btn" title="Add Feedback"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="add-task-icon"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></span>
                             <span id="filter-markers-btn" class="filter-markers-btn" title="View All Tasks">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                                    <path d="M4 22v-7"></path><path d="M16 4v16"></path><path d="M12 17v5"></path><path d="M20 3v13"></path>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div id="hide-sidebar-btn" class="chevron-container" title="Hide Sidebar"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron-icon"><polyline points="15 18 9 12 15 6"></polyline></svg></div>
                </div>

                <!-- Task List View -->
                <div id="task-list-view" class="sidebar-view">
                    <div class="task-list-header">
                        <div id="back-to-main-btn" class="back-arrow" title="Back"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg></div>
                        <h4 class="task-list-title">Tasks</h4>
                    </div>
                    <input type="text" id="task-search" class="task-search-input" placeholder="Search tasks...">
                    <div class="filter-controls">
                        <button class="filter-btn active" data-filter="To Do">To Do</button>
                        <button class="filter-btn" data-filter="In Progress">In Progress</button>
                        <button class="filter-btn" data-filter="Done">Done</button>
                        <button class="filter-btn" data-filter="All">All</button>
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

                <div class="end-session-container">
                    <button id="end-reporting-btn" class="end-reporting-btn">End Reporting</button>
                </div>
            </div>
            <div id="image-modal" class="image-modal-overlay hidden">
                <div class="image-modal-content">
                    <span class="image-modal-close">&times;</span>
                    <img id="image-modal-img" src="" alt="Full size screenshot">
                </div>
            </div>`;
        const sidebarStyleSheet = document.createElement('style');
        sidebarStyleSheet.textContent = `
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
            :host { all: initial; font-family: 'Inter', sans-serif; box-sizing: border-box; }
            *, *:before, *:after { box-sizing: inherit; }
            .sidebar-nav { position: fixed; top: 0; right: 0; height: 100vh; width: 60px; background-color: #212121; color: #fff; z-index: 2147483647; display: flex; flex-direction: column; justify-content: space-between; align-items: center; box-shadow: -4px 0px 8px rgba(0,0,0,0.2); transition: all 0.3s ease-in-out; transform: translateX(100%); overflow: hidden; }
            .sidebar-nav.visible { transform: translateX(0); width: 560px; }
            .sidebar-view { display: none; flex-direction: column; justify-content: flex-start; align-items: center; width: 100%; height: 100%; }
            .sidebar-view.active { display: flex; }
            #main-view { justify-content: space-between; padding: 10px 0; }
            .header-container { display: flex; flex-direction: column; align-items: center; width: 100%; }
            .logo-container { margin-bottom: 20px; cursor: pointer; } .logo-container img { width: 30px; height: auto; }
            .tasks-section { display: flex; flex-direction: column; align-items: center; margin-bottom: 20px; width: 100%; }
            .tasks-label { font-size: 10px; font-weight: 600; color: #a0a0a0; margin-bottom: 5px; }
            .tasks-button { position: relative; display: flex; justify-content: center; align-items: center; width: 40px; height: 40px; border-radius: 50%; background-color: #555; cursor: pointer; transition: background-color 0.2s; }
            .task-count { position: absolute; top: -5px; right: -5px; background-color: #ff3b30; color: #fff; font-size: 10px; font-weight: bold; padding: 2px 5px; border-radius: 10px; }
            .actions-section {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            .add-task-btn, .filter-markers-btn { display: flex; justify-content: center; align-items: center; width: 40px; height: 40px; cursor: pointer; border-radius: 50%; transition: background-color 0.2s; border: none; background-color: #555;}
            .add-task-btn:hover, .filter-markers-btn:hover { background-color: #444; }
            .filter-markers-btn.active { background-color: #1a73e8; }
            .chevron-container { width: 100%; display: flex; justify-content: center; align-items: center; padding: 10px 0; cursor: pointer; border-top: 1px solid #444; }
            .end-session-container { position: absolute; bottom: 0; width: 100%; padding: 10px; border-top: 1px solid #444; }
            .end-reporting-btn { width: 100%; background-color: #dc3545; color: #fff; border: none; padding: 10px; border-radius: 4px; cursor: pointer; font-size: 14px; }
            .end-reporting-btn:hover { background-color: #c82333; }

            /* Task List & Details Styles */
            .task-list-header { display: flex; align-items: center; justify-content: center; position: relative; width: 100%; padding: 10px 0; border-bottom: 1px solid #444; flex-shrink: 0; }
            .back-arrow { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #a0a0a0; }
            .back-arrow:hover { color: #fff; }
            .task-list-title { margin: 0; font-size: 14px; font-weight: 600; }
            .task-search-input { width: 90%; padding: 8px; margin: 10px auto; border: 1px solid #444; background-color: #333; color: #fff; border-radius: 4px; }
            .filter-controls { display: flex; justify-content: space-around; width: 100%; padding: 10px 0; border-bottom: 1px solid #444; }
            .filter-btn { background: none; border: none; color: #a0a0a0; font-size: 12px; cursor: pointer; padding: 5px 10px; border-radius: 4px; }
            .filter-btn:hover { background-color: #333; }
            .filter-btn.active { color: #fff; font-weight: bold; background-color: #444; }
            #task-list-container { list-style: none; padding: 0; margin: 0; width: 100%; overflow-y: auto; flex-grow: 1; }
            .task-list-item {
                padding: 10px 15px;
                border-bottom: 1px solid #333;
                font-size: 12px;
                cursor: pointer;
                display: flex;
                flex-direction: column;
                position: relative;
            }
            .task-list-item:hover { background-color: #3a3a3a; }
            .task-list-item .status-badge {
                position: absolute;
                top: 10px;
                right: 15px;
                font-size: 10px;
                font-weight: bold;
                padding: 2px 6px;
                border-radius: 10px;
                color: #fff;
            }
            .status-badge.to-do { background-color: #8c95a0; }
            .status-badge.in-progress { background-color: #1a73e8; }
            .status-badge.paused { background-color: #ffc107; }
            .status-badge.blocked { background-color: #dc3545; }
            .status-badge.done { background-color: #28a745; }
            .status-badge.archived { background-color: #6c757d; }
            .task-list-item h5 {
                margin: 0;
                font-size: 14px;
                line-height: 1.4;
                color: #fff;
            }
            .task-list-item .description {
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                text-overflow: ellipsis;
                color: #ccc;
                margin-bottom: 5px;
            }
            .task-list-item .dates {
                font-size: 10px;
                color: #a0a0a0;
            }
            .ozee-marker-tooltip {
                display: none;
                position: absolute;
                background-color: #333;
                color: #fff;
                padding: 5px 10px;
                border-radius: 4px;
                white-space: nowrap;
                font-size: 12px;
                z-index: 2147483648;
                top: -5px;
                left: 35px;
            }
            .ozee-marker-tooltip.visible {
                display: block;
            }
            .ozee-marker-tooltip-card {
                position: absolute;
                background-color: #fff;
                color: #212121;
                padding: 15px;
                border-radius: 8px;
                max-width: 250px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 2147483649;
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .ozee-marker-tooltip-card .status-badge {
                position: static;
                align-self: flex-start;
            }
            .ozee-marker-tooltip-card h6 {
                margin: 0;
                font-size: 12px;
                color: #8c8c8c;
            }
            .ozee-marker-tooltip-card p {
                margin: 0;
                font-size: 14px;
                line-height: 1.4;
                word-wrap: break-word;
            }
            .ozee-marker-tooltip-card a {
                color: #1a73e8;
                text-decoration: underline;
                cursor: pointer;
                font-size: 12px;
                margin-top: 10px;
            }

            .task-details-content { width: 100%; padding: 15px; overflow-y: auto; flex-grow: 1; box-sizing: border-box; }
            .screenshot-container-details { margin-bottom: 15px; }
            .screenshot-container-details img {
                max-width: 100%;
                border-radius: 4px;
                border: 1px solid #444;
                margin-bottom: 10px;
                display: block;
                cursor: pointer;
            }
            .tech-details h5 { margin: 0 0 10px 0; font-size: 13px; color: #a0a0a0; }
            .tech-info-list { list-style: none; padding: 0; margin: 0; font-size: 12px; color: #ccc; }
            .tech-info-list li { margin-bottom: 5px; }
            /* Status View Styles */
            .status-message { color: #a0a0a0; font-size: 14px; text-align: center; padding: 20px; align-self: center; justify-self: center; margin: auto; }

            /* Image Modal Styles */
            .image-modal-overlay {
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                background-color: rgba(0, 0, 0, 0.9);
                display: flex; justify-content: center; align-items: center;
                z-index: 2147483647;
                padding: 20px;
            }
            .image-modal-content {
                position: relative;
                max-width: 90vw;
                max-height: 90vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            #image-modal-img {
                    max-width: 100%;
                    max-height: 100%;
                    object-fit: contain;
                    border-radius: 8px;
            }
            .image-modal-close {
                position: absolute;
                top: 10px; right: 20px;
                font-size: 40px;
                color: #fff;
                cursor: pointer;
                line-height: 1;
                font-weight: 300;
            }
            .hidden { display: none; }
        `;
        sidebarShadowRoot.appendChild(sidebarStyleSheet);
        sidebarShadowRoot.innerHTML += sidebarHTML;

        // Event Listeners
        sidebarShadowRoot.getElementById('add-task-btn').addEventListener('click', startScreenshotSelection);
        sidebarShadowRoot.getElementById('hide-sidebar-btn').addEventListener('click', () => toggleSidebar(false));
        sidebarShadowRoot.getElementById('show-tasks-btn').addEventListener('click', () => switchView('task-list-view'));
        sidebarShadowRoot.getElementById('back-to-main-btn').addEventListener('click', () => switchView('main-view'));
        sidebarShadowRoot.getElementById('back-to-list-btn').addEventListener('click', () => switchView('task-list-view'));

        // Filter button event listeners
        sidebarShadowRoot.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const filter = e.target.dataset.filter;
                appState.currentFilter = filter;
                sidebarShadowRoot.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');
                renderTaskList();
            });
        });

        // Search input event listener
        sidebarShadowRoot.getElementById('task-search').addEventListener('keyup', renderTaskList);

        // End Reporting button listener
        sidebarShadowRoot.getElementById('end-reporting-btn').addEventListener('click', endOzeeSession);

        // Marker filter toggle listener
        sidebarShadowRoot.getElementById('filter-markers-btn').addEventListener('click', () => {
            appState.showAllMarkers = !appState.showAllMarkers;
            const filterBtn = sidebarShadowRoot.getElementById('filter-markers-btn');
            if (appState.showAllMarkers) {
                filterBtn.classList.add('active');
                filterBtn.title = 'Show To Do Tasks Only';
            } else {
                filterBtn.classList.remove('active');
                filterBtn.title = 'View All Tasks';
            }
            renderMarkers();
        });

        // Image modal close listener
        sidebarShadowRoot.querySelector('.image-modal-close').addEventListener('click', () => {
            sidebarShadowRoot.getElementById('image-modal').classList.add('hidden');
        });
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
            }
            .hidden { display: none; }
        `;
        markerShadowRoot.appendChild(markerStyleSheet);
        markerShadowRoot.innerHTML += markerHTML;

        markerShadowRoot.getElementById('ozee-marker-btn').addEventListener('click', startScreenshotSelection);
    }

    /**
     * Creates and displays the feedback submission popup.
     * @param {string} screenshotDataUrl - The base64 data URL of the screenshot.
     * @param {object} rect - The coordinates for placing the marker.
     */
    function showFeedbackPopup(rect) {
        showMarkers();

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
                    <div id="popup-status-message" class="popup-status-message hidden"></div>
                </div>`;
            const popupStyleSheet = document.createElement('style');
            popupStyleSheet.textContent = `
                :host { all: initial; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
                .popup-container {
                    position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
                    width: 50vw;
                    max-width: 800px;
                    max-height: 60vh;
                    overflow-y: auto;
                    border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); background-color: #fff; z-index: 2147483647;
                    display: flex;
                    flex-direction: column;
                    font-size: 14px; color: #262626;
                    padding: 20px;
                    box-sizing: border-box;
                }
                .project-title { font-weight: 500; font-size: 13px; color: rgba(0, 0, 0, 0.45); text-align: center; margin-bottom: 16px; }
                .close-btn-container { position: absolute; top: 10px; right: 10px; cursor: pointer; }
                .close-icon { width: 16px; height: 16px; stroke: #8c8c8c; stroke-width: 2; }
                .description-input {
                    width: 100%; border: 1px solid #d9d9d9; border-radius: 6px; padding: 8px;
                    font-size: 14px;
                    min-height: 100px;
                    box-sizing: border-box; margin-bottom: 12px;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease;
                }
                .description-input:focus {
                    outline: none;
                    border-color: #1a73e8;
                    box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
                }
                .screenshot-preview-container {
                    margin-bottom: 12px; border-radius: 6px; overflow: hidden; border: 1px solid #d9d9d9;
                    max-height: 30vh;
                }
                #screenshot-preview {
                    max-width: 100%; display: block;
                    object-fit: contain;
                }
                .create-task-btn {
                    width: 100%; border: none; background-color: #1a73e8; color: #fff; cursor: pointer;
                    padding: 8px 16px; border-radius: 6px; font-weight: 500;
                    transition: background-color 0.2s ease, transform 0.1s ease;
                }
                .create-task-btn:hover { background-color: #1562c2; }
                .create-task-btn:active { transform: scale(0.98); }
                .popup-status-message {
                    text-align: center; font-style: italic; color: #a0a0a0; margin-top: 10px;
                }
                .popup-status-message.hidden {
                    display: none;
                }
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

        const screenshotPreview = popupShadowRoot.getElementById('screenshot-preview');
        const statusMessageEl = popupShadowRoot.getElementById('popup-status-message');
        const createBtn = popupShadowRoot.getElementById('create-task-btn');

        // Initially, show a loading message and a blank placeholder image.
        createBtn.disabled = true; // Disable the button immediately
        createBtn.textContent = 'Capturing...';
        statusMessageEl.classList.remove('hidden');
        statusMessageEl.textContent = 'Capturing screenshot...';
        screenshotPreview.src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='; // A tiny transparent GIF placeholder
        screenshotPreview.style.opacity = '0.5'; // Visually indicate it's loading

        popupShadowContainer.style.display = 'block';
        currentTaskData = {
            rect: rect,
            description: null,
            screenshot: null,
        };
    }

    /**
     * Helper function to update the popup UI with a screenshot.
     * @param {string|null} screenshotDataUrl - The screenshot data URL or null for error state.
     */
    function updatePopupWithScreenshot(screenshotDataUrl) {
        const screenshotPreview = popupShadowRoot.getElementById('screenshot-preview');
        const statusMessageEl = popupShadowRoot.getElementById('popup-status-message');
        const createBtn = popupShadowRoot.getElementById('create-task-btn');

        if (screenshotDataUrl) {
            screenshotPreview.src = screenshotDataUrl;
            screenshotPreview.style.opacity = '1';
            statusMessageEl.textContent = ''; // Clear the message
            statusMessageEl.classList.add('hidden');
            currentTaskData.screenshot = screenshotDataUrl;
            createBtn.disabled = false;
            createBtn.textContent = 'Create task';
        } else {
            // Show a placeholder or error message if the screenshot failed
            screenshotPreview.src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
            statusMessageEl.textContent = 'Failed to capture screenshot.';
            statusMessageEl.classList.remove('hidden');
            createBtn.disabled = true; // Keep the button disabled on failure
            createBtn.textContent = 'Create task';
        }
    }

    /**
     * Central function to switch between sidebar views.
     * @param {string} viewId - The ID of the view to show.
     * @param {string|null} message - An optional message for the error view.
     */
    function switchView(viewId, message = null) {
        const sidebarNav = sidebarShadowRoot.querySelector('#nav');
        const views = sidebarShadowRoot.querySelectorAll('.sidebar-view');
        views.forEach(view => view.classList.remove('active'));

        const targetView = sidebarShadowRoot.getElementById(viewId);
        if (targetView) {
            targetView.classList.add('active');
        }

        if (viewId === 'error-view' && message) {
            sidebarShadowRoot.getElementById('error-message-text').textContent = message;
        }

        const endReportingBtn = sidebarShadowRoot.getElementById('end-reporting-btn');
        if (viewId === 'main-view' || viewId === 'loading-view' || viewId === 'error-view') {
            sidebarNav.style.width = '60px';
            if (endReportingBtn) {
                endReportingBtn.textContent = 'Exit';
            }
        } else {
            sidebarNav.style.width = '560px';
            if (endReportingBtn) {
                endReportingBtn.textContent = 'End Reporting';
            }
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

        if (report.screenshot && Array.isArray(report.screenshot)) {
            report.screenshot.forEach(screenshotObject => {
                const img = document.createElement('img');
                img.src = screenshotObject.thumbnail_url || screenshotObject.path_url;
                img.alt = 'Bug Report Screenshot Thumbnail';
                img.classList.add('clickable-screenshot');
                img.addEventListener('click', () => showImageModal(screenshotObject.path_url));
                screenshotContainer.appendChild(img);
            });
        } else if (report.screenshot && typeof report.screenshot === 'string') {
            const img = document.createElement('img');
            img.src = report.screenshot;
            img.alt = 'OZee Bug Report Screenshot';
            img.classList.add('clickable-screenshot');
            img.addEventListener('click', () => showImageModal(report.screenshot));
            screenshotContainer.appendChild(img);
        } else {
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
        const searchQuery = sidebarShadowRoot.getElementById('task-search').value.toLowerCase();

        // Filter the reports based on the current filter and search query
        const filteredReports = ozeeReports.filter(report => {
            const statusMatch = appState.currentFilter === 'All' || report.status === appState.currentFilter;
            const descriptionMatch = report.description && report.description.toLowerCase().includes(searchQuery);
            const idMatch = report.id && report.id.toString().toLowerCase().includes(searchQuery);
            const indexMatch = (`task #${ozeeReports.indexOf(report) + 1}`).toLowerCase().includes(searchQuery);
            const searchMatch = searchQuery === '' || descriptionMatch || idMatch || indexMatch;

            return statusMatch && searchMatch;
        });

        if (filteredReports.length === 0) {
            const emptyItem = document.createElement('li');
            emptyItem.textContent = 'No tasks found.';
            emptyItem.style.cursor = 'default';
            emptyItem.style.textAlign = 'center';
            emptyItem.style.color = '#a0a0a0';
            listContainer.appendChild(emptyItem);
            return;
        }

        filteredReports.forEach((report, index) => {
            const listItem = document.createElement('li');
            listItem.className = 'task-list-item';

            const statusClass = (report.status || 'To Do').toLowerCase().replace(' ', '-');
            const createdDate = new Date(report.created_at).toLocaleDateString();
            const updatedDate = new Date(report.updated_at).toLocaleDateString();

            const truncatedDescription = report.description || 'No description';

            listItem.innerHTML = `
                <div class="status-badge ${statusClass}">${report.status}</div>
                <h5>Task #${report.id || ozeeReports.indexOf(report) + 1}</h5>
                <div class="description">${truncatedDescription}</div>
                <div class="dates">Created: ${createdDate} | Updated: ${updatedDate}</div>
            `;

            listItem.addEventListener('click', (event) => {
                event.stopPropagation(); // Stop propagation here as well for safety
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
        const createBtn = popupShadowRoot.getElementById('create-task-btn');
        const statusMessageEl = popupShadowRoot.getElementById('popup-status-message');

        createBtn.disabled = true;
        createBtn.textContent = 'Saving...';
        statusMessageEl.classList.remove('hidden');
        statusMessageEl.textContent = 'Saving...';

        try {
            const userEmail = localStorage.getItem('ozeeUserEmail');
            const payload = {
                description: taskData.description,
                screenshot: taskData.screenshot, // html2canvas output is already base64
                pageUrl: window.location.href,
                rect: taskData.rect,
                user_email: userEmail
            };

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

            // Update the local task with data from the backend.
            const backendTask = data;
            ozeeReports.push(backendTask);
            renderMarkers();
            updateTaskCount();

            statusMessageEl.textContent = 'Task created successfully!';
        } catch (error) {
            statusMessageEl.textContent = `Failed to save task: ${error.message}`;
            // If creation fails, we don't add the pin, so no need to revert changes
        } finally {
            createBtn.disabled = false;
            createBtn.textContent = 'Create task';
            setTimeout(() => {
                popupShadowContainer.style.display = 'none';
                toggleSidebar(true);
            }, 2000); // Give user time to see the status message
        }
    }

    /**
     * Clears all existing markers and redraws them from the ozeeReports array.
     */
    function renderMarkers() {
        const existingMarkers = document.querySelectorAll('.ozee-task-marker');
        existingMarkers.forEach(marker => marker.remove());

        // New logic: Filter markers based on appState.showAllMarkers
        const markersToRender = appState.showAllMarkers ? ozeeReports : ozeeReports.filter(report => report.status === 'To Do');

        markersToRender.forEach((report, index) => {
            if (report.rect) {
                addPin(report, index);
            }
        });
    }

    /**
     * Adds a single, numbered, and clickable visual pin to the page.
     * Now includes color-coding and a custom tooltip.
     * @param {object} report - The report object to generate the pin for.
     * @param {number} index - The index of the report in the ozeeReports array.
     */
    function addPin(report, index) {
        const pinElement = document.createElement('div');
        pinElement.className = 'ozee-task-marker';
        pinElement.textContent = index + 1;

        // Remove title attribute to use custom tooltip
        pinElement.removeAttribute('title');

        // Color-coding logic using the STATUS_COLORS map
        const markerColor = STATUS_COLORS[report.status] || STATUS_COLORS['To Do'];

        Object.assign(pinElement.style, {
            position: 'absolute',
            top: `${report.rect.y}px`,
            left: `${report.rect.x}px`,
            width: '30px',
            height: '30px',
            backgroundColor: markerColor,
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
            cursor: 'default' // Cursor is now default since marker isn't clickable
        });

        // Add custom tooltip functionality
        const tooltip = document.createElement('div');
        tooltip.className = 'ozee-marker-tooltip-card';

        // Truncate description for the tooltip
        const maxTooltipLength = 70;
        const truncatedDescription = (report.description || 'No description').length > maxTooltipLength ?
            report.description.substring(0, maxTooltipLength) + '...' :
            report.description;

        const statusClass = (report.status || 'To Do').toLowerCase().replace(' ', '-');

        tooltip.innerHTML = `
            <div class="status-badge ${statusClass}">${report.status}</div>
            <p>${truncatedDescription}</p>
            <h6>Task #${report.id || index + 1}</h6>
            <a href="#" class="view-details-link">View Details</a>
        `;

        // Add styling to the tooltip
        Object.assign(tooltip.style, {
            position: 'absolute',
            top: `${report.rect.y + 35}px`, // Position below the marker
            left: `${report.rect.x}px`,
            transform: 'translateX(-50%)',
            display: 'none',
        });

        // Attach click listener to the new link
        const viewLink = tooltip.querySelector('.view-details-link');
        viewLink.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleSidebar(true);
            showTaskDetails(report);
        });

        pinElement.addEventListener('mouseover', () => {
            document.body.appendChild(tooltip);
            // Position the tooltip relative to the marker
            Object.assign(tooltip.style, {
                top: `${report.rect.y + 35}px`,
                left: `${report.rect.x}px`,
                display: 'flex',
            });
        });
        pinElement.addEventListener('mouseout', () => {
            if (tooltip.parentElement === document.body) {
                document.body.removeChild(tooltip);
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
     * Shows the full-size image in a modal.
     * @param {string} imageUrl - The URL of the image to display.
     */
    function showImageModal(imageUrl) {
        const modal = sidebarShadowRoot.getElementById('image-modal');
        const modalImg = sidebarShadowRoot.getElementById('image-modal-img');
        modalImg.src = imageUrl;
        modal.classList.remove('hidden');
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
            selectionOverlay.addEventListener('mouseup', handleMouseUp);
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
        appState.isDragging = false;

        // Store initial mouse coordinates
        appState.mouseDownCoords = {
            x: e.pageX,
            y: e.pageY
        };

        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    }

    function handleMouseMove(e) {
        // Define a small drag threshold
        const dragThreshold = 5;
        if (Math.abs(e.clientX - startX) > dragThreshold || Math.abs(e.clientY - startY) > dragThreshold) {
            appState.isDragging = true;
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

        if (appState.isDragging) {
            const endX = e.pageX;
            const endY = e.pageY;

            const rect = {
                x: Math.min(appState.mouseDownCoords.x, endX),
                y: Math.min(appState.mouseDownCoords.y, endY),
                width: Math.abs(endX - appState.mouseDownCoords.x),
                height: Math.abs(endY - appState.mouseDownCoords.y)
            };

            const scrollY = window.pageYOffset || document.documentElement.scrollTop;
            const documentRect = {
                x: rect.x,
                y: rect.y + scrollY,
                width: rect.width,
                height: rect.height
            };

            // Show popup immediately with loading state and trigger screenshot in background
            showFeedbackPopup(documentRect);
            captureAndCropScreenshot(documentRect);

        } else {
            const rect = {
                x: e.pageX,
                y: e.pageY
            };
            const scrollY = window.pageYOffset || document.documentElement.scrollTop;
            const documentRect = {
                x: rect.x,
                y: rect.y + scrollY
            };

            // Show popup immediately with loading state and trigger screenshot in background
            showFeedbackPopup(documentRect);
            captureAndShowFullPageScreenshot(documentRect);
        }

        // Clean up selection box after a delay to allow the screenshot to be taken without it
        if (selectionBox) {
            setTimeout(() => {
                document.body.removeChild(selectionBox);
                selectionBox = null;
            }, 100);
        }
    }

    /**
     * Captures a screenshot of the visible page and crops it based on the selected area.
     * @param {object} documentRect - The {x, y, width, height} of the crop area relative to the whole page.
     */
    async function captureAndCropScreenshot(documentRect) {
        try {
            const canvas = await new Promise(resolve => {
                setTimeout(() => {
                    html2canvas(document.body, {
                        ignoreElements: (element) => {
                            return element.id === 'ozee-sidebar-container' ||
                                element.id === 'ozee-marker-container' ||
                                element.id === 'ozee-selection-overlay';
                        },
                        width: Math.max(document.body.scrollWidth, document.documentElement.scrollWidth, document.documentElement.clientWidth),
                        height: Math.max(document.body.scrollHeight, document.documentElement.scrollHeight, document.documentElement.clientHeight),
                        allowTaint: true,
                        useCORS: true,
                    }).then(canvas => resolve(canvas));
                }, 500); // Wait for 500 milliseconds (0.5 seconds)
            });

            // Create a new canvas to hold the cropped section
            const croppedCanvas = document.createElement('canvas');
            const ctx = croppedCanvas.getContext('2d');
            croppedCanvas.width = documentRect.width;
            croppedCanvas.height = documentRect.height;

            ctx.drawImage(
                canvas,
                documentRect.x, documentRect.y, documentRect.width, documentRect.height,
                0, 0, documentRect.width, documentRect.height
            );

            const screenshotDataUrl = croppedCanvas.toDataURL('image/png');
            updatePopupWithScreenshot(screenshotDataUrl);
        } catch (error) {
            console.error("Ozee screenshot failed:", error);
            updatePopupWithScreenshot(null);
            showMarkers();
            toggleSidebar(true);
        }
    }

    /**
     * Captures a screenshot of the entire visible page.
     * Requires the html2canvas library.
     * @param {object} documentRect - The click coordinates to place the marker.
     */
    async function captureAndShowFullPageScreenshot(documentRect) {
        try {
            const scrollY = window.pageYOffset || document.documentElement.scrollTop;

            const canvas = await new Promise(resolve => {
                setTimeout(() => {
                    html2canvas(document.body, {
                        ignoreElements: (element) => {
                            return element.id === 'ozee-sidebar-container' ||
                                element.id === 'ozee-marker-container' ||
                                element.id === 'ozee-selection-overlay';
                        },
                        width: Math.max(document.body.scrollWidth, document.documentElement.scrollWidth, document.documentElement.clientWidth),
                        height: Math.max(document.body.scrollHeight, document.documentElement.scrollHeight, document.documentElement.clientHeight),
                        scrollY: -scrollY,
                        allowTaint: true,
                        useCORS: true,
                    }).then(canvas => resolve(canvas));
                }, 500); // Wait for 500 milliseconds (0.5 seconds)
            });

            const screenshotDataUrl = canvas.toDataURL('image/png');
            updatePopupWithScreenshot(screenshotDataUrl);
        } catch (error) {
            console.error("Ozee screenshot failed:", error);
            updatePopupWithScreenshot(null);
            showMarkers();
            toggleSidebar(true);
        }
    }

    /**
     * Helper function to update the popup UI with a screenshot.
     * @param {string|null} screenshotDataUrl - The screenshot data URL or null for error state.
     */
    function updatePopupWithScreenshot(screenshotDataUrl) {
        const screenshotPreview = popupShadowRoot.getElementById('screenshot-preview');
        const statusMessageEl = popupShadowRoot.getElementById('popup-status-message');
        const createBtn = popupShadowRoot.getElementById('create-task-btn');

        if (screenshotDataUrl) {
            screenshotPreview.src = screenshotDataUrl;
            screenshotPreview.style.opacity = '1';
            statusMessageEl.textContent = ''; // Clear the message
            statusMessageEl.classList.add('hidden');
            currentTaskData.screenshot = screenshotDataUrl;
            createBtn.disabled = false;
            createBtn.textContent = 'Create task';
        } else {
            // Show a placeholder or error message if the screenshot failed
            screenshotPreview.src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
            statusMessageEl.textContent = 'Failed to capture screenshot.';
            statusMessageEl.classList.remove('hidden');
            createBtn.disabled = true; // Keep the button disabled on failure
            createBtn.textContent = 'Create task';
        }
    }


    // --- INITIALIZATION ---
    /**
     * The main entry point for the embeddable script.
     * This function now acts as a controller, checking for session status before initializing the UI.
     */
    function initialize() {
        // Check for URL parameter first
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('reporting') && urlParams.get('reporting') === 'true') {
            const userEmail = urlParams.get('user_email');
            startOzeeSession(userEmail);
        }

        // If a session is active (either from the URL or localStorage), initialize the UI.
        if (isOzeeSessionActive()) {
            const html2canvasScript = document.createElement('script');
            html2canvasScript.src = 'https://html2canvas.hertzen.com/dist/html2canvas.min.js';
            html2canvasScript.onload = () => {
                createSidebar();
                createMarker();
                toggleSidebar(true);

                // Start URL change listener
                setupUrlChangeListener();
            };
            html2canvasScript.onerror = () => {
                createSidebar();
                switchView('error-view', 'Failed to load a required library.');
            };
            document.head.appendChild(html2canvasScript);
        }
    }

    /**
     * Sets up a listener to detect URL changes in SPAs and re-fetches tasks.
     */
    function setupUrlChangeListener() {
        let lastUrl = window.location.href;
        const observer = new MutationObserver(() => {
            const currentUrl = window.location.href;
            if (currentUrl !== lastUrl) {
                lastUrl = currentUrl;
                // Re-run the task fetching and rendering logic for the new page
                ozeeReports = []; // Clear old tasks
                renderMarkers(); // Remove old markers
                updateTaskCount(); // Reset task count
                fetchAndShowTasks();
            }
        });

        const config = { subtree: true, childList: true };
        observer.observe(document, config);

        // Also listen for popstate and hashchange events for browser history navigation
        window.addEventListener('popstate', () => {
            const currentUrl = window.location.href;
            if (currentUrl !== lastUrl) {
                lastUrl = currentUrl;
                ozeeReports = [];
                renderMarkers();
                updateTaskCount();
                fetchAndShowTasks();
            }
        });
    }

    // Start the initialization process
    initialize();

})();
