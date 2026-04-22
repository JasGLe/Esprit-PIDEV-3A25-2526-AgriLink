(function () {
    var locale = (document.documentElement.getAttribute('lang') || 'fr').toLowerCase();
    if (locale === 'fr') {
        return;
    }

    var path = window.location.pathname || '';
    var isMarketplaceModule = path.startsWith('/marketplace')
        || path.startsWith('/mes-commandes')
        || path.startsWith('/mes-locations')
        || path.startsWith('/boutique');
    if (!isMarketplaceModule) {
        return;
    }

    var translatedTextNodes = new WeakSet();
    var translatedAttrByElement = new WeakMap();
    var translationCache = new Map();
    var inFlight = false;
    var pendingRun = false;
    var ATTRS = ['placeholder', 'title', 'aria-label'];

    function isTranslatableText(value) {
        if (!value) {
            return false;
        }
        var txt = value.replace(/\s+/g, ' ').trim();
        if (!txt || txt.length < 2) {
            return false;
        }
        if (/^[\d\s.,:%+#\-_/()]+$/.test(txt)) {
            return false;
        }
        return /[A-Za-zÀ-ÖØ-öø-ÿ]/.test(txt);
    }

    function collectTranslatableEntries(root) {
        var textEntries = [];
        var attrEntries = [];

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node || !node.parentElement) {
                    return NodeFilter.FILTER_REJECT;
                }
                if (translatedTextNodes.has(node)) {
                    return NodeFilter.FILTER_REJECT;
                }
                var parent = node.parentElement;
                var tag = parent.tagName;
                if (!tag) {
                    return NodeFilter.FILTER_REJECT;
                }
                if (parent.closest('[data-no-auto-translate]')) {
                    return NodeFilter.FILTER_REJECT;
                }
                if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'CODE', 'PRE', 'TEXTAREA'].indexOf(tag) !== -1) {
                    return NodeFilter.FILTER_REJECT;
                }
                if (!isTranslatableText(node.nodeValue || '')) {
                    return NodeFilter.FILTER_REJECT;
                }

                return NodeFilter.FILTER_ACCEPT;
            }
        });

        var current;
        while ((current = walker.nextNode())) {
            textEntries.push({ node: current, text: (current.nodeValue || '').trim() });
        }

        var elements = root.querySelectorAll('*');
        elements.forEach(function (el) {
            if (el.closest('[data-no-auto-translate]')) {
                return;
            }
            var attrSet = translatedAttrByElement.get(el);
            if (!attrSet) {
                attrSet = new Set();
                translatedAttrByElement.set(el, attrSet);
            }
            ATTRS.forEach(function (attr) {
                if (attrSet.has(attr) || !el.hasAttribute(attr)) {
                    return;
                }
                var value = (el.getAttribute(attr) || '').trim();
                if (!isTranslatableText(value)) {
                    return;
                }
                attrEntries.push({ el: el, attr: attr, text: value });
            });
        });

        return { textEntries: textEntries, attrEntries: attrEntries };
    }

    function fetchTranslations(texts) {
        return fetch('/marketplace/i18n/translate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target: locale,
                texts: texts
            })
        })
            .then(function (response) { return response.ok ? response.json() : { translations: {} }; })
            .then(function (json) { return (json && json.translations) ? json.translations : {}; })
            .catch(function () { return {}; });
    }

    async function translateRoot(root) {
        if (inFlight) {
            pendingRun = true;
            return;
        }

        inFlight = true;
        try {
            var entries = collectTranslatableEntries(root || document.body);
            var allTexts = [];
            entries.textEntries.forEach(function (entry) { allTexts.push(entry.text); });
            entries.attrEntries.forEach(function (entry) { allTexts.push(entry.text); });

            var uniques = Array.from(new Set(allTexts)).filter(function (txt) { return !translationCache.has(txt); });
            if (uniques.length > 0) {
                var serverTranslations = await fetchTranslations(uniques);
                Object.keys(serverTranslations).forEach(function (key) {
                    translationCache.set(key, serverTranslations[key] || key);
                });
                uniques.forEach(function (txt) {
                    if (!translationCache.has(txt)) {
                        translationCache.set(txt, txt);
                    }
                });
            }

            entries.textEntries.forEach(function (entry) {
                var translated = translationCache.get(entry.text);
                if (translated && entry.node.nodeValue !== translated) {
                    entry.node.nodeValue = translated;
                }
                translatedTextNodes.add(entry.node);
            });

            entries.attrEntries.forEach(function (entry) {
                var translated = translationCache.get(entry.text);
                if (translated) {
                    entry.el.setAttribute(entry.attr, translated);
                }
                var attrSet = translatedAttrByElement.get(entry.el);
                if (!attrSet) {
                    attrSet = new Set();
                    translatedAttrByElement.set(entry.el, attrSet);
                }
                attrSet.add(entry.attr);
            });
        } finally {
            inFlight = false;
            if (pendingRun) {
                pendingRun = false;
                translateRoot(document.body);
            }
        }
    }

    function scheduleTranslate() {
        clearTimeout(scheduleTranslate._timer);
        scheduleTranslate._timer = setTimeout(function () {
            translateRoot(document.body);
        }, 250);
    }

    translateRoot(document.body);

    var observer = new MutationObserver(function () {
        scheduleTranslate();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();
