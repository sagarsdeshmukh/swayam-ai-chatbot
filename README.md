# Swayam AI Chatbot - WordPress Plugin

![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)
![License](https://img.shields.io/badge/License-GPL%20v3-green)

A WordPress plugin that provides an AI-powered chatbot using RAG (Retrieval-Augmented Generation) architecture with LLPhant, Llama 3.2 (via Ollama), and Elasticsearch.

## Why "Swayam"?

Swayam (स्वयं)—an ancient Sanskrit word meaning "self." Your content. Your knowledge. Autonomously intelligent.

## Features

- **RAG-Powered Q&A**: Answers questions based on your WordPress content
- **Automatic Content Indexing**: Syncs posts, pages, and custom post types to Elasticsearch
- **Auto-Sync on Publish**: Automatically updates the index when content is published/updated/deleted
- **Customizable Chat Interface**: Shortcode and floating widget options
- **Admin Dashboard**: Easy configuration with connection testing
- **REST API**: Programmatic access to the chatbot
- **Rate Limiting**: Built-in protection against spam
- **PHP 8.2+ Compatible**: Works with PHP 8.2, 8.3, and later versions

**[Download plugin on wordpress.org](https://wordpress.org/plugins/swayam-ai-chatbot/)**

## Requirements

- **PHP**: 8.2 or higher
- **WordPress**: 6.0 or higher
- **Ollama**: Running locally with Llama 3.2 model
- **Elasticsearch**: 9.x with vector search support
- **Composer**: For dependency management

## Installation

**1. Install the Plugin**

```bash
# Navigate to your WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Clone or copy the plugin
cp -r /path/to/swayam-ai-chatbot ./

# Install dependencies
cd swayam-ai-chatbot
composer install
```

**2. Install and Start Ollama**

You can install [Llama 3.2](https://www.llama.com/) using [ollama](https://ollama.com/).

For installing ollama on Linux, run the following command:

```bash
curl -fsSL https://ollama.com/install.sh | sh
```

For macOS or Windows use the [download](https://ollama.com/download) page.

It is recommended to install Llama 3.2-1B or 3B for optimized CPU/GPU, and RAM usage.

For installing Llama3.2-3B use the following command:

```bash
ollama run llama3.2:3b
```

You can start interacting to the LLama3.2 model using a chat. To exit, write `/bye` in the chat.

**3. Install and Start Elasticsearch**

```bash
curl -fsSL https://elastic.co/start-local | sh
```

This script will install Elasticsearch and Kibana using a `docker-compose.yml` file stored in
`elastic-start-local` folder.

Elasticsearch and Kibana will run locally at http://localhost:9200 and http://localhost:5601.

All the settings of Elasticsearch and Kibana are stored in the `elastic-start-local/.env` file.

You can use the `start` and `stop` commands available in the `elastic-start-local` folder.

To **stop** the Elasticsearch and Kibana Docker services, use the `stop` command:

```bash
cd elastic-start-local
./stop.sh
```

To **start** the Elasticsearch and Kibana Docker services, use the `start` command:

```bash
cd elastic-start-local
./start.sh
```
