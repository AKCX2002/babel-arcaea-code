<?php
namespace BabelArcaeaCode; defined('ABSPATH') || exit;

class Headers { public function __construct() { \add_action('send_headers',[$this,'apply'],1); }
    public function apply(): void { \header('X-Content-Type-Options: nosniff'); \header_remove('X-Powered-By'); \header_remove('X-Frame-Options'); \header_remove('X-XSS-Protection'); foreach(\headers_list() as $h){if(\stripos($h,'Content-Security-Policy:')===0){if(\trim(\substr($h,\strlen('Content-Security-Policy:')))==='')\header_remove('Content-Security-Policy');}} \header_remove('Expires'); \header_remove('Pragma'); \header('Cache-Control: private, max-age=0'); }
}
