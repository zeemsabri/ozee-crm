# Task Management System Documentation

This document provides a comprehensive overview of the Task Management System, including its models and core functionality.

## Models

### Milestone Model

**Structure:**
- `id`: Primary key
- `name`: String, name of the milestone
- `description`: Text, detailed description (nullable)
- `completion_date`: Date, planned completion date (nullable)
- `actual_completion_date`: Date, when the milestone was actually completed (nullable)
- `status`: Enum ('Not Started', 'In Progress', 'Completed', 'Overdue'), default 'Not Started'
- `created_at`: Timestamp, when the milestone was created
- `updated_at`: Timestamp, when the milestone was last updated

**Relationships:**
- Has many Tasks

**Methods:**
- `isCompleted()`: Checks if the milestone is completed
- `isOverdue()`: Checks if the milestone is overdue
- `markAsCompleted()`: Marks the milestone as completed
- `start()`: Changes the status to 'In Progress'

### Task Model (Parent Tasks)

**Structure:**
- `id`: Primary key
- `name`: String, name of the task
- `description`: Text, detailed description (nullable)
- `assigned_to_user_id`: Foreign key to User (nullable)
- `due_date`: Date, when the task is due (nullable)
- `actual_completion_date`: Date, when the task was actually completed (nullable)
- `status`: Enum ('To Do', 'In Progress', 'Done', 'Blocked', 'Archived'), default 'To Do'
- `task_type_id`: Foreign key to TaskType
- `milestone_id`: Foreign key to Milestone (nullable)
- `google_chat_space_id`: String, ID of the Google Chat space (nullable)
- `google_chat_thread_id`: String, ID of the Google Chat thread (nullable)
- `created_at`: Timestamp, when the task was created
- `updated_at`: Timestamp, when the task was last updated

**Relationships:**
- Belongs to User (assignedTo)
- Belongs to TaskType
- Belongs to Milestone
- Has many Subtasks
- Belongs to many Tags (through task_tag pivot table)

**Methods:**
- `isCompleted()`: Checks if the task is completed
- `isOverdue()`: Checks if the task is overdue
- `markAsCompleted()`: Marks the task as completed
- `start()`: Changes the status to 'In Progress'
- `block()`: Changes the status to 'Blocked'
- `archive()`: Changes the status to 'Archived'
- `addNote()`: Adds a note to the task's Google Chat space as a threaded message
- `attachTag()`: Attaches a tag to the task
- `detachTag()`: Detaches a tag from the task

### Subtask Model

**Structure:**
- `id`: Primary key
- `name`: String, name of the subtask
- `description`: Text, detailed description (nullable)
- `assigned_to_user_id`: Foreign key to User (nullable)
- `due_date`: Date, when the subtask is due (nullable)
- `actual_completion_date`: Date, when the subtask was actually completed (nullable)
- `status`: Enum ('To Do', 'In Progress', 'Done', 'Blocked'), default 'To Do'
- `parent_task_id`: Foreign key to Task
- `created_at`: Timestamp, when the subtask was created
- `updated_at`: Timestamp, when the subtask was last updated

**Relationships:**
- Belongs to Task (parentTask)
- Belongs to User (assignedTo)

**Methods:**
- `isCompleted()`: Checks if the subtask is completed
- `isOverdue()`: Checks if the subtask is overdue
- `markAsCompleted()`: Marks the subtask as completed
- `start()`: Changes the status to 'In Progress'
- `block()`: Changes the status to 'Blocked'
- `addNote()`: Adds a note to the parent task's Google Chat space as a threaded message, clearly indicating the subtask name

### Supporting Models

#### TaskType Model

**Structure:**
- `id`: Primary key
- `name`: String, name of the task type
- `description`: Text, detailed description (nullable)
- `created_by_user_id`: Foreign key to User
- `created_at`: Timestamp, when the task type was created
- `updated_at`: Timestamp, when the task type was last updated

**Relationships:**
- Belongs to User (createdBy)
- Has many Tasks

#### Tag Model

**Structure:**
- `id`: Primary key
- `name`: String, name of the tag
- `created_by_user_id`: Foreign key to User
- `created_at`: Timestamp, when the tag was created
- `updated_at`: Timestamp, when the tag was last updated

**Relationships:**
- Belongs to User (createdBy)
- Belongs to many Tasks (through task_tag pivot table)

#### TaskTag (Junction Model)

**Structure:**
- `id`: Primary key
- `task_id`: Foreign key to Task
- `tag_id`: Foreign key to Tag
- `created_at`: Timestamp, when the relationship was created
- `updated_at`: Timestamp, when the relationship was last updated

## Core Functionality

### Google Chat Integration

When a new Task is created:
1. A dedicated Google Chat space is created using the GoogleChatService
2. The space ID is saved to the task's `google_chat_space_id` field
3. An initial message is sent to the space with the task details
4. The thread ID from this message is extracted and saved to the task's `google_chat_thread_id` field
5. If a user is assigned to the task, they are added to the space

When notes are added to a Task:
1. The note is sent as a threaded message to the task's Google Chat space
2. If the task doesn't have a thread ID yet, one is created and saved

When notes are added to a Subtask:
1. The note is sent as a threaded message to the parent task's Google Chat space
2. The message clearly indicates the subtask name (e.g., "📌 *Subtask: Subtask Name*")

### User Assignment

Tasks and Subtasks can be assigned to any user through the `assigned_to_user_id` field. When a user is assigned to a Task, they are automatically added to the Task's Google Chat space.

### Status Management

**Milestone Statuses:**
- Not Started (default)
- In Progress
- Completed
- Overdue

**Task Statuses:**
- To Do (default)
- In Progress
- Done
- Blocked
- Archived

**Subtask Statuses:**
- To Do (default)
- In Progress
- Done
- Blocked

Each model provides methods for transitioning between statuses:
- `start()`: Changes status to 'In Progress'
- `markAsCompleted()`: Changes status to 'Completed' or 'Done'
- `block()` (Tasks and Subtasks only): Changes status to 'Blocked'
- `archive()` (Tasks only): Changes status to 'Archived'

### Dynamic Customization

**Task Types:**
- Users can create new Task Types with a name, description, and creator
- Task Types are linked to Tasks through the `task_type_id` field

**Tags:**
- Users can create new Tags with a name and creator
- Tags can be attached to Tasks using the `attachTag()` method
- Tags can be detached from Tasks using the `detachTag()` method
- Tags are linked to Tasks through the task_tag junction table
