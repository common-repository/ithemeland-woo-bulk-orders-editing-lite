<?php

namespace wobel\classes\helpers;

defined('ABSPATH') || exit(); // Exit if accessed directly

use KhanhIceTea\Twigeval\Calculator;

class Formula
{
    public function calculate($input, $params)
    {
        $calculator = new Calculator(false);
        return $calculator->number($input, $params);
    }
}
