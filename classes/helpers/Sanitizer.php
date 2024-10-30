<?php

namespace wobel\classes\helpers;

defined('ABSPATH') || exit(); // Exit if accessed directly

class Sanitizer
{
    public static function array($val)
    {
        $sanitized = null;
        if (is_array($val)) {
            if (count($val) > 0) {
                foreach ($val as $key => $value) {
                    $sanitized[$key] = (is_array($value)) ? self::array($value) : sprintf("%s", stripslashes($value));
                }
            }
        } else {
            $sanitized = sprintf("%s", stripslashes($val));
        }
        return $sanitized;
    }

    public static function number($input)
    {
        return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function allowed_html_tags()
    {
        $allowed = wp_kses_allowed_html('post');

        $allowed['input']['data-*'] = true;
        $allowed['input']['checked'] = true;
        $allowed['input']['disabled'] = true;
        $allowed['input']['type'] = true;
        $allowed['input']['id'] = true;
        $allowed['input']['class'] = true;
        $allowed['input']['placeholder'] = true;
        $allowed['input']['style'] = true;
        $allowed['input']['value'] = true;
        $allowed['input']['name'] = true;

        $allowed['label']['data-*'] = true;
        $allowed['label']['class'] = true;
        $allowed['label']['id'] = true;
        $allowed['label']['for'] = true;
        $allowed['label']['style'] = true;

        $allowed['option']['data-*'] = true;
        $allowed['option']['value'] = true;
        $allowed['option']['selected'] = true;
        $allowed['option']['disabled'] = true;

        $allowed['span']['data-*'] = true;
        $allowed['span']['class'] = true;
        $allowed['span']['style'] = true;
        $allowed['span']['id'] = true;

        $allowed['svg']['width'] = true;
        $allowed['svg']['height'] = true;
        $allowed['svg']['xmlns'] = true;
        $allowed['svg']['version'] = true;
        $allowed['svg']['viewbox'] = true;
        $allowed['svg']['preserveaspectratio'] = true;

        $allowed['path']['d'] = true;
        $allowed['path']['fill'] = true;

        $allowed['g']['transform'] = true;
        $allowed['g']['stroke'] = true;
        $allowed['g']['fill'] = true;

        $allowed['li']['data-*'] = true;
        $allowed['li']['class'] = true;
        $allowed['li']['style'] = true;
        $allowed['li']['id'] = true;

        $allowed['ul']['data-*'] = true;
        $allowed['ul']['class'] = true;
        $allowed['ul']['style'] = true;
        $allowed['ul']['id'] = true;

        $allowed['i']['data-*'] = true;
        $allowed['i']['class'] = true;
        $allowed['i']['style'] = true;
        $allowed['i']['id'] = true;

        $allowed['select']['data-*'] = true;
        $allowed['select']['class'] = true;
        $allowed['select']['name'] = true;
        $allowed['select']['id'] = true;
        $allowed['select']['disabled'] = true;
        $allowed['select']['multiple'] = true;
        $allowed['select']['style'] = true;
        $allowed['select']['title'] = true;

        $allowed['button']['data-*'] = true;
        $allowed['button']['class'] = true;
        $allowed['button']['type'] = true;
        $allowed['button']['name'] = true;
        $allowed['button']['id'] = true;
        $allowed['button']['disabled'] = true;
        $allowed['button']['style'] = true;
        $allowed['button']['title'] = true;

        $allowed['textarea']['data-*'] = true;
        $allowed['textarea']['title'] = true;
        $allowed['textarea']['placeholder'] = true;
        $allowed['textarea']['name'] = true;
        $allowed['textarea']['disabled'] = true;

        $allowed['div']['style'] = true;
        $allowed['div']['data-*'] = true;
        $allowed['div']['class'] = true;
        $allowed['div']['id'] = true;

        $allowed['a']['data-*'] = true;
        $allowed['a']['class'] = true;
        $allowed['a']['style'] = true;
        $allowed['a']['id'] = true;
        $allowed['a']['href'] = true;
        $allowed['a']['target'] = true;

        $allowed['table']['style'] = true;
        $allowed['table']['data-*'] = true;
        $allowed['table']['class'] = true;
        $allowed['table']['id'] = true;

        $allowed['thead']['style'] = true;
        $allowed['thead']['data-*'] = true;
        $allowed['thead']['class'] = true;
        $allowed['thead']['id'] = true;

        $allowed['tbody']['style'] = true;
        $allowed['tbody']['data-*'] = true;
        $allowed['tbody']['class'] = true;
        $allowed['tbody']['id'] = true;

        $allowed['tr']['data-*'] = true;
        $allowed['tr']['class'] = true;
        $allowed['tr']['id'] = true;
        $allowed['tr']['style'] = true;

        $allowed['th']['data-*'] = true;
        $allowed['th']['class'] = true;
        $allowed['th']['id'] = true;
        $allowed['th']['style'] = true;

        $allowed['td']['data-*'] = true;
        $allowed['td']['class'] = true;
        $allowed['td']['id'] = true;
        $allowed['td']['style'] = true;
        
        $allowed['style'] = [];
        $allowed['form']['action'] = true;
        $allowed['form']['method'] = true;

        return $allowed;
    }
}
