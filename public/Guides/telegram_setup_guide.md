# Telegram Integration Setup Guide

This document summarizes the architecture and usage of the Telegram integration within the Ozee CRM.

## 1. Bot Configuration
Ensure your [.env](file:///Users/zeeshansabri/laravel/email-approval-app/.env) file contains the following keys:
- `TELEGRAM_BOT_TOKEN`: Your bot's API token from @BotFather.
- `TELEGRAM_BOT_NAME`: Your bot's username (e.g., `ozee_web_bot`).

The webhook is automatically handled at: `https://your-crm-domain.com/api/telegram/wh`.

---

## 2. Linking Procedures

### A. Linking a Project (Groups)
To link a project with a Telegram group:
1. Generate a **Project Link Code** from the Project settings page in the CRM.
2. Add your Telegram Bot to the desired Telegram group and make it an **Administrator** with permission to "Manage Topics".
3. In the group, send the command:
   ```text
   /link #PROJECT_CODE
   ```
4. Once linked, the CRM automatically initializes the following topics in that group:
   - **General**: Main discussion thread.
   - **Client Communication**: Dedicated thread for client messages.

### B. Linking an Account (Admin Users & Clients)
To receive private notifications and link your identity to Telegram messages:
1. Generate a **Link Code** from:
   - **Admins**: Your User Profile > Telegram Integration.
   - **Clients**: Client Dashboard prompt or Client Profile.
2. Open a private chat with the bot and send:
   ```text
   /link #USER_OR_CLIENT_CODE
   ```
3. Once linked, the bot will notify you that your identity is verified.

---

## 3. Communication Architecture

### Topic-Based Routing
The system uses Telegram's **Forum Topics** (threads) to organize discussions:
- **General Discussion**: Standard project management chat.
- **Client Communication**: All messages from linked clients (incoming) and replies to them (outgoing) are automatically routed here.
- **Custom Topics**: Admins can create additional topics through the CRM Chat interface to discuss specific items (e.g., "Budget", "Design").

### Automated Client Routing
- Even if a client sends a message in the "General" thread of the Telegram group, the CRM is programmed to automatically capture and record it under the **"Client Communication"** topic in the CRM Chat history.
- When an admin replies to these messages in the CRM, the response is sent back to the correct "Client Communication" thread in Telegram.

---

## 4. Real-time Capabilities
- **Broadcasting**: All incoming Telegram messages trigger a [ChatMessageSent](file:///Users/zeeshansabri/laravel/email-approval-app/app/Events/ChatMessageSent.php#14-73) event.
- **Synchronization**: The frontend uses **Laravel Reverb (Echo)** to update the chat sidebar in real-time, showing new messages without page refreshes.
- **Performance**: Heavy database lookups (mapping Telegram IDs to CRM IDs) are cached to ensure low latency for webhook processing.

---

## 5. UI Components

### Admin Interface
- **Communication Sidebar**: Full-featured chat center with project/topic navigation.
- **Client Management**: Allows admins to generate and share Telegram link codes for their clients directly.

### Client Dashboard
- **Telegram Prompt**: A branded "Link Account" banner appears on the client dashboard if they are not yet linked.
- **Branded Linking Form**: A specialized form ([TelegramIntegrationForm.vue](file:///Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Profile/Partials/TelegramIntegrationForm.vue)) guides clients through the verification process.

## 6. Maintenance & Debugging
- All incoming webhook payloads are logged as JSON files in `storage/app/telegram/webhooks/` for troubleshooting.
- Ensure the bot has **"Manage Topics"** permissions to create new threads.
- If a group is not a "Forum", the bot will send instructions to the group explaining how to enable the feature.
