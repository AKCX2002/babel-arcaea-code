<?php
/**
 * Babel Arcaea Code — Gutenberg Block Registration
 *
 * @package Babel_Arcaea_Code
 * @since   1.7.0
 */

namespace BabelArcaeaCode;

defined('ABSPATH') || exit;

class Blocks {

    public function __construct() {
        $opts = Plugin::init()->options()->get();
        if (!$opts['enabled']) return;

        \add_action('init', [$this, 'register']);
    }

    /** Register all blocks from block.json metadata. */
    public function register(): void {
        $dir = BAC_PLUGIN_DIR . 'blocks';

        // Mermaid block
        if (\file_exists($dir . '/mermaid/block.json')) {
            \register_block_type($dir . '/mermaid');
        }
    }
}
