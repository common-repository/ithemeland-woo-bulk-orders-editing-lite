<?php

namespace wobel\classes\services\order\update;

defined('ABSPATH') || exit(); // Exit if accessed directly

interface Handler_Interface
{
    public static function get_instance();

    public function update($order_ids, $update_data);
}
