<?php

namespace wobel\classes\repositories\order;

defined('ABSPATH') || exit(); // Exit if accessed directly

interface Order_Interface
{
    public function get_orders($args);
}
