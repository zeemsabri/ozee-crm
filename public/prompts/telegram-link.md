Telegram CRM Integration - Database Schema BlueprintBased on the Two-Bot Proxy Architecture (one bot for Clients, one bot for the Internal Team) and the use of Telegram Topics for routing, here are the required changes to your existing database to support advanced features like read receipts, saved messages, and polymorphic topics.1. The users Table (Internal Team)Your team members will interact with the Internal Bot. We need to link their CRM profile to their Telegram account.Columns to Add:telegram_chat_id (VARCHAR, nullable, unique): Stores the positive integer ID of the user's Telegram account. (Use VARCHAR because Telegram IDs are growing and can exceed standard INT limits).Data Strategy:Discard: Telegram usernames (they change frequently and aren't reliable).Save in JSON: If you want to store their Telegram status or other minor variables, put it in the existing online_data JSON column. (No need for is_premium or language_code).2. The clients Table (External Clients)Clients will interact with the Client Support Bot via Direct Message. Because clients can have multiple projects, we must track their "Active Context" so the bot knows where to route their messages.Columns to Add:telegram_chat_id (VARCHAR, nullable, unique): The client's ID for the Support Bot.active_telegram_project_id (BIGINT unsigned, nullable): A foreign key linking to the projects table. When the client selects "Switch Project" from the bot menu, this column updates.Data Strategy:Discard: Raw Telegram update payloads (unless debugging).Save in JSON: Store their Telegram first_name and last_name in a new telegram_meta JSON column, just in case it differs from their official CRM name.3. The projects & New telegram_topics TableSince a single project can have multiple specific topics, and topics can be linked to other models (like Tasks, Milestones, or Leads), we need a highly flexible polymorphic table.Modifications to projects:telegram_group_id (VARCHAR, nullable): Stores the Supergroup ID (e.g., -1003821883588).New Table: telegram_topicsThis model maps a CRM entity (Project, Task, Milestone, etc.) to its specific Telegram threads.id (BIGINT unsigned, auto-increment)project_id (BIGINT unsigned, nullable): Foreign key to the project (nullable if the topic belongs directly to a Lead instead).topicable_id (BIGINT unsigned, nullable): Polymorphic ID to link directly to Milestones, Tasks, Project Deliverables, etc.topicable_type (VARCHAR, nullable): Polymorphic Type (e.g., App\Models\Task).telegram_id (VARCHAR): The actual message_thread_id from Telegram (e.g., 8, 17277).name (VARCHAR): The name of the topic (e.g., "Content Writing").type (VARCHAR): Helps the CRM know what this topic is for (e.g., proxy_client, internal, billing, general).is_private (BOOLEAN, default 0): If true, only users with the view_private_topic permission can view this topic in the CRM.4. The chat_messages Table (Unified Messaging)To support your deep search, direct Telegram ID tracking, and future-proofing, we will update the messaging table.Columns to Add:client_id (BIGINT unsigned, nullable): Foreign key to the clients table.telegram_topic_id (BIGINT unsigned, nullable): Foreign key to the new telegram_topics table.telegram_message_id (VARCHAR, nullable): The exact message ID from Telegram (required to sync edits/deletes).source (VARCHAR, default 'crm'): Identifies where the message came from. (Values: crm, telegram_client_bot, telegram_internal_bot). use enumsData Strategy (Using the existing meta_data JSON column):We will heavily utilize your existing meta_data JSON column for flexible future data without schema changes:{
"reply_to_message_id": 36,
"has_attachments": true,
"file_ids": ["AgACAgUAAxkBAAMN..."]
}

5. UI Feature Tracking Tables (Read Receipts & Saved Messages)To support the new Phase 1 UI features (seeing exactly who read a message via a tooltip, and allowing users to save/bookmark messages), we need two new pivot tables.New Table: chat_message_reads (Read Receipts)chat_message_id (BIGINT unsigned)user_id (BIGINT unsigned)read_at (TIMESTAMP)New Table: user_saved_messages (Bookmarks)user_id (BIGINT unsigned)chat_message_id (BIGINT unsigned)notes (TEXT, nullable): Optional context if the user wants to remember why they saved it or to link it to a specific task.created_at (TIMESTAMP)Summary of Laravel Migrations NeededTo implement this, you would generate a migration file to alter your existing tables and create the new tracking tables:Schema::table('users', function (Blueprint $table) {
   $table->string('telegram_chat_id')->nullable()->unique()->after('chat_name');
   });

Schema::table('clients', function (Blueprint $table) {
$table->string('telegram_chat_id')->nullable()->unique()->after('phone');
$table->foreignId('active_telegram_project_id')->nullable()->constrained('projects')->nullOnDelete();
});

Schema::table('projects', function (Blueprint $table) {
$table->string('telegram_group_id')->nullable()->after('google_chat_id');
});

Schema::create('telegram_topics', function (Blueprint $table) {
$table->id();
$table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
$table->nullableMorphs('topicable'); // Creates topicable_id and topicable_type
$table->string('telegram_id')->nullable(); // The thread ID
$table->string('name');
$table->string('type')->default('general');
$table->boolean('is_private')->default(false); // Protects sensitive topics/billing
$table->timestamps();
});

Schema::table('chat_messages', function (Blueprint $table) {
$table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete()->after('user_id');
$table->foreignId('telegram_topic_id')->nullable()->constrained('telegram_topics')->nullOnDelete()->after('project_id');
$table->string('telegram_message_id')->nullable()->after('telegram_topic_id');
$table->string('source')->default('crm')->after('type');
// Note: The `meta_data` JSON column already exists in your DB!
});

// For Read Receipts Tooltips
Schema::create('chat_message_reads', function (Blueprint $table) {
$table->id();
$table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->timestamp('read_at')->useCurrent();
$table->unique(['chat_message_id', 'user_id']);
});

// For "Save Message" feature
Schema::create('user_saved_messages', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
$table->text('notes')->nullable();
$table->timestamps();
$table->unique(['user_id', 'chat_message_id']);
});

Why this structure is bulletproof:Total Polymorphism: The telegram_topics table can now belong to a Project, a Task, a Milestone, or a Lead. If a user with the create_topic permission clicks "Create Topic" on a Task UI, the DB effortlessly handles the linkage.Private Data Isolation: The is_private flag on topics works exactly like your existing emails.is_private logic. It guarantees AI context generation and regular contractors are completely locked out of sensitive threads.Advanced UI Support: The chat_message_reads table powers the exact "multiple read tool-tip" you want to build, separating delivery from interaction.
