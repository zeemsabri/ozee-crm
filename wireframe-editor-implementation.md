# Wireframe Editor Implementation

This document provides an overview of the wireframe editor implementation in the Email Approval App.

## Overview

The wireframe editor is a feature that allows users to create, edit, and manage wireframes for projects. It includes a versioning system, component management, and integration with the existing project view.

## Features

- **Wireframe Editor**: A drag-and-drop editor for creating wireframes
- **Versioning System**: Support for draft and published versions of wireframes
- **Component Management**: Ability to create, edit, and use custom components
- **Project Integration**: Seamless integration with the project view

## Implementation Details

### Database Structure

The implementation includes the following database tables:

1. **wireframes**: Stores wireframe metadata
   - `id`: Primary key
   - `project_id`: Foreign key to projects table
   - `name`: Wireframe name
   - `created_at`, `updated_at`: Timestamps

2. **wireframe_versions**: Stores wireframe version data
   - `id`: Primary key
   - `wireframe_id`: Foreign key to wireframes table
   - `version_number`: Version number
   - `data`: JSON data representing the wireframe state
   - `status`: Enum ('draft', 'published')
   - `created_at`, `updated_at`: Timestamps

3. **components**: Stores component definitions
   - `id`: Primary key
   - `name`: Component name
   - `type`: Component type
   - `definition`: JSON data representing the component definition
   - `icon_id`: Foreign key to icons table (nullable)
   - `created_at`, `updated_at`: Timestamps

4. **icons**: Stores SVG icons for components
   - `id`: Primary key
   - `name`: Icon name
   - `svg_content`: SVG content as text
   - `created_at`, `updated_at`: Timestamps

### Models

The implementation includes the following models:

1. **Wireframe**: Represents a wireframe
   - Relationships: belongs to Project, has many WireframeVersions
   - Methods: latestVersion(), latestDraftVersion(), latestPublishedVersion()

2. **WireframeVersion**: Represents a version of a wireframe
   - Relationships: belongs to Wireframe
   - Methods: isDraft(), isPublished(), publish()

3. **Component**: Represents a component definition
   - Relationships: belongs to Icon
   - Methods: validateDefinition()

4. **Icon**: Represents an SVG icon
   - Relationships: has many Components
   - Methods: validateSvgContent(), sanitizeSvgContent()

### API Endpoints

The implementation includes the following API endpoints:

1. **Wireframe Endpoints**:
   - `GET /api/projects/{projectId}/wireframes`: List wireframes for a project
   - `GET /api/projects/{projectId}/wireframes/{id}`: Get a specific wireframe
   - `POST /api/projects/{projectId}/wireframes`: Create a new wireframe
   - `PUT /api/projects/{projectId}/wireframes/{id}`: Update a wireframe
   - `POST /api/projects/{projectId}/wireframes/{id}/publish`: Publish a wireframe
   - `POST /api/projects/{projectId}/wireframes/{id}/versions`: Create a new version
   - `DELETE /api/projects/{projectId}/wireframes/{id}`: Delete a wireframe
   - `GET /api/projects/{projectId}/wireframes/{id}/logs`: Get activity logs for a wireframe

2. **Component Endpoints**:
   - `GET /api/components`: List all components
   - `GET /api/components/{id}`: Get a specific component
   - `POST /api/components`: Create a new component
   - `PUT /api/components/{id}`: Update a component
   - `DELETE /api/components/{id}`: Delete a component

### Frontend Components

The implementation includes the following frontend components:

1. **Wireframe.vue**: The main wireframe editor component
   - Features: drag-and-drop, resizing, component palette, property editing
   - Actions: save, publish, create new version, import/export

2. **Project/Show.vue**: Updated to include wireframe functionality
   - Added "Wireframe" button to replace "Send Magic Link" button
   - Implemented toggling between project and wireframe views

### Web Routes

The implementation includes the following web routes:

1. **Project Wireframe Routes**:
   - `GET /projects/{project}/wireframe`: Open the wireframe editor for a project
   - `GET /projects/{project}/wireframe/{wireframe}`: Open a specific wireframe
   - `GET /projects/{project}/wireframe/{wireframe}/version/{version}`: Open a specific version of a wireframe

## Testing

The implementation includes test scripts for API endpoints:

1. **test-wireframe-api.php**: Tests wireframe API endpoints
   - Creating, reading, updating, and deleting wireframes
   - Publishing wireframes and creating new versions
   - Getting wireframe logs

2. **test-component-api.php**: Tests component API endpoints
   - Creating, reading, updating, and deleting components
   - Creating components with icons
   - Testing validation for component creation

## Usage

### Creating a Wireframe

1. Navigate to a project
2. Click the "Wireframe" button
3. Use the component palette to add components to the canvas
4. Click "Save Draft" to save the wireframe

### Publishing a Wireframe

1. Open a wireframe
2. Make changes as needed
3. Click "Publish" to publish the wireframe

### Creating a New Version

1. Open a published wireframe
2. Click "New Version" to create a new draft version
3. Make changes as needed
4. Click "Save Draft" to save the new version

### Adding Custom Components

1. Open the wireframe editor
2. Use the component upload form in the left sidebar
3. Fill in the component details (name, type, definition, optional icon)
4. Click "Upload Component" to add the component to the palette

## Future Improvements

Potential improvements for the wireframe editor:

1. **Component Versioning**: Add versioning for components
2. **Collaborative Editing**: Allow multiple users to edit a wireframe simultaneously
3. **Export to HTML/CSS**: Add functionality to export wireframes as HTML/CSS
4. **Templates**: Add support for wireframe templates
5. **Component Library**: Create a library of pre-built components
