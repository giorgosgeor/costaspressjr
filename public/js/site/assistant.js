/**
 * Shop assistant widget.
 *
 * Talks to POST /assistant/ask and renders the reply. All the knowledge lives
 * server-side in ShopAssistant.php; this file is only the conversation UI.
 */
(function () {
    'use strict';

    var launcher = document.getElementById('assistantLauncher');
    var panel    = document.getElementById('assistantPanel');
    if (!launcher || !panel) return;

    var log      = document.getElementById('assistantLog');
    var form     = document.getElementById('assistantForm');
    var input    = document.getElementById('assistantInput');
    var closeBtn = document.getElementById('assistantClose');
    var sendBtn  = form ? form.querySelector('button[type="submit"]') : null;

    var started = false;
    var busy    = false;

    function t(key, fallback) {
        var v = window.I18N && window.I18N.messages && window.I18N.messages[key];
        return v || fallback;
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function scrollToEnd() {
        log.scrollTop = log.scrollHeight;
    }

    /**
     * Two deliberately different insertion paths.
     *
     * Bot text comes from our own language file and legitimately contains
     * markup - the FAQ answers carry <a> and <strong> - so it is set as HTML.
     * Anything the customer typed is set with textContent, never HTML: it is
     * untrusted input and echoing it back into innerHTML is precisely how a
     * chat box becomes a self-XSS.
     */
    function addBot(html) {
        var el = document.createElement('div');
        el.className = 'assistant-msg assistant-msg-bot';
        el.innerHTML = html;
        log.appendChild(el);
        scrollToEnd();
        return el;
    }

    function addYou(text) {
        var el = document.createElement('div');
        el.className = 'assistant-msg assistant-msg-you';
        el.textContent = text;
        log.appendChild(el);
        scrollToEnd();
    }

    function addChips(items) {
        if (!items || !items.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'assistant-chips';
        items.forEach(function (item) {
            var node;
            if (typeof item === 'string') {
                // A suggested question: asks it on click.
                node = document.createElement('button');
                node.type = 'button';
                node.className = 'assistant-chip';
                node.textContent = item;
                node.addEventListener('click', function () { ask(item); });
            } else {
                // A link the answer offered.
                node = document.createElement('a');
                node.className = 'assistant-chip assistant-chip-link';
                node.href = item.href;
                node.textContent = item.label;
            }
            wrap.appendChild(node);
        });
        log.appendChild(wrap);
        scrollToEnd();
    }

    function setBusy(state) {
        busy = state;
        if (sendBtn) sendBtn.disabled = state;
        if (input) input.disabled = state;
    }

    function ask(question) {
        if (busy) return;
        var q = (question || '').trim();
        if (!q) return;

        addYou(q);
        if (input) input.value = '';
        setBusy(true);

        var typing = document.createElement('div');
        typing.className = 'assistant-typing';
        typing.textContent = t('assistant.thinking', 'Typing…');
        log.appendChild(typing);
        scrollToEnd();

        fetch('/assistant/ask', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf()
            },
            body: JSON.stringify({ q: q })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            typing.remove();
            addBot(data && data.text ? data.text : t('assistant.error', 'Something went wrong.'));
            // Links first (the useful next step), then any suggested questions.
            if (data && data.links && data.links.length) addChips(data.links);
            if (data && data.suggestions && data.suggestions.length) addChips(data.suggestions);
        })
        .catch(function () {
            typing.remove();
            addBot(t('assistant.error', 'Something went wrong. Please use the contact page.'));
        })
        .finally(function () {
            setBusy(false);
            if (input && panel.offsetParent !== null) input.focus();
        });
    }

    function open() {
        panel.hidden = false;
        launcher.hidden = true;
        launcher.setAttribute('aria-expanded', 'true');
        document.body.classList.add('assistant-open');

        if (!started) {
            started = true;
            addBot(t('assistant.greeting', 'Hi! How can I help?'));
            var chips = [];
            ['pricing', 'shipping', 'sizes', 'design'].forEach(function (k) {
                var v = t('assistant.chip.' + k, '');
                if (v) chips.push(v);
            });
            addChips(chips);
        }
        if (input) input.focus();
    }

    function close() {
        panel.hidden = true;
        launcher.hidden = false;
        launcher.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('assistant-open');
        launcher.focus();
    }

    launcher.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) close();
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            ask(input ? input.value : '');
        });
    }
})();
