# Categorization System Documentation

This document explains the reusable, polymorphic categorization engine designed to organize models into sets and categories without creating redundant tables.

## Core Components

### 1. Database Tables
*   **`category_sets`**: Containers for categories (e.g., "Project Type", "Departments").
*   **`categories`**: Individual items within a set (e.g., "Management", "Accounts").
*   **`category_set_bindings`**: Maps a `CategorySet` to specific model types (e.g., "Departments" $\rightarrow$ `App\Models\User`).
*   **`categorizables`**: Polymorphic pivot table linking any model record to a category.

### 2. The `HasCategories` Trait
To make a model categorizable, include the `App\Models\Traits\HasCategories` trait.

```php
use App\Models\Traits\HasCategories;

class User extends Authenticatable {
    use HasCategories;
}
```

## Key Mechanisms

### Reusability
The system uses **Polymorphic Relationships**. The `categorizables` table stores `categorizable_id` and `categorizable_type`, allowing it to link to any model.

### Context-Aware Filtering
The trait provides helper methods that respect bindings:
*   `availableCategorySets()`: Returns only sets bound to the model class (or global sets with no bindings).
*   `availableCategories(?string $setSlug = null)`: Returns categories belonging to allowed sets.

### Bindings
Bindings ensure that users only see relevant categories. For example, "Management" should appear when editing a **User**, but not when categorizing an **Email**.

## Common Tasks

### Adding Categories to a New Model
1.  Apply `HasCategories` trait to the model.
2.  Create a `CategorySet` (e.g., via Seeder or UI).
3.  Add a record to `category_set_bindings` linking the set to your model's class string.

### Managing Categorizations
```php
// Sync categories (replaces existing)
$model->syncCategories([1, 2, 3]);

// Attach without detaching
$model->attachCategories([4]);

// Detach specific
$model->detachCategories([1]);
```

## Advanced: Nesting Responsibilities
Categories themselves can be `Taggable`. This allows for a two-tier hierarchy:
1.  **Category**: The primary group (e.g., Department: "Accounts").
2.  **Tags**: Responsibilities within that group (e.g., "Invoicing", "Payroll").
