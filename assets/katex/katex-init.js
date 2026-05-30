/**
 * Babel Arcaea Code — KaTeX JS init
 *
 * Renders KaTeX math inline ($...$) and display ($$...$$) blocks.
 * Uses KaTeX auto-render for automatic DOM scanning.
 *
 * @package Babel_Arcaea_Code
 */
(function () {
    'use strict';

    const LOG = '[Babel Arcaea Code: KaTeX]';
    const config = window.BAC_Config || {};

    function asBool(value, fallback) {
        if (typeof value === 'boolean') return value;
        if (value === 1 || value === '1') return true;
        if (value === 0 || value === '0') return false;
        return fallback;
    }

    /* ── KaTeX auto-render ── */
    function renderKatex(root) {
        if (!asBool(config.katexEnabled, false)) return;

        const scope = root && root.querySelectorAll ? root : document;

        if (typeof renderMathInElement === 'undefined') {
            console.warn(LOG, 'KaTeX auto-render not loaded.');
            return;
        }

        try {
            renderMathInElement(scope.body || scope, {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '$', right: '$', display: false },
                    { left: '\\(', right: '\\)', display: false },
                    { left: '\\[', right: '\\]', display: true },
                ],
                throwOnError: false,
                errorColor: '#cc0000',
                strict: false,
                trust: false,
            });
            console.log(LOG, 'rendered');
        } catch (e) {
            console.warn(LOG, 'render failed:', e);
        }
    }

    /* ── Boot ── */
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        renderKatex(document);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            renderKatex(document);
        });
    }

    /* PJAX support */
    document.addEventListener('pjax:complete', function () {
        renderKatex(document);
    });
    document.addEventListener('pjax:end', function () {
        renderKatex(document);
    });
})();
