# SYNC_RELATIONSHIP Action Type

This document describes the new `SYNC_RELATIONSHIP` action type added to the automation workflow system.

## Overview

The `SYNC_RELATIONSHIP` action allows you to sync many-to-many relationships (BelongsToMany, MorphToMany) on existing records through workflow automation. This is particularly useful for managing relationships like categories, tags, or other pivot table associations.

## Features Added

### 1. CategorySet Model in Automation Schema
- Added `CategorySet` model to the automation schema seed models
- Both `Category` and `CategorySet` models now implement `CreatableViaWorkflow` interface
- Added field metadata for better UI labels and descriptions

### 2. New Action Type: SYNC_RELATIONSHIP
- **Action Type**: `SYNC_RELATIONSHIP`
- **Label**: "Sync Relationship"

### 3. Configuration Options

The SYNC_RELATIONSHIP action requires the following configuration:

- **Target Model**: The model that contains the relationship
- **Record ID**: The ID of the record to update (supports tokens like `{{trigger.task.id}}`)
- **Relationship**: The name of the relationship method (filtered to many-to-many only)
- **Sync Mode**: How to handle the relationship sync
  - `sync`: Replace all existing relationships with new ones
  - `attach`: Add new relationships without removing existing ones  
  - `detach`: Remove specified relationships
- **Related IDs**: Comma-separated list of IDs to sync/attach/detach (supports tokens)

### 4. UI Enhancements

The ActionStep.vue component now includes:
- Dropdown showing only BelongsToMany/MorphToMany relationships
- Sync mode selector with clear descriptions
- Support for token insertion in all relevant fields
- Helpful descriptions for each configuration option

## Usage Examples

### Example 1: Sync Categories on Task Creation
When a task is created, automatically sync it with specific categories:

```
Action Type: SYNC_RELATIONSHIP
Target Model: Task
Record ID: {{trigger.task.id}}
Relationship: categories
Sync Mode: sync
Related IDs: 1,2,3
```

### Example 2: Add Category from AI Step
After an AI step determines categories, attach them to a task:

```
Action Type: SYNC_RELATIONSHIP
Target Model: Task  
Record ID: {{trigger.task.id}}
Relationship: categories
Sync Mode: attach
Related IDs: {{step_2.category_ids}}
```

## Technical Implementation

### Backend Components

1. **SyncRelationshipStepHandler**: Handles the relationship sync logic
   - Validates relationship exists and is many-to-many
   - Supports template token resolution  
   - Provides detailed logging and error handling

2. **AutomationSchemaController**: Updated to include Category/CategorySet models
3. **WorkflowEngineService**: Registered the new handler as `ACTION_SYNC_RELATIONSHIP`

### Model Changes

1. **Category Model**:
   - Implements `CreatableViaWorkflow`
   - Added field metadata for workflow UI

2. **CategorySet Model**:
   - Implements `CreatableViaWorkflow` 
   - Added field metadata for workflow UI

### Frontend Changes

1. **ActionStep.vue**: Added complete UI for SYNC_RELATIONSHIP configuration
   - Model dropdown
   - Record ID input with token support
   - Relationship dropdown (filtered to many-to-many)
   - Sync mode selector
   - Related IDs input with token support

## Error Handling

The system provides comprehensive error handling for:
- Missing required configuration
- Invalid model classes
- Non-existent records
- Invalid relationships
- Non-many-to-many relationship types
- Template resolution errors

## Benefits

1. **Complete Category Management**: Now both Category and CategorySet models are available in workflows
2. **Relationship Sync Support**: Full support for syncing many-to-many relationships
3. **Flexible Token Support**: Use data from triggers and previous steps
4. **Multiple Sync Modes**: Choose how to handle existing relationships
5. **Type Safety**: Only allows appropriate relationship types
6. **Clear UI**: Intuitive interface with helpful descriptions

## Future Enhancements

Potential future improvements could include:
- Support for other relationship types (HasMany, etc.)
- Bulk relationship operations
- Conditional relationship syncing
- Relationship-specific validation rules