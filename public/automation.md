Prompt for PhpStorm AI (Junie)
ROLE: You are a senior Vue.js developer specializing in creating complex, intuitive, and highly interactive user interfaces. You have a deep understanding of UI/UX principles.

CONTEXT: We are building the frontend for our "AI Automation Engine." The backend API (built in Laravel) provides CRUD endpoints for workflows, prompts, and workflow_steps. Our goal is to create a Single Page Application (SPA) called the "Automation Studio" where users can visually build and manage workflows. The user experience is paramount; it must be clear, visual, and forgiving for non-technical users.

YOUR TASK: Your task is to generate the foundational Vue 3 components and structure for the Automation Studio. This includes setting up the main page layout, creating the visual components for the workflow canvas, and defining the state management and API service layers.

CORE TECHNOLOGIES & LIBRARIES:

Framework: Vue 3 (Composition API with <script setup>)

State Management: Pinia

HTTP Client: Axios

Styling: Tailwind CSS

Drag & Drop: Use the vue.draggable.next library for the workflow steps.

Icons: Use the lucide-vue-next library for all icons.

Part 1: Overall Architecture & Design Principles
Before you write the code, understand the user experience we are creating:

Clarity Above All: The user must be able to understand an entire workflow at a single glance. We will use a top-to-bottom flowchart metaphor.

Guided Creation: The interface should guide the user. Instead of asking for JSON, we will use dropdowns, toggles, and visual rule builders.

Instant Feedback: The user's changes on the configuration panel should immediately reflect on the visual canvas.

Component-Driven: The entire application will be broken down into logical, reusable components.

Part 2: Folder & Component Structure
Please create the following folder and file structure inside resources/js/Pages/Automation/.

For axios reference to existing usage so it styas how we are authenticaing and use AuthenticatedLayout so we remain inside the structure 
/Automation
|-- /Api
|   |-- automationApi.js  // Axios service for all API calls
|-- /Components
|   |-- /Blocks            // Components for each step_type on the canvas
|   |   |-- TriggerBlock.vue
|   |   |-- AiPromptBlock.vue
|   |   |-- ConditionBlock.vue
|   |-- /Configuration     // Components for the RightSidebar
|   |   |-- TriggerConfig.vue
|   |   |-- AiPromptConfig.vue
|   |   |-- ConditionConfig.vue
|   |-- WorkflowCanvas.vue // The main central panel
|   |-- WorkflowList.vue   // The list of workflows on the left
|-- /Store
|   |-- workflowStore.js   // Pinia store for state management
|-- Index.vue              // The main page component
Part 3: Detailed Component Specifications
Generate the code for the following Vue components.

1. Store/workflowStore.js (Pinia Store)
   This is the single source of truth for the application state.

State:

workflows: An array to hold the list of all workflows.

activeWorkflow: The full workflow object currently being edited on the canvas.

selectedStep: The specific step object that is currently selected for configuration.

isLoading: A boolean for loading states.

Actions:

fetchWorkflows(): Action to get all workflows from the API.

fetchWorkflow(id): Action to get a single workflow and set it as activeWorkflow.

selectStep(step): Action to set the selectedStep.

addStep(stepType): Action to create a new step object in the activeWorkflow.steps array.

Getters:

getStepById(id): A getter to easily find a step within the activeWorkflow.

2. Index.vue (The Main Page)
   This component assembles the main three-panel layout.

Layout:

A main flex container.

Left Panel: The WorkflowList.vue component.

Center Panel: The WorkflowCanvas.vue component.

Right Panel: Your existing RightSidebar.vue component.

Logic:

On mount (onMounted), it should call the fetchWorkflows action from the Pinia store.

The RightSidebar's content should be dynamic. It will use a dynamic <component :is="..."> to render the correct configuration panel (TriggerConfig, AiPromptConfig, etc.) based on the selectedStep.step_type from the Pinia store.

3. Components/WorkflowCanvas.vue (The Centerpiece)
   This is where the user builds the workflow.

Props: It should get the activeWorkflow from the Pinia store.

Functionality:

It will use v-for to loop through the activeWorkflow.steps.

Inside the loop, it will use a dynamic <component :is="..."> to render the correct block component (TriggerBlock, AiPromptBlock, etc.) based on each step.step_type.

It will wrap the list of steps in a draggable component from vue.draggable.next to allow reordering.

Each block component should emit a select event when clicked, which calls the selectStep action in the Pinia store.

There should be a + button between each step to allow adding a new step. Clicking this should show a small menu of available step types and call the addStep action.

4. Block Components (e.g., Components/Blocks/AiPromptBlock.vue)
   These are the visual representations of steps on the canvas.

Props: It will receive a step object as a prop.

Display: It should be a styled "card" with an appropriate icon (lucide-vue-next) and display a summary of its configuration (e.g., the name of the prompt it uses).

Styling: When this block's step.id matches the selectedStep.id in the Pinia store, it should have a prominent blue border to indicate it's selected.

5. Configuration Components (e.g., Components/Configuration/AiPromptConfig.vue)
   These are the forms inside the RightSidebar.

Data Binding: It should get the selectedStep from the Pinia store.

Functionality: It will use v-model to bind its form inputs directly to the properties of the selectedStep object in the store (e.g., v-model="store.selectedStep.prompt_id").

Reactivity: Because it's directly modifying the Pinia store object, any changes made here will be instantly reactive and reflected in the corresponding Block component on the canvas.
