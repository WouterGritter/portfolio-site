(function() {
    var hljsBase = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/';
    var modes = ['auto', 'light', 'dark'];
    var icons = { auto: 'fa-circle-half-stroke', light: 'fa-sun', dark: 'fa-moon' };

    function isDark(pref) {
        if (pref === 'dark') return true;
        if (pref === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function giscusTheme(dark) {
        return dark ? 'dark' : 'light';
    }

    // Tell an already loaded giscus comment frame to switch theme.
    function updateGiscusTheme(dark) {
        var frame = document.querySelector('iframe.giscus-frame');
        if (!frame) return;
        frame.contentWindow.postMessage({ giscus: { setConfig: { theme: giscusTheme(dark) } } }, 'https://giscus.app');
    }

    // Load the giscus comment widget into the .giscus container emitted by
    // template.php. Done here instead of in the HTML so the script tag can be
    // given the active theme, which giscus only reads at load time.
    function loadGiscus() {
        var container = document.querySelector('.giscus');
        if (!container) return;

        var script = document.createElement('script');
        script.src = 'https://giscus.app/client.js';
        script.async = true;
        script.crossOrigin = 'anonymous';
        Object.keys(container.dataset).forEach(function(key) {
            script.dataset[key] = container.dataset[key];
        });
        script.dataset.theme = giscusTheme(document.documentElement.classList.contains('dark-theme'));
        container.appendChild(script);
    }

    function applyTheme(pref) {
        var dark = isDark(pref);
        document.documentElement.classList.toggle('dark-theme', dark);
        document.getElementById('hljs-theme').href = hljsBase + (dark ? 'github-dark' : 'github') + '.min.css';
        updateGiscusTheme(dark);
        var btn = document.getElementById('theme-toggle');
        if (btn) {
            var icon = btn.querySelector('i');
            icon.className = 'fa-solid ' + icons[pref];
            btn.title = 'Theme: ' + pref;
        }
    }

    // Apply hljs theme immediately (dark-theme class was set by earlier script)
    if (document.documentElement.classList.contains('dark-theme')) {
        document.getElementById('hljs-theme').href = hljsBase + 'github-dark.min.css';
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Fill in code blocks whose entire content is a "gist:<url>" marker
        // with the live raw content of that GitHub gist, then highlight.
        var GIST_MARKER = /^gist:\s*(\S+)\s*$/i;

        function gistRawUrl(markerUrl) {
            var m = markerUrl.match(/gist\.github\.com\/([^\/\s]+)\/([0-9a-f]+)/i);
            if (!m) return null;

            var url = 'https://gist.githubusercontent.com/' + m[1] + '/' + m[2] + '/raw';

            var fileMatch = markerUrl.match(/[#?]file=([^&]+)/i);
            if (fileMatch) url += '/' + encodeURIComponent(decodeURIComponent(fileMatch[1]));

            return url;
        }

        var pending = [];
        document.querySelectorAll('pre code').forEach(function(block) {
            var match = block.textContent.trim().match(GIST_MARKER);
            if (!match) return;

            var rawUrl = gistRawUrl(match[1]);
            if (!rawUrl) return;

            block.textContent = 'Loading gist content...';

            pending.push(fetch(rawUrl).then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            }).then(function(content) {
                block.textContent = content.replace(/\n$/, '');
            }).catch(function(err) {
                block.textContent = 'Failed to load gist content (' + err.message + ').\nView it at: ' + match[1];
            }));
        });

        // Syntax highlighting (waits for any gist fetches so they get highlighted too)
        Promise.all(pending).finally(function() {
            document.querySelectorAll('pre code[class*="language-"]').forEach(function(block) {
                hljs.highlightElement(block);
            });
        });

        // Theme toggle button
        var current = localStorage.getItem('theme') || 'auto';
        var btn = document.getElementById('theme-toggle');
        applyTheme(current);
        loadGiscus();

        btn.addEventListener('click', function() {
            var idx = modes.indexOf(current);
            current = modes[(idx + 1) % 3];
            localStorage.setItem('theme', current);
            applyTheme(current);
        });

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
            if (current === 'auto') applyTheme('auto');
        });
    });
})();
