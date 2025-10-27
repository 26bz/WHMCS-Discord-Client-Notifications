/*
 * WHMCS Discord Client Notifications
 * Copyright (C) 2025 26bz (https://26bz.online/)
 * Licensed under GNU GPLv3 or later. See LICENSE file.
 */

require('dotenv').config();

const { Client, GatewayIntentBits } = require('discord.js');
const express = require('express');
const rateLimit = require('express-rate-limit');

const PORT = process.env.PORT || 3000;
const BOT_TOKEN = process.env.DISCORD_BOT_TOKEN;
const API_KEY = process.env.API_KEY;

const client = new Client({
  intents: [GatewayIntentBits.Guilds, GatewayIntentBits.DirectMessages],
});

const app = express();
app.set('trust proxy', 1);
app.use(express.json());

const limiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 100,
});

client.once('ready', () => {
  console.log(`Logged in as ${client.user.tag}`);
});

client.on('error', (error) => {
  console.error('Discord client error:', error);
});

app.post('/api/send-dm', limiter, async (req, res) => {
  const apiKey = req.headers['x-api-key'];
  if (!apiKey || apiKey.toLowerCase() !== API_KEY.toLowerCase()) {
    return res.status(401).json({ success: false, error: 'Unauthorized' });
  }

  const { discord_id, message, use_embed, embed_data } = req.body;

  if (!discord_id) {
    return res.status(400).json({ success: false, error: 'Missing discord_id parameter' });
  }

  if (!message && !embed_data) {
    return res.status(400).json({ success: false, error: 'Missing message or embed_data parameter' });
  }

  try {
    const user = await client.users.fetch(discord_id);

    let messageOptions = {};

    if (use_embed && embed_data) {
      const embed = {
        title: embed_data.title,
        description: embed_data.description,
        url: embed_data.url,
        color: embed_data.color || 0x0099ff,
        timestamp: embed_data.timestamp,
        footer: embed_data.footer,
        fields: embed_data.fields || [],
      };

      messageOptions.embeds = [embed];

      if (embed_data.components && embed_data.components.length > 0) {
        messageOptions.components = embed_data.components;
      }
    } else {
      messageOptions.content = message;
    }

    await user.send(messageOptions);

    return res.status(200).json({ success: true });
  } catch (error) {
    console.error('Error sending message to user: %s', discord_id, error);

    if (error.code === 50007) {
      return res.status(403).json({
        success: false,
        error: 'Cannot send messages to this user (they may have DMs disabled)',
      });
    } else if (error.code === 10013) {
      return res.status(404).json({
        success: false,
        error: 'Unknown user - Discord ID not found',
      });
    } else if (error.code === 50001) {
      return res.status(403).json({
        success: false,
        error: 'Missing access - Bot lacks permissions',
      });
    }

    return res.status(500).json({ success: false, error: error.message });
  }
});

app.get('/health', (req, res) => {
  return res.status(200).json({ status: 'ok' });
});

async function startServer() {
  try {
    await client.login(BOT_TOKEN);

    app.listen(PORT, () => {
      console.log(`Server running on port ${PORT}`);
    });
  } catch (error) {
    console.error('Failed to start server:', error);
    process.exit(1);
  }
}

process.on('SIGINT', () => {
  console.log('Shutting down...');
  client.destroy();
  process.exit(0);
});

startServer();
