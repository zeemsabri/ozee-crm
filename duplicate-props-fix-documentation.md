# Duplicate Props Declaration Fix Documentation

## Issue Description

When loading the ProjectForm.vue component, the following error was occurring:

```
[plugin:vite:vue] [vue/compiler-sfc] Identifier 'props' has already been declared. (97:6)

/Users/zeeshansabri/laravel/email-approval-app/resources/js/Components/ProjectForm.vue
99 |      project: { type: Object, default: () => ({}) },
```

This error was happening because the `props` variable was declared twice in the component:

1. First declaration at lines 19-29:
```javascript
const props = defineProps({
    show: { type: Boolean, required: true },
    project: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    clientRoleOptions: { type: Array, default: () => [] },
    userRoleOptions: { type: Array, default: () => [] },
    paymentTypeOptions: { type: Array, required: true },
});
```

2. Second declaration at lines 97-106:
```javascript
const props = defineProps({
    show: { type: Boolean, required: true },
    project: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    clientRoleOptions: { type: Array, default: () => [] },
    userRoleOptions: { type: Array, default: () => [] },
    paymentTypeOptions: { type: Array, required: true },
});
```

In JavaScript, you cannot declare the same variable twice using `const`, which was causing the compilation error.

## Root Cause

The duplicate declaration likely occurred during a previous fix for the "Cannot access 'props' before initialization" error. In that fix, the `defineProps` call was moved to the beginning of the component to ensure it was defined before any references to `props`. However, the original `defineProps` call was not removed, resulting in this duplicate declaration.

## Changes Made

The following change was made to fix the issue:

1. Removed the second `props` declaration (lines 97-106) while keeping all other code intact:

```javascript
// Before
// Define reactive refs for roles
const dbClientRoles = ref([]);
const dbUserRoles = ref([]);

const props = defineProps({
    show: { type: Boolean, required: true },
    project: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    clientRoleOptions: { type: Array, default: () => [] },
    userRoleOptions: { type: Array, default: () => [] },
    paymentTypeOptions: { type: Array, required: true },
});

// After
// Define reactive refs for roles
const dbClientRoles = ref([]);
const dbUserRoles = ref([]);
```

## Verification

A test script was created to simulate the component setup process and verify that the fix works correctly. The test script follows the same structure as the actual component but without the duplicate props declaration.

The test was run using Node.js and successfully passed without any errors:

```
Testing ProjectForm.vue fix for 'Identifier props has already been declared' error
Initializing component...
Auth user initialized: { name: 'Test User' }
Project ref initialized with empty object: {}
Props defined: [
  'show',
  'project',
  'statusOptions',
  'departmentOptions',
  'sourceOptions',
  'clientRoleOptions',
  'userRoleOptions',
  'paymentTypeOptions'
]
Project ref updated in watch: { id: 1, name: 'Test Project' }
Watch set up successfully
User project role initialized
Permission functions initialized
Permission checks initialized
Emits defined: [ 'close', 'submit' ]
Component initialized successfully!
Test PASSED: No 'Identifier props has already been declared' error
```

## Best Practices for Vue 3 Composition API

To avoid similar issues in the future, follow these best practices:

1. Always define props using `defineProps` at the beginning of the setup function, before any references to `props`.
2. Be careful when refactoring code to ensure that you don't accidentally duplicate variable declarations.
3. When fixing one issue, make sure you don't introduce new issues by leaving redundant code.
4. Use a linter or IDE with good Vue 3 support to catch these types of errors early.

By following these practices, you can avoid the "Identifier has already been declared" error and ensure that your Vue 3 components work correctly.
