(function () {
    var storageKey = 'inflacionmundial-theme';
    var root = document.documentElement;
    var toggle = document.querySelector('[data-theme-toggle]');
    var revealItems = document.querySelectorAll('[data-reveal]');

    function currentTheme() {
        var theme = root.getAttribute('data-theme');
        return theme === 'light' ? 'light' : 'dark';
    }

    function updateToggle(theme) {
        if (!toggle) {
            return;
        }

        var label = toggle.querySelector('.theme-toggle-label');
        var hint = toggle.querySelector('.theme-toggle-hint');
        var isDark = theme === 'dark';

        toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');

        if (label) {
            label.innerHTML = isDark ? 'Modo oscuro' : 'Modo claro';
        }

        if (hint) {
            hint.innerHTML = isDark ? 'Cambiar a claro' : 'Cambiar a oscuro';
        }
    }

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        updateToggle(theme);

        try {
            if (window.localStorage) {
                localStorage.setItem(storageKey, theme);
            }
        } catch (error) {
        }
    }

    function revealSections() {
        var index;

        for (index = 0; index < revealItems.length; index++) {
            (function (position) {
                window.setTimeout(function () {
                    if (revealItems[position]) {
                        revealItems[position].className += revealItems[position].className.indexOf('is-visible') === -1 ? ' is-visible' : '';
                    }
                }, position * 80);
            }(index));
        }
    }

    updateToggle(currentTheme());

    if (toggle) {
        toggle.onclick = function () {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', revealSections);
    } else {
        revealSections();
    }
}());
