<?php

use wobel\classes\helpers\Sanitizer;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

$item = (!empty($item)) ? $item : $parent;
echo (!empty($column_provider) && is_object($column_provider)) ? wp_kses($column_provider->get_item_columns($item, $columns), Sanitizer::allowed_html_tags()) : '';
