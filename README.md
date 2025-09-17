# WHMCS Discord Client Notifications

A comprehensive Discord notification system for WHMCS that sends real-time notifications directly to your clients via Discord DMs.

## ⚠️ Important Dependency

**This addon requires our free [WHMCS Client Verification Addon](https://github.com/26bz/WHMCS-Discord-Client-Verification) to function properly.**

The Client Verification addon provides:

- **Discord Account Linking**: Links WHMCS client accounts to Discord users
- **Role Assignment**: Automatically assigns Discord roles based on client status
- **User Verification**: Ensures only verified clients receive notifications
- **Account Security**: Prevents unauthorized access to client notifications

**You must install and configure the Client Verification addon first before using this notification system.**

## Features

- **Direct Message Notifications**: Send notifications directly to clients' Discord DMs
- **Rich Embed Support**: Beautiful, formatted Discord embeds with colors and fields
- **Priority Color Coding**: Different colors based on ticket priority levels
- **Comprehensive Event Support**: Tickets, invoices, orders, services, and domains
- **Custom Hook Support**: Extensible with custom notification events
- **Rate Limiting**: Built-in protection against spam
- **Error Handling**: Robust error handling with detailed logging
- **Health Monitoring**: Built-in health check endpoint for monitoring

## Requirements

- WHMCS 7.0+
- Node.js 16+
- Discord Bot Token
- PHP 7.4+
- **[WHMCS Client Verification Addon](https://github.com/26bz/WHMCS-Discord-Client-Verification)** (Required)

## Setup Guide

### Step 0: Install Prerequisites

**Before proceeding, you MUST install the Client Verification addon:**

1. Download the [WHMCS Client Verification Addon](https://github.com/26bz/WHMCS-Discord-Client-Verification)
2. Follow its installation and configuration guide
3. Ensure Discord account linking is working properly
4. Test that clients can verify their Discord accounts

**Only proceed to Step 1 after the Client Verification addon is fully configured.**

### Step 1: Create Discord Bot

1. **Create Discord Application:**

   - Go to [Discord Developer Portal](https://discord.com/developers/applications)
   - Click "New Application" → Enter name → Create
   - Go to "Bot" tab → Click "Add Bot"
   - Copy the bot token (save for later)

2. **Configure Bot Permissions:**
   - In Bot settings, enable "SERVER MEMBERS INTENT"
   - Go to "OAuth2" → "URL Generator"
   - Select "bot" scope and "Send Messages" permission
   - Use generated URL to invite bot to your server

### Step 2: Install & Run Discord Bot

1. **Install Dependencies:**

   ```bash
   cd whmcs-discord-client-notifications
   npm install
   ```

2. **Configure Environment:**

   ```bash
   cp .env.example .env
   ```

   Edit `.env` file:

   ```env
   DISCORD_BOT_TOKEN=your_bot_token_here
   API_KEY=your_secure_random_string
   PORT=3000
   ```

3. **Start Bot:**

   ```bash
   # Development
   node index.js

   # Production (with PM2)
   npm install -g pm2
   pm2 start index.js --name discord-notifications
   pm2 save && pm2 startup
   ```

4. **Verify Bot is Running:**
   - Visit `http://your-server:3000/health`
   - Should return `{"status":"ok"}`

### Step 3: Install WHMCS Notification System

1. **Copy Files:**

   ```bash
   cp -r whmcs/modules/notifications/Discord /path/to/whmcs/modules/notifications/
   ```

2. **Configure Provider:**

   - Login to WHMCS Admin
   - Go to **Configuration → System Settings → Notifications**
   - Find "Discord" provider → Click "Configure"
   - Set:
     - **API URL**: `http://your-server:3000/api/send-dm`
     - **API Key**: Same as in your `.env` file
     - **Discord ID Field**: `discord` (custom field name)
   - Enable all desired features (Rich Embeds, Buttons, etc.)

3. **Create Custom Field:**
   - Go to **Setup → Custom Client Fields**
   - Add new field:
     - **Field Name**: `discord`
     - **Field Type**: Text Box
     - **Description**: Enter your Discord User ID
   - Save changes

### Step 4: Create Notification Rules

1. **Basic Notification Rule:**

   - Go to **Configuration → System Settings → Notifications**
   - Click "Create New Notification Rule"
   - Configure:
     - **Name**: High Priority Tickets
     - **Event Category**: Ticket
     - **Event**: New Ticket
     - **Conditions**: Priority = High
     - **Provider**: Discord
   - Save rule

2. **Test Notification:**
   - Create test ticket with high priority
   - Client with Discord ID should receive DM

### Step 5: Implement Custom Hooks

1. **Install Hook Files:**

   ```bash
   # Copy custom hooks to WHMCS
   cp whmcs/modules/notifications/Discord/hooks/*.php /path/to/whmcs/includes/hooks/
   ```

2. **Available Custom Hooks:**

   - `affiliate_notifications.php` - Welcome new affiliates
   - `affiliate_withdrawal_notifications.php` - Confirm withdrawal requests
   - `invoice_unpaid_notifications.php` - Alert payment issues

3. **Create Notification Rules for Hooks:**

   **For Affiliate Activation:**

   - Event Category: `API`
   - Event: `Custom API Trigger`
   - Trigger Identifier: `affiliate.activation.user`
   - Provider: `Discord`

   **For Withdrawal Requests:**

   - Event Category: `API`
   - Event: `Custom API Trigger`
   - Trigger Identifier: `affiliate.withdrawal.confirmation`
   - Provider: `Discord`

   **For Invoice Unpaid:**

   - Event Category: `API`
   - Event: `Custom API Trigger`
   - Trigger Identifier: `invoice.unpaid.client`
   - Provider: `Discord`

### Step 6: Client Setup

1. **Instruct Clients:**

   - Clients go to their profile in client area
   - Fill in "Discord ID" custom field with their Discord user ID
   - Save profile

2. **Find Discord User ID:**
   - Enable Developer Mode in Discord (Settings → Advanced)
   - Right-click username → Copy User ID

## Troubleshooting

**Bot Not Sending Messages:**

- Check bot is running: `http://your-server:3000/health`
- Verify API key matches between bot and WHMCS
- Check WHMCS Activity Log for errors
- Ensure client has valid Discord ID in custom field

**Discord API Errors:**

- Bot must share a server with the user to send DMs
- User must have DMs enabled from server members
- Check bot has "Send Messages" permission

**Hook Not Triggering:**

- Verify hook file is in `/path/to/whmcs/includes/hooks/`
- Check WHMCS Activity Log for hook execution
- Ensure notification rule exists with correct trigger identifier

## Contact

- Discord: https://26bz.online/discord/
