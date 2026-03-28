(function () {
    var storageKey = 'inflacionmundial-theme';
    var root = document.documentElement;
    var body = document.body;
    var toggle = document.querySelector('[data-theme-toggle]');
    var revealItems = document.querySelectorAll('[data-reveal]');
    var calculatorForms = document.querySelectorAll('[data-calculator-form]');

    function eachNode(nodes, callback) {
        var index;

        for (index = 0; index < nodes.length; index++) {
            callback(nodes[index], index);
        }
    }

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
        eachNode(revealItems, function (item, position) {
            if (item) {
                item.style.transitionDelay = (position * 120) + 'ms';
            }

            window.setTimeout(function () {
                if (item && item.className.indexOf('is-visible') === -1) {
                    item.className += ' is-visible';
                }
            }, 140 + (position * 120));
        });
    }

    function getRequestUrl(form) {
        var action = form.getAttribute('action') || window.location.href;
        var hashIndex = action.indexOf('#');

        if (hashIndex === -1) {
            return action;
        }

        return action.substring(0, hashIndex);
    }

    function getCalculatorCard(element) {
        while (element && element !== document) {
            if (element.getAttribute && element.getAttribute('data-calculator-card')) {
                return element;
            }

            element = element.parentNode;
        }

        return null;
    }

    function findCalculatorCard(calculator) {
        return document.querySelector('[data-calculator-card="' + calculator + '"]');
    }

    function findFeedbackNode(calculator) {
        return document.querySelector('[data-calculator-feedback="' + calculator + '"]');
    }

    function setActiveCalculator(calculator) {
        eachNode(document.querySelectorAll('[data-calculator-card]'), function (card) {
            if (card.getAttribute('data-calculator-card') === calculator) {
                card.className += card.className.indexOf('is-active') === -1 ? ' is-active' : '';
            } else {
                card.className = card.className.replace(/\s?is-active/g, '');
            }
        });

        if (body) {
            body.setAttribute('data-active-calculator', calculator);
        }
    }

    function clearOtherFeedback(activeCalculator) {
        eachNode(document.querySelectorAll('[data-calculator-feedback]'), function (feedback) {
            if (feedback.getAttribute('data-calculator-feedback') !== activeCalculator) {
                feedback.innerHTML = '';
            }
        });
    }

    function keepCardVisible(card) {
        if (!card || !card.getBoundingClientRect) {
            return;
        }

        var rect = card.getBoundingClientRect();
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

        if (rect.top >= 24 && rect.bottom <= viewportHeight - 24) {
            return;
        }

        try {
            card.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        } catch (error) {
            card.scrollIntoView();
        }
    }

    function updateUrl(baseUrl, anchor) {
        if (!anchor) {
            return;
        }

        if (window.history && history.replaceState) {
            history.replaceState(null, document.title, baseUrl + '#' + anchor);
            return;
        }

        window.location.hash = anchor;
    }

    function setSubmitState(form, isLoading) {
        var submitButton = form.querySelector('button[type="submit"]') || form.querySelector('button');
        var card = getCalculatorCard(form);

        if (card) {
            card.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }

        if (!submitButton) {
            return;
        }

        if (!submitButton.getAttribute('data-label-default')) {
            submitButton.setAttribute('data-label-default', submitButton.innerHTML);
        }

        submitButton.disabled = isLoading;
        submitButton.className = isLoading
            ? submitButton.className + (submitButton.className.indexOf('is-loading') === -1 ? ' is-loading' : '')
            : submitButton.className.replace(/\s?is-loading/g, '');
        submitButton.innerHTML = isLoading ? 'Calculando...' : submitButton.getAttribute('data-label-default');
    }

    function submitCalculator(form) {
        var calculatorInput = form.querySelector('input[name="calculator"]');
        var calculator = calculatorInput ? calculatorInput.value : '';
        var requestUrl = getRequestUrl(form);
        var formData;

        if (!calculator || !window.fetch || !window.FormData) {
            form.submit();
            return;
        }

        formData = new FormData(form);
        formData.append('async', '1');

        setSubmitState(form, true);

        window.fetch(requestUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.text();
        }).then(function (text) {
            var payload = JSON.parse(text);
            var activeCalculator = payload.calculator || calculator;
            var feedbackNode = findFeedbackNode(activeCalculator);
            var activeCard = findCalculatorCard(activeCalculator);

            clearOtherFeedback(activeCalculator);
            setActiveCalculator(activeCalculator);

            if (feedbackNode) {
                feedbackNode.innerHTML = payload.feedbackHtml || '';
            }

            updateUrl(requestUrl, payload.anchor);
            keepCardVisible(activeCard);
            setSubmitState(form, false);
        }).catch(function () {
            setSubmitState(form, false);
            form.submit();
        });
    }

    function bindCalculatorForms() {
        eachNode(calculatorForms, function (form) {
            form.onsubmit = function (event) {
                if (event && event.preventDefault) {
                    event.preventDefault();
                }

                submitCalculator(form);
                return false;
            };
        });
    }

    updateToggle(currentTheme());

    if (toggle) {
        toggle.onclick = function () {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        };
    }

    bindCalculatorForms();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', revealSections);
    } else {
        revealSections();
    }
}());
