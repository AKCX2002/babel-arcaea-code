<?php
/**
 * Babel Arcaea Code — Security & Performance HTTP Headers
 *
 * Adds recommended security headers and removes disallowed ones.
 * Uses WordPress 'send_headers' action hook.
 *
 * @package Babel_Arcaea_Code
 */

if (!defined('ABSPATH')) exit;

add_action('send_headers', function () {
    // ── Security ──

    // Prevent MIME-type sniffing.
    header('X-Content-Type-Options: nosniff');

    // Remove X-Powered-By (try to unset; some servers override this).
    header_remove('X-Powered-By');

    // Remove X-Frame-Options — prefer CSP frame-ancestors at server/theme level.
    header_remove('X-Frame-Options');

    // Remove X-XSS-Protection (legacy header, superseded by CSP).
    header_remove('X-XSS-Protection');

    // Remove any unconfigured/empty Content-Security-Policy headers.
    // Site administrators should configure CSP at the web-server level.
    // We only remove headers that are empty or contain only default values.
    $csp_headers = headers_list();
    foreach ($csp_headers as $h) {
        if (stripos($h, 'Content-Security-Policy:') === 0) {
            $value = trim(substr($h, strlen('Content-Security-Policy:')));
            // Remove only if empty or contains only whitespace.
            if ($value === '') {
                header_remove('Content-Security-Policy');
            }
        }
    }

    // ── Performance ──

    // Remove Expires — Cache-Control is the modern standard.
    header_remove('Expires');
    // Remove Pragma (legacy, superseded by Cache-Control).
    header_remove('Pragma');

    // Ensure a sensible Cache-Control default is present.
    header('Cache-Control: private, max-age=0');
}, 1);
