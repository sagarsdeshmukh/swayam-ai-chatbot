(function() {
    'use strict';

    // Initialize all chat widgets on the page
    document.addEventListener('DOMContentLoaded', function() {
        initializeChatWidgets();
        initializeFloatingWidget();
    });

    function initializeChatWidgets() {
        const widgets = document.querySelectorAll('.swayam-ai-chatbot-widget');
        widgets.forEach(widget => new ChatWidget(widget));
    }

    function initializeFloatingWidget() {
        const toggle = document.querySelector('.swayam-ai-chatbot-toggle');
        const container = document.querySelector('.swayam-ai-chatbot-floating-container');

        if (!toggle || !container) return;

        toggle.addEventListener('click', function() {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !isExpanded);
            container.style.display = isExpanded ? 'none' : 'block';

            // Focus input when opening
            if (!isExpanded) {
                const input = container.querySelector('.swayam-ai-chatbot-input');
                if (input) {
                    setTimeout(() => input.focus(), 100);
                }
            }
        });

        // Close button inside floating widget
        const closeBtn = container.querySelector('.swayam-ai-chatbot-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                toggle.setAttribute('aria-expanded', 'false');
                container.style.display = 'none';
            });
        }
    }

    class ChatWidget {
        constructor(element) {
            this.element = element;
            this.messagesContainer = element.querySelector('.swayam-ai-chatbot-messages');
            this.form = element.querySelector('.swayam-ai-chatbot-form');
            this.input = element.querySelector('.swayam-ai-chatbot-input');
            this.submitBtn = element.querySelector('.swayam-ai-chatbot-submit');
            this.sessionId = this.generateSessionId();
            this.isLoading = false;

            this.bindEvents();
        }

        bindEvents() {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleSubmit();
            });

            // Allow Enter to submit (but Shift+Enter for new line if it were a textarea)
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.handleSubmit();
                }
            });
        }

        async handleSubmit() {
            const question = this.input.value.trim();

            if (!question || this.isLoading) return;

            // Clear input
            this.input.value = '';

            // Remove welcome message if present
            const welcome = this.messagesContainer.querySelector('.swayam-ai-chatbot-welcome');
            if (welcome) {
                welcome.remove();
            }

            // Add user message
            this.addMessage(question, 'user');

            // Show typing indicator
            this.showTypingIndicator();

            // Disable input while loading
            this.setLoading(true);

            try {
                const response = await this.sendQuestion(question);

                // Remove typing indicator
                this.hideTypingIndicator();

                if (response.success) {
                    this.addBotMessage(response.answer, response.sources);
                } else {
                    this.addErrorMessage(response.error || swayamAiChatbot.strings.error);
                }
            } catch (error) {
                console.error('Swayam AI Chatbot error:', error);
                this.hideTypingIndicator();
                this.addErrorMessage(swayamAiChatbot.strings.networkError);
            } finally {
                this.setLoading(false);
                this.input.focus();
            }
        }

        async sendQuestion(question) {
            const response = await fetch(swayamAiChatbot.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': swayamAiChatbot.nonce
                },
                body: JSON.stringify({
                    question: question,
                    session_id: this.sessionId
                })
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.error || 'Request failed');
            }

            return response.json();
        }

        addMessage(content, type) {
            const message = document.createElement('div');
            message.className = `swayam-ai-chatbot-message swayam-ai-chatbot-message-${type}`;
            message.textContent = content;
            this.messagesContainer.appendChild(message);
            this.scrollToBottom();
        }

        addBotMessage(answer, sources) {
            const message = document.createElement('div');
            message.className = 'swayam-ai-chatbot-message swayam-ai-chatbot-message-bot';

            // Format the answer (basic markdown-like support)
            const formattedAnswer = this.formatAnswer(answer);
            message.innerHTML = formattedAnswer;

            // Add sources if available
            if (sources && sources.length > 0) {
                const sourcesHtml = this.formatSources(sources);
                message.innerHTML += sourcesHtml;
            }

            this.messagesContainer.appendChild(message);
            this.scrollToBottom();
        }

        formatAnswer(text) {
            // Convert line breaks to paragraphs
            const paragraphs = text.split(/\n\n+/);
            return paragraphs.map(p => `<p>${this.escapeHtml(p.trim())}</p>`).join('');
        }

        formatSources(sources) {
            let html = '<div class="swayam-ai-chatbot-sources">';
            html += `<div class="swayam-ai-chatbot-sources-title">${swayamAiChatbot.strings.sources}</div>`;
            html += '<ul class="swayam-ai-chatbot-sources-list">';

            sources.forEach(source => {
                const title = source.title || source.url;
                html += `<li><a href="${this.escapeHtml(source.url)}" target="_blank" rel="noopener">${this.escapeHtml(title)}</a></li>`;
            });

            html += '</ul></div>';
            return html;
        }

        addErrorMessage(error) {
            const message = document.createElement('div');
            message.className = 'swayam-ai-chatbot-error';
            message.textContent = error;
            this.messagesContainer.appendChild(message);
            this.scrollToBottom();
        }

        showTypingIndicator() {
            const typing = document.createElement('div');
            typing.className = 'swayam-ai-chatbot-typing';
            typing.innerHTML = `
                <span class="swayam-ai-chatbot-typing-dot"></span>
                <span class="swayam-ai-chatbot-typing-dot"></span>
                <span class="swayam-ai-chatbot-typing-dot"></span>
            `;
            typing.setAttribute('aria-label', swayamAiChatbot.strings.thinking);
            this.messagesContainer.appendChild(typing);
            this.scrollToBottom();
        }

        hideTypingIndicator() {
            const typing = this.messagesContainer.querySelector('.swayam-ai-chatbot-typing');
            if (typing) {
                typing.remove();
            }
        }

        setLoading(loading) {
            this.isLoading = loading;
            this.submitBtn.disabled = loading;
            this.input.disabled = loading;
        }

        scrollToBottom() {
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        }

        generateSessionId() {
            return 'swayam_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }
})();
