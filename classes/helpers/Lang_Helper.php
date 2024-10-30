<?php

namespace wobel\classes\helpers;

defined('ABSPATH') || exit(); // Exit if accessed directly

class Lang_Helper
{
    public static function get_js_strings()
    {
        return [
            'success' => __('Success !', 'ithemeland-woocommerce-bulk-orders-editing-lite'),
        ];
    }
}
