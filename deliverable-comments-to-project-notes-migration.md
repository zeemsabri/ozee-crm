# Deliverable Comments to Project Notes Migration

## Overview
This document describes the changes made to replace the `DeliverableComment` model with the `ProjectNote` model, which has morph columns to link to both user and client models.

## Changes Made

### 1. Updated Model Relationships

#### Deliverable Model
- Changed the `comments()` relationship from `hasMany(DeliverableComment::class)` to `morphMany(ProjectNote::class, 'noteable')`.
- Added the `ProjectNote` import.

#### Client Model
- Added a new `notes()` relationship using `morphMany(ProjectNote::class, 'creator')`.
- Marked the existing `deliverableComments()` relationship as deprecated.
- Added the `ProjectNote` import.

### 2. Added Context Field to ProjectNote

- Created a migration to add a `context` field to the `project_notes` table.
- Added `context` to the `$fillable` array in the `ProjectNote` model.

### 3. Updated Controller Logic

#### ProjectClientAction Controller
- Updated the `addDeliverableComment` method to create a `ProjectNote` instead of a `DeliverableComment`.
- Changed the field mappings:
  - `comment_text` -> `content`
  - Added `type` = 'comment'
  - Added `noteable_id` and `noteable_type` for the polymorphic relationship to Deliverable
  - Added `creator_id` and `creator_type` for the polymorphic relationship to Client
  - Added `project_id` from the deliverable
- Commented out the `DeliverableComment` import.

#### ProjectDeliverableAction Controller
- Updated the imports to include `ProjectNote` instead of `DeliverableComment`.
- Modified the `addComment` method to create a `ProjectNote` instead of a `DeliverableComment`.
- Changed the field mappings:
  - `comment_text` -> `content`
  - Added `type` = 'comment'
  - Added `noteable_id` and `noteable_type` for the polymorphic relationship to Deliverable
  - Added `creator_id` and `creator_type` for the polymorphic relationship to User
  - Added `project_id` from the deliverable
- Updated the response in the `addComment` method to load the `creator` relationship instead of the `teamMember` relationship.
- Updated the eager loading in the `show` method to load `comments.creator` instead of `comments.client` and `comments.teamMember`.

### 4. Updated Frontend Component

#### DeliverableViewerModal.vue
- Updated the comment display to use `ProjectNote` fields:
  - `comment.client?.name` -> `comment.creator_name`
  - `comment.comment_text` -> `comment.content`
- Updated the comment to indicate we're using `ProjectNote` model.

### 5. Deprecated Old Code

- Marked the `DeliverableComment` model as deprecated with a comment indicating that `ProjectNote` should be used instead.
- Marked the `DeliverableCommentController` as deprecated with a comment indicating that it should be removed in a future update.

## Benefits

1. **Unified Note System**: All notes in the system (project notes, task notes, deliverable comments) now use the same `ProjectNote` model.
2. **Polymorphic Relationships**: The `ProjectNote` model can link to both users and clients through its `creator` relationship, and to various entities (projects, tasks, deliverables) through its `noteable` relationship.
3. **Improved Maintainability**: Reduces code duplication and simplifies the data model.

## Future Work

1. **Remove Deprecated Code**: In a future update, the `DeliverableComment` model, controller, and migration can be removed.
2. **Data Migration**: If there are existing deliverable comments in the database, they should be migrated to the `project_notes` table.
