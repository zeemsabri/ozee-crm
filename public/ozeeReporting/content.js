(() => {
    // Prevents the script from running multiple times on the same page.
    if (window.hasRun) {
        return;
    }
    window.hasRun = true;

    // --- STATE MANAGEMENT ---
    const appState = {
        sidebarVisible: false,
        mode: 'idle', // 'idle' or 'selecting'
        showAllTasks: false,
        activeStatusFilter: ['To Do', 'In Progress']
    };
    // BRAND UPDATE: This array is the single source of truth for all on-screen markers and the task list.
    let ozeeReports = [];
    let currentTaskData = {}; // Temporary storage for the task being created
    let tasksFetched = false; // Flag to ensure tasks are fetched only once per page load.

    // --- DOM & SHADOW DOM SETUP ---
    let sidebarShadowContainer = null, sidebarShadowRoot = null;
    let popupShadowContainer = null, popupShadowRoot = null;
    let markerShadowContainer = null, markerShadowRoot = null;
    let selectionOverlay = null;
    let hoverCard = null; // Global reference to the hover card

    // --- INITIALIZATION ---
    sidebarShadowContainer = document.createElement('div');
    // BRAND UPDATE: Changed ID to reflect new branding.
    sidebarShadowContainer.id = 'ozee-sidebar-container';
    document.body.appendChild(sidebarShadowContainer);
    sidebarShadowRoot = sidebarShadowContainer.attachShadow({ mode: 'open' });
    createSidebar();


    // --- UI CREATION FUNCTIONS ---

    /**
     * Creates and injects the sidebar HTML and CSS into its Shadow DOM.
     */
    function createSidebar() {
        const sidebarHTML = `
            <div id="nav" class="sidebar-nav">
                <!-- Main View for creating tasks -->
                <div id="main-view" class="sidebar-view">
                    <div class="header-container">
                        <!-- BRAND UPDATE: Logo, link, and alt text updated -->
                        <div class="logo-container"><a href="https://www.ozeeweb.com.au/" target="_blank" rel="noopener"><img src="https://ozeeweb.com.au/wp-content/uploads/2025/08/Logo._simple_white-1.png" alt="OZee Bug Reporting"></a></div>
                        <div class="tasks-section">
                            <span class="tasks-label">TASKS</span>
                            <div id="show-tasks-btn" class="tasks-button" title="View Tasks"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tasks-icon"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg><span class="task-count">0</span></div>
                        </div>
                        <div class="actions-section">
                            <span id="add-task-btn" class="add-task-btn" title="Add Feedback"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="add-task-icon"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></span>
                            <span id="toggle-tasks-btn" class="toggle-tasks-btn" title="Show All Tasks">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                             <span id="refresh-tasks-btn" class="refresh-tasks-btn" title="Refresh Tasks">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-refresh-cw"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.5 14h6.7a8.5 8.5 0 0 1 0-8h-6.7c-3.1 0-5.5 2.4-5.5 5.5s2.4 5.5 5.5 5.5z"></path></svg>
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
                    <div class="task-list-controls">
                        <input type="text" id="search-input" placeholder="Search tasks..." class="search-input">
                        <div class="status-filters">
                            <span class="filter-btn active" data-status="To Do">To Do</span>
                            <span class="filter-btn active" data-status="In Progress">In Progress</span>
                            <span class="filter-btn" data-status="Done">Done</span>
                            <span class="filter-btn" data-status="Blocked">Blocked</span>
                            <span class="filter-btn" data-status="Archived">Archived</span>
                        </div>
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
                     <div id="error-message-text" class="status-message">This project is inactive.</div>
                </div>
            </div>`;
        const sidebarStyleSheet = document.createElement('style');
        sidebarStyleSheet.textContent = `
            :host { all: initial; font-family: 'Inter', sans-serif; }
            .sidebar-nav { position: fixed; top: 0; right: 0; height: 100vh; width: 60px; background-color: #212121; color: #fff; z-index: 2147483647; display: flex; flex-direction: column; justify-content: space-between; align-items: center; box-shadow: -4px 0px 8px rgba(0,0,0,0.2); transition: all 0.3s ease-in-out; transform: translateX(100%); overflow: hidden; }
            .sidebar-nav.visible { transform: translateX(0); }
            .sidebar-view { display: none; flex-direction: column; justify-content: flex-start; align-items: center; width: 100%; height: 100%; }
            .sidebar-view.active { display: flex; }
            #main-view { justify-content: space-between; padding: 10px 0; }
            .header-container { display: flex; flex-direction: column; align-items: center; width: 100%; }
            .logo-container { margin-bottom: 20px; cursor: pointer; } .logo-container img { width: 30px; height: auto; }
            .tasks-section { display: flex; flex-direction: column; align-items: center; margin-bottom: 20px; width: 100%; }
            .tasks-label { font-size: 10px; font-weight: 600; color: #a0a0a0; margin-bottom: 5px; }
            .tasks-button { position: relative; display: flex; justifyContent: center; alignItems: center; width: 40px; height: 40px; borderRadius: 50%; backgroundColor: #555; cursor: pointer; transition: backgroundColor 0.2s; }
            .task-count { position: absolute; top: -5px; right: -5px; backgroundColor: #ff3b30; color: #fff; fontSize: 10px; fontWeight: bold; padding: 2px 5px; borderRadius: 10px; }
            .actions-section { display: flex; flex-direction: column; align-items: center; gap: 8px; }
            .add-task-btn, .toggle-tasks-btn, .refresh-tasks-btn { display: flex; justifyContent: center; alignItems: center; width: 40px; height: 40px; cursor: pointer; border-radius: 4px; transition: background-color 0.2s; }
            .add-task-btn:hover, .toggle-tasks-btn:hover, .refresh-tasks-btn:hover { background-color: #444; }
            .chevron-container { width: 100%; display: flex; justifyContent: center; alignItems: center; padding: 10px 0; cursor: pointer; borderTop: 1px solid #444; }
            /* Task List & Details Styles */
            .task-list-view { width: 400px; }
            .task-list-header { display: flex; alignItems: center; justifyContent: center; position: relative; width: 100%; padding: 10px 0; borderBottom: 1px solid #444; flexShrink: 0; }
            .back-arrow { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #a0a0a0; }
            .back-arrow:hover { color: #fff; }
            .task-list-title { margin: 0; fontSize: 14px; fontWeight: 600; }
            .task-list-controls { padding: 10px; display: flex; flex-direction: column; gap: 10px; flex-shrink: 0; }
            .search-input { width: 100%; padding: 8px 10px; box-sizing: border-box; border: 1px solid #444; background-color: #333; color: #fff; border-radius: 4px; }
            .status-filters { display: flex; gap: 5px; flex-wrap: wrap; }
            .filter-btn { padding: 5px 10px; font-size: 11px; border: 1px solid #444; border-radius: 20px; cursor: pointer; transition: background-color 0.2s; }
            .filter-btn.active { background-color: #555; border-color: #777; }
            .filter-btn:hover { background-color: #555; }
            #task-list-container { listStyle: none; padding: 0; margin: 0; width: 100%; overflowY: auto; flexGrow: 1; }
            #task-list-container li { padding: 10px 15px; borderBottom: 1px solid #333; font-size: 12px; whiteSpace: nowrap; overflow: hidden; textOverflow: ellipsis; cursor: pointer; }
            #task-list-container li:hover { backgroundColor: #3a3a3a; }
            .task-list-item { display: flex; align-items: center; justify-content: space-between; }
            .task-list-item-content { flex-grow: 1; overflow: hidden; }
            .task-list-item-title { font-weight: bold; font-size: 13px; margin-bottom: 2px; }
            .task-list-item-time { font-size: 10px; color: #a0a0a0; }
            .task-list-item-status-marker { min-width: 10px; min-height: 10px; border-radius: 50%; border: 1px solid #fff; margin-left: 10px; }
            .task-details-content { width: 100%; padding: 15px; overflowY: auto; flexGrow: 1; boxSizing: border-box; }
            .task-description-details { fontSize: 14px; margin: 0 0 15px 0; whiteSpace: pre-wrap; wordWrap: break-word; }
            .screenshot-container-details { marginBottom: 15px; }
            .screenshot-container-details img { max-width: 100%; border-radius: 4px; border: 1px solid #444; margin-bottom: 10px; display: block; }
            .screenshot-thumbnails { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px; }
            .screenshot-thumbnails img { width: 100px; height: auto; border: 2px solid transparent; border-radius: 4px; cursor: pointer; transition: border-color 0.2s; }
            .screenshot-thumbnails img:hover, .screenshot-thumbnails img.active { border-color: #fff; }
            .tech-details h5 { margin: 0 0 10px 0; fontSize: 13px; color: #a0a0a0; }
            .tech-info-list { listStyle: none; padding: 0; margin: 0; fontSize: 12px; color: #ccc; }
            .tech-info-list li { marginBottom: 5px; }
            /* Status View Styles */
            .status-message { color: #a0a0a0; fontSize: 14px; textAlign: center; padding: 20px; alignSelf: center; justifySelf: center; margin: auto; }
        `;
        sidebarShadowRoot.appendChild(sidebarStyleSheet);
        sidebarShadowRoot.innerHTML += sidebarHTML;

        // New code to create the hover card element and append it to the body
        hoverCard = document.createElement('div');
        hoverCard.id = 'ozee-task-hover-card';
        document.body.appendChild(hoverCard);

        const hoverCardHTML = `
            <div class="ozee-hover-card-header">
                <span class="ozee-hover-card-id"></span>
            </div>
            <div class="ozee-hover-card-body">
                <span class="ozee-hover-card-desc"></span>
            </div>
        `;
        hoverCard.innerHTML = hoverCardHTML;

        // Event Listeners
        sidebarShadowRoot.getElementById('add-task-btn').addEventListener('click', startScreenshotSelection);
        sidebarShadowRoot.getElementById('hide-sidebar-btn').addEventListener('click', () => toggleSidebar(false));
        sidebarShadowRoot.getElementById('show-tasks-btn').addEventListener('click', () => switchView('task-list-view'));
        sidebarShadowRoot.getElementById('back-to-main-btn').addEventListener('click', () => switchView('main-view'));
        sidebarShadowRoot.getElementById('back-to-list-btn').addEventListener('click', () => switchView('task-list-view'));

        // New toggle button event listener
        sidebarShadowRoot.getElementById('toggle-tasks-btn').addEventListener('click', toggleTaskVisibility);

        // NEW: Refresh button event listener
        sidebarShadowRoot.getElementById('refresh-tasks-btn').addEventListener('click', refreshTasks);

        // NEW: Search and filter event listeners
        sidebarShadowRoot.getElementById('search-input').addEventListener('input', renderTaskList);
        sidebarShadowRoot.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', handleStatusFilter);
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
                display: flex; alignItems: center; justifyContent: center;
                font-size: 2em; font-weight: bold; cursor: pointer;
                boxShadow: 0 4px 8px rgba(0, 0, 0, 0.2); zIndex: 2147483646;
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
    async function showFeedbackPopup(screenshotDataUrl, rect) {
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

            // Fetch the HTML and CSS for the popup
            const popupHtmlUrl = chrome.runtime.getURL('popup.html');
            const popupCssUrl = chrome.runtime.getURL('popup.css');

            const [htmlResponse, cssResponse] = await Promise.all([
                fetch(popupHtmlUrl),
                fetch(popupCssUrl)
            ]);
            const popupHTML = await htmlResponse.text();
            const popupCSS = await cssResponse.text();

            const popupStyleSheet = document.createElement('style');
            popupStyleSheet.textContent = popupCSS;

            popupShadowRoot.appendChild(popupStyleSheet);
            popupShadowRoot.innerHTML += popupHTML;

            const mainContainer = popupShadowRoot.querySelector('.ozee-popup-main-container');
            const headerHandle = popupShadowRoot.querySelector('.ozee-popup-header-handle');
            let isDragging = false;
            let initialMouseX, initialMouseY;
            let initialPopupX, initialPopupY;

            headerHandle.addEventListener("mousedown", (e) => {
                initialMouseX = e.clientX;
                initialMouseY = e.clientY;

                // Use getBoundingClientRect for more accurate positioning
                const rect = mainContainer.getBoundingClientRect();
                initialPopupX = rect.left;
                initialPopupY = rect.top;

                // Remove the initial transform to prevent jitter
                mainContainer.style.transform = 'none';

                isDragging = true;
            });

            document.addEventListener("mousemove", (e) => {
                if (isDragging) {
                    e.preventDefault();
                    const dx = e.clientX - initialMouseX;
                    const dy = e.clientY - initialMouseY;

                    mainContainer.style.left = `${initialPopupX + dx}px`;
                    mainContainer.style.top = `${initialPopupY + dy}px`;
                }
            });

            document.addEventListener("mouseup", () => {
                isDragging = false;
            });

            popupShadowRoot.getElementById('close-popup-btn').addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                popupShadowContainer.style.display = 'none';
                toggleSidebar(true);
            });

            const descriptionInput = popupShadowRoot.getElementById('description-input');
            const createTaskButton = popupShadowRoot.getElementById('createTaskButton');
            const characterCounter = popupShadowRoot.querySelector('.ozee-character-counter');
            const shortMessageInfo = popupShadowRoot.getElementById('short-message-info');
            const MIN_DESCRIPTION_LENGTH = 10;
            const MAX_DESCRIPTION_LENGTH = 250;

            descriptionInput.addEventListener('input', () => {
                const textLength = descriptionInput.value.trim().length;
                characterCounter.textContent = `${textLength}/${MAX_DESCRIPTION_LENGTH}`;

                if (textLength >= MIN_DESCRIPTION_LENGTH) {
                    createTaskButton.disabled = false;
                    shortMessageInfo.style.display = 'none';
                } else {
                    createTaskButton.disabled = true;
                }
            });

            createTaskButton.addEventListener('click', () => {
                if (descriptionInput.value.trim().length < MIN_DESCRIPTION_LENGTH) {
                    shortMessageInfo.style.display = 'block';
                } else {
                    shortMessageInfo.style.display = 'none';
                    // The rest of the task creation logic
                    handleTaskCreation({
                        description: descriptionInput.value.trim(),
                        screenshot: currentTaskData.screenshot,
                        pageUrl: window.location.href,
                        rect: currentTaskData.rect
                    });
                }
            });
        }

        const screenshotPreviewSpan = popupShadowRoot.querySelector('.ozee-screenshot-inner');
        if (screenshotPreviewSpan) {
            screenshotPreviewSpan.style.backgroundImage = `url('${screenshotDataUrl}')`;
        }

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
            sidebarNav.style.width = '400px';
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

        const mainScreenshotContainer = detailsView.querySelector('#ozee-screenshots-container');
        mainScreenshotContainer.innerHTML = '';

        const thumbnailsContainer = document.createElement('div');
        thumbnailsContainer.className = 'screenshot-thumbnails';
        mainScreenshotContainer.appendChild(thumbnailsContainer);

        const fullImageContainer = document.createElement('div');
        mainScreenshotContainer.appendChild(fullImageContainer);

        let activeThumbnail = null;

        if (report.screenshot && Array.isArray(report.screenshot) && report.screenshot.length > 0) {
            const firstScreenshot = report.screenshot[0];
            const mainImg = document.createElement('img');
            mainImg.src = firstScreenshot.path_url;
            mainImg.alt = 'OZee Bug Report Screenshot';
            fullImageContainer.appendChild(mainImg);

            report.screenshot.forEach(screenshot => {
                const thumbnailImg = document.createElement('img');
                thumbnailImg.src = screenshot.thumbnail_url || screenshot.path_url;
                thumbnailImg.alt = 'Thumbnail';
                thumbnailsContainer.appendChild(thumbnailImg);

                thumbnailImg.addEventListener('click', () => {
                    if (activeThumbnail) activeThumbnail.classList.remove('active');
                    thumbnailImg.classList.add('active');
                    activeThumbnail = thumbnailImg;
                    mainImg.src = screenshot.path_url;
                });
            });
            // Set the first thumbnail as active by default
            if (thumbnailsContainer.firstChild) {
                thumbnailsContainer.firstChild.classList.add('active');
                activeThumbnail = thumbnailsContainer.firstChild;
            }
        }
        else {
            const noScreenshotMessage = document.createElement('p');
            noScreenshotMessage.textContent = 'No screenshots available.';
            mainScreenshotContainer.appendChild(noScreenshotMessage);
        }

        const techInfoList = detailsView.querySelector('.tech-info-list');
        techInfoList.innerHTML = '';

        const userAgent = navigator.userAgent;
        let browser = "Unknown";
        if (userAgent.includes("Firefox")) browser = "Firefox";
        else if (userAgent.includes("Chrome")) browser = "Chrome";
        else if (userAgent.includes("Safari")) browser = "Safari";

        const os = navigator.platform;

        const techData = {
            "Browser": `${browser}`,
            "OS": os,
            "Screen Resolution": `${window.screen.width} x ${window.screen.height}`,
            "URL": report.pageUrl || window.location.href,
            "Created": report.created_at ? new Date(report.created_at).toLocaleString() : 'N/A',
            "Updated": report.updated_at ? new Date(report.updated_at).toLocaleString() : 'N/A'
        };

        for (const [key, value] of Object.entries(techData)) {
            const li = document.createElement('li');
            li.innerHTML = `<strong>${key}:</strong> ${value}`;
            techInfoList.appendChild(li);
        }
    }

    /**
     * Renders the list of tasks in the sidebar based on current filters.
     */
    function renderTaskList() {
        const listContainer = sidebarShadowRoot.getElementById('task-list-container');
        listContainer.innerHTML = '';
        const searchQuery = sidebarShadowRoot.getElementById('search-input').value.toLowerCase();

        const filteredReports = ozeeReports.filter(report => {
            const statusMatch = appState.activeStatusFilter.includes(report.status);
            const searchMatch = !searchQuery ||
                (report.description && report.description.toLowerCase().includes(searchQuery)) ||
                (report.id && report.id.toString().includes(searchQuery));
            return statusMatch && searchMatch;
        });

        if (filteredReports.length === 0) {
            const emptyItem = document.createElement('li');
            emptyItem.textContent = 'No tasks match the filters.';
            emptyItem.style.cursor = 'default';
            emptyItem.style.textAlign = 'center';
            emptyItem.style.color = '#a0a0a0';
            listContainer.appendChild(emptyItem);
            return;
        }

        filteredReports.forEach((report, index) => {
            const listItem = document.createElement('li');
            listItem.className = 'task-list-item';

            const taskContent = document.createElement('div');
            taskContent.className = 'task-list-item-content';
            const taskTitle = document.createElement('div');
            taskTitle.className = 'task-list-item-title';
            taskTitle.textContent = `#${report.id}: ${report.description || 'No description'}`;
            const taskTime = document.createElement('div');
            taskTime.className = 'task-list-item-time';
            taskTime.textContent = `Created: ${report.created_at ? new Date(report.created_at).toLocaleString() : 'N/A'}`;

            taskContent.appendChild(taskTitle);
            taskContent.appendChild(taskTime);

            const statusMarker = document.createElement('span');
            statusMarker.className = 'task-list-item-status-marker';
            statusMarker.style.backgroundColor = STATUS_COLORS[report.status] || '#ccc';

            listItem.appendChild(taskContent);
            listItem.appendChild(statusMarker);

            listItem.title = report.description;
            listItem.addEventListener('click', () => {
                showTaskDetails(report);
            });
            listContainer.appendChild(listItem);
        });
    }

    // New function to handle status filter button clicks
    function handleStatusFilter(event) {
        const status = event.target.dataset.status;
        const button = event.target;

        if (appState.activeStatusFilter.includes(status)) {
            appState.activeStatusFilter = appState.activeStatusFilter.filter(s => s !== status);
            button.classList.remove('active');
        } else {
            appState.activeStatusFilter.push(status);
            button.classList.add('active');
        }

        renderTaskList();
    }


    /**
     * Central function to handle the creation of a task.
     * @param {object} taskData - The complete data for the new task.
     */
    function handleTaskCreation(taskData) {
        ozeeReports.push(taskData);
        renderMarkers();
        updateTaskCount();
        popupShadowContainer.style.display = 'none';
        toggleSidebar(true);

        chrome.runtime.sendMessage({
            action: "createTask",
            payload: {
                description: taskData.description,
                screenshot: taskData.screenshot,
                pageUrl: window.location.href,
                rect: taskData.rect
            }
        }, (response) => {
            if (response && response.status === "success") {
                console.log("Task successfully saved to backend:", response.data);
                const backendTask = response.data;
                const localTask = ozeeReports.find(r => r.rect === taskData.rect && r.description === taskData.description);
                if (localTask) {
                    localTask.id = backendTask.id;
                    localTask.screenshot = backendTask.screenshot;
                }
            } else {
                console.error("Failed to save task to backend:", response ? response.message : "No response from background script.");
                ozeeReports.pop();
                renderMarkers();
                updateTaskCount();
            }
        });
    }

    // New state for task filtering
    let isShowingAllTasks = false;

    // Mapping of statuses to colors
    const STATUS_COLORS = {
        'To Do': '#4a90e2',      // Blue
        'In Progress': '#f5a623', // Orange
        'Paused': '#9b9b9b',     // Light Gray
        'Done': '#7ed321',      // Green
        'Blocked': '#d0021b',    // Red
        'Archived': '#5d5d5d'    // Dark Gray
    };

    /**
     * Clears all existing markers and redraws them from the ozeeReports array, applying filters.
     */
    function renderMarkers() {
        const existingMarkers = document.querySelectorAll('.ozee-task-marker');
        existingMarkers.forEach(marker => marker.remove());

        const filteredReports = isShowingAllTasks ? ozeeReports : ozeeReports.filter(report =>
            report.status === 'To Do' || report.status === 'In Progress'
        );

        filteredReports.forEach((report, index) => {
            if (report.rect) {
                const status = report.status || 'To Do';
                addPin(report.rect, index, status);
            }
        });
    }

    /**
     * Adds a single, numbered, and CLICKABLE visual pin to the page.
     * @param {object} rect - The {x, y} coordinates for the pin.
     * @param {number} index - The index of the report in the ozeeReports array.
     * @param {string} status - The status of the task for color-coding.
     */
    function addPin(rect, index, status) {
        const pinElement = document.createElement('div');
        pinElement.className = `ozee-task-marker status-${status.toLowerCase().replace(' ', '-')}`;
        pinElement.textContent = index + 1;

        Object.assign(pinElement.style, {
            position: 'absolute',
            top: `${rect.y}px`,
            left: `${rect.x}px`,
            width: '30px',
            height: '30px',
            backgroundColor: STATUS_COLORS[status],
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

        const cardId = hoverCard.querySelector('.ozee-hover-card-id');
        const cardDesc = hoverCard.querySelector('.ozee-hover-card-desc');

        pinElement.addEventListener('mouseover', (e) => {
            const reportData = ozeeReports[index];
            if (reportData) {
                cardId.textContent = `#${index + 1} (${reportData.status || 'To Do'})`;
                cardDesc.textContent = reportData.description || 'No description provided.';
                hoverCard.style.top = `${e.clientY + 15}px`;
                hoverCard.style.left = `${e.clientX + 15}px`;
                hoverCard.style.display = 'block';
            }
        });

        pinElement.addEventListener('mouseout', () => {
            hoverCard.style.display = 'none';
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
     * Toggles the visibility of all markers.
     */
    function toggleTaskVisibility() {
        isShowingAllTasks = !isShowingAllTasks;
        const toggleBtn = sidebarShadowRoot.getElementById('toggle-tasks-btn');
        const showAllIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        const hideAllIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye-off"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-3.69 5.06M2 2l20 20"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;

        if (isShowingAllTasks) {
            toggleBtn.title = "Hide archived tasks";
            toggleBtn.innerHTML = hideAllIcon;
        } else {
            toggleBtn.title = "Show all tasks";
            toggleBtn.innerHTML = showAllIcon;
        }

        renderMarkers();
    }

    // NEW: Function to request a refresh from background.js
    function refreshTasks() {
        switchView('loading-view');
        chrome.runtime.sendMessage({ action: "refreshTasks", payload: { pageUrl: window.location.href } }, (response) => {
            if (response && response.status === "success") {
                ozeeReports = response.data.map(task => ({
                    ...task,
                    // Temporarily assign a random status for demonstration
                    status: ['To Do', 'In Progress', 'Done', 'Archived', 'Blocked'][Math.floor(Math.random() * 5)]
                }));
                renderMarkers();
                updateTaskCount();
                switchView('main-view');
            } else {
                console.error("Failed to refresh tasks:", response ? response.message : "No response");
                switchView('error-view', response.message || 'Failed to refresh tasks.');
            }
        });
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

    // REFACTORED: Now gets tasks from background.js instead of making a direct fetch call
    function getTasksFromBackground() {
        chrome.runtime.sendMessage({
            action: "getTasks",
            payload: { pageUrl: window.location.href }
        }, (response) => {
            if (response && response.status === "success" && Array.isArray(response.data)) {
                ozeeReports = response.data.map(task => ({
                    ...task,
                    // Temporarily assign a random status for demonstration
                    status: ['To Do', 'In Progress', 'Done', 'Archived', 'Blocked'][Math.floor(Math.random() * 5)]
                }));
                renderMarkers();
                updateTaskCount();
                switchView('main-view');
            } else {
                console.error("Failed to fetch tasks for this URL:", response ? response.message : "No response");
                switchView('error-view', response.message || 'Failed to fetch tasks.');
            }
            tasksFetched = true;
        });
    }


    // --- STATE & FLOW FUNCTIONS ---

    /**
     * REFACTORED: Handles sidebar visibility and project status check.
     */
    function toggleSidebar(forceVisible = null) {
        const shouldBeVisible = forceVisible !== null ? forceVisible : !appState.sidebarVisible;
        const sidebar = sidebarShadowRoot.querySelector('#nav');

        if (shouldBeVisible) {
            sidebar.classList.add('visible');
            appState.sidebarVisible = true;

            // Only show loading view and get tasks if not already fetched.
            if (!tasksFetched) {
                switchView('loading-view');
                chrome.runtime.sendMessage({
                    action: "checkProjectStatus",
                    payload: { pageUrl: window.location.href }
                }, (response) => {
                    if (response && response.status === "success") {
                        if (response.projectStatus === "active") {
                            getTasksFromBackground();
                        } else {
                            switchView('error-view', 'This project is inactive.');
                        }
                    } else {
                        switchView('error-view', response.message || 'Failed to check project status.');
                    }
                });
            } else {
                switchView('main-view');
            }

            createMarker();
            if(markerShadowContainer) markerShadowContainer.style.display = 'block';

        } else {
            sidebar.classList.remove('visible');
            appState.sidebarVisible = false;
            switchView('main-view');
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
                position: 'fixed',
                top: '0',
                left: '0',
                width: '100vw',
                height: '100vh',
                backgroundColor: 'rgba(0, 0, 0, 0.5)',
                cursor: 'crosshair',
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
            captureAndCropScreenshot(rect, pageRect);

        } else {
            const clickRect = {
                x: e.pageX,
                y: e.pageY
            };
            captureAndShowFullPageScreenshot(clickRect);
        }
    }

    function captureAndCropScreenshot(rect, pageRect) {
        hideMarkers(); // Hide markers before taking screenshot
        chrome.runtime.sendMessage({ action: "captureScreenshot" }, (response) => {
            if (response && response.dataUrl) {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const dpr = window.devicePixelRatio || 1;
                    canvas.width = rect.width * dpr;
                    canvas.height = rect.height * dpr;
                    ctx.drawImage(img, rect.x * dpr, rect.y * dpr, rect.width * dpr, rect.height * dpr, 0, 0, canvas.width, canvas.height);
                    showFeedbackPopup(canvas.toDataURL('image/png'), pageRect);
                };
                img.src = response.dataUrl;
            } else {
                console.error("Error capturing screenshot:", response.error);
                showMarkers(); // Show markers again if screenshot fails
                toggleSidebar(true);
            }
        });
    }

    function captureAndShowFullPageScreenshot(rect) {
        hideMarkers(); // Hide markers before taking screenshot
        chrome.runtime.sendMessage({ action: "captureScreenshot" }, (response) => {
            if (response && response.dataUrl) {
                showFeedbackPopup(response.dataUrl, rect);
            } else {
                console.error("Error capturing screenshot:", response.error);
                showMarkers(); // Show markers again if screenshot fails
                toggleSidebar(true);
            }
        });
    }

    // --- MESSAGE LISTENER ---
    chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
        if (request.action === "toggleSidebar") {
            toggleSidebar();
        }
        // Return true to indicate you wish to send a response asynchronously
        // This is important for chrome.runtime.onMessage listeners.
        return true;
    });

})();
