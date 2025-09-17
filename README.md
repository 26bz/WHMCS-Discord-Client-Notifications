# WHMCS Discord Notifications

Send WHMCS notifications directly to customers via Discord DMs with rich embeds and interactive buttons.

## Features

- **Rich Discord Embeds** - Beautiful notifications with colors, buttons, and formatting
- **Interactive Buttons** - Direct action buttons (View Ticket, Pay Invoice, etc.)
- **Universal Support** - Works with all WHMCS events (tickets, invoices, services, etc.)
- **Custom Hooks** - Easy integration for affiliate activations, withdrawals, and more
- **Smart Detection** - Automatically finds Discord IDs and client information

## Requirements

- WHMCS 8.x+
- Node.js 16.9.0+
- Discord bot token from [Discord Developer Portal](https://discord.com/developers/applications)

## Setup Guide

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
   cd discord-bot
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
     - **Discord ID Field**: `Discord ID` (custom field name)
   - Enable all desired features (Rich Embeds, Buttons, etc.)

3. **Create Custom Field:**
   - Go to **Setup → Custom Client Fields**
   - Add new field:
     - **Field Name**: `Discord ID`
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

## License

MIT License
