CRM Category Model: The Hybrid Powerhouse
1. Overview
   This document outlines the design for a flexible and structured categorization system for the CRM. The goal is to create a reusable model that allows various CRM records (like Contacts, Companies, Products, etc.) to be organized by distinct types of categories.

This "Hybrid Powerhouse" or "Category Set" model avoids the chaos of a single, flat tag list by grouping categories into logical Sets. It also provides database-level rules to define which models a particular set can be applied to.

2. Core Concepts
   The model is built on four core components:

Category Sets: These are the high-level containers that define the type of categories. For example, "Industry," "Region," or "Account Tier."

Model Bindings (New!): This defines which models a Category Set is allowed to be used with. For example, the "Account Tier" set might be bound only to the Company model. If a set has no bindings, it is considered "global" and can be used anywhere.

Categories: These are the specific, individual labels that live inside a Category Set. For example, inside the "Industry" set, you might have "Technology," "Healthcare," and "Finance."

Categorizables: This is the "glue" that links a specific Category to any other record in the CRM using a polymorphic relationship.

3. Database Schema
   The system will be composed of four primary database tables.

3.1. category_sets
This table defines the high-level groupings for categories.

Column

Data Type

Constraints

Description

id

BIGINT

Primary Key, Unsigned

The unique identifier for the category set.

name

VARCHAR(255)

Not Null

The user-friendly name (e.g., "Account Tier").

slug

VARCHAR(255)

Not Null, Unique

A URL-friendly version of the name (e.g., "account-tier").

created_at

TIMESTAMP

Not Null

Timestamp of when the record was created.

updated_at

TIMESTAMP

Not Null

Timestamp of the last update.

Example Data:

id

name

slug

1

Industry

industry

2

Region

region

3

Account Tier

account-tier

3.2. category_set_bindings (New Table)
This table creates an explicit link between a category_set and the Eloquent models it can be applied to.

Column

Data Type

Constraints

Description

category_set_id

BIGINT

Foreign Key to category_sets.id

The ID of the category set being bound.

model_type

VARCHAR(255)

Not Null

The class name of the model (e.g., "App\Models\Company").

Indexes: A composite primary key should be placed on [category_set_id, model_type].

Example Data:

category_set_id

model_type

1

App\Models\Company

2

App\Models\Company

2

App\Models\Contact

3

App\Models\Company

Logic:

The "Industry" set (id 1) can only be used with Company models.

The "Region" set (id 2) can be used with both Company and Contact models.

The "Account Tier" set (id 3) can only be used with Company models.

3.3. categories
This table holds all the individual category options, each belonging to a specific category_set.

Column

Data Type

Constraints

Description

id

BIGINT

Primary Key, Unsigned

The unique identifier for the category.

category_set_id

BIGINT

Foreign Key to category_sets.id

Links this category to its parent set.

name

VARCHAR(255)

Not Null

The user-friendly name (e.g., "Technology").

created_at

TIMESTAMP

Not Null

Timestamp of when the record was created.

updated_at

TIMESTAMP

Not Null

Timestamp of the last update.

3.4. categorizables
This is the polymorphic join table that connects a category to any other model in the application.

Column

Data Type

Constraints

Description

category_id

BIGINT

Foreign Key to categories.id

The ID of the category being applied.

categorizable_id

BIGINT

Not Null

The ID of the record being categorized (e.g., a Company's ID).

categorizable_type

VARCHAR(255)

Not Null

The model name of the record (e.g., "Company", "Contact").

Indexes: A composite index should be placed on [categorizable_id, categorizable_type] for efficient lookups.

4. Key Advantages & Use Cases
   Database-Enforced Relevance: The system now guarantees that only the correct categories can be applied to the correct records. You can't accidentally categorize a Contact with an "Account Tier."

Smarter UI: The UI can query the category_set_bindings table to dynamically and automatically show only the relevant Category Sets for the screen being viewed, with no hard-coded logic.

Structure and Organization: Prevents the "tag soup" problem where tags like "Lead" (a status) and "Technology" (an industry) are mixed together.

Improved Reporting: You can easily generate reports like "Show me all Gold Tier companies in the Technology industry."

Scalability: Adding a new type of categorization or linking an existing set to a new model is as simple as adding a new row, without affecting existing data.
