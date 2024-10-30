<?php

namespace wobel\classes\helpers;

defined('ABSPATH') || exit(); // Exit if accessed directly

use wobel\classes\helpers\Formula;

class Order_Helper
{
    public static function round($value, $round_item)
    {
        $division = intval('1' . str_repeat('0', wc_get_price_decimals()));
        switch ($round_item) {
            case 5:
            case 10:
                $value += floatval($round_item / $division);
                $decimals = floatval($value - floor($value));
                $value = floor($value) + ($decimals - floatval(intval(($decimals * $division) . '') % $round_item) / $division);
                break;
            case 9:
            case 19:
            case 29:
            case 39:
            case 49:
            case 59:
            case 69:
            case 79:
            case 89:
            case 99:
                $value = intval($value) + floatval($round_item / $division);
                break;
            default:
                break;
        }

        return $value;
    }

    public static function orders_id_parser($ids)
    {
        $output = '';
        $ids_array = explode('|', $ids);
        if (is_array($ids_array) && !empty($ids_array)) {
            foreach ($ids_array as $item) {
                $output .= self::parser($item);
            }
        } else {
            $output .= self::parser($ids_array);
        }

        return rtrim($output, ',');
    }

    private static function parser($ids_string)
    {
        $output = '';
        if (strpos($ids_string, '-') > 0) {
            $from_to = explode('-', $ids_string);
            if (isset($from_to[0]) && isset($from_to[1])) {
                for ($i = intval($from_to[0]); $i <= intval($from_to[1]); $i++) {
                    $output .= $i . ',';
                }
            }
        } else {
            $output = $ids_string . ',';
        }

        return $output;
    }

    public static function get_tax_query($taxonomy, $terms, $operator = null, $field = null)
    {
        $field = !empty($field) ? $field : 'slug';
        $values = (is_array($terms)) ? array_map('urldecode', $terms) : $terms;
        switch ($operator) {
            case null:
                $tax_item = [
                    'taxonomy' => urldecode($taxonomy),
                    'field' => $field,
                    'terms' => $values,
                    'operator' => 'AND'
                ];
                break;
            case 'or':
                $tax_item = [
                    'taxonomy' => urldecode($taxonomy),
                    'field' => $field,
                    'terms' => $values,
                    'operator' => 'IN'
                ];
                break;
            case 'and':
                $tax_item['relation'] = 'AND';
                if (is_array($values) && !empty($values)) {
                    foreach ($values as $value) {
                        $tax_item[] = [
                            'taxonomy' => urldecode($taxonomy),
                            'field' => $field,
                            'terms' => [$value],
                        ];
                    }
                }
                break;
            case 'not_in':
                $tax_item = [
                    'taxonomy' => urldecode($taxonomy),
                    'field' => $field,
                    'terms' => $values,
                    'operator' => 'NOT IN'
                ];
                break;
        }
        return $tax_item;
    }

    public static function set_filter_data_items($data, $others = null)
    {
        $args = [];
        if (!is_null($others)) {
            $args = $others;
        }
        if (isset($data['search_type']) && $data['search_type'] == 'quick_search') {
            if (isset($data['quick_search_text']) && !empty($data['quick_search_text'])) {
                switch ($data['quick_search_field']) {
                    case 'id':
                        $ids = self::orders_id_parser($data['quick_search_text']);
                        $args['order_ids'] = [
                            'value' => $ids,
                            'operator' => "in"
                        ];
                        break;
                }
            }
        } else {
            if (isset($data['order_ids']) && !empty($data['order_ids']['value'])) {
                $ids = self::orders_id_parser($data['order_ids']['value']);
                $args['order_ids'] = [
                    'value' => $ids,
                    'operator' => "in"
                ];
            }
            if (isset($data['order_status']) && !empty($data['order_status']['value'])) {
                $args['status'] = [
                    'value' => (is_array($data['order_status']['value'])) ? array_map('sanitize_text_field', $data['order_status']['value']) : sanitize_text_field($data['order_status']['value']),
                    'operator' => 'in'
                ];
            }
            if (isset($data['date_created']) && (!empty($data['date_created']['from']) || !empty(!empty($data['date_created']['to'])))) {
                $from = (!empty($data['date_created']['from'])) ? gmdate('Y-m-d H:i:s', strtotime($data['date_created']['from'])) : null;
                $to = (!empty($data['date_created']['to'])) ? gmdate('Y-m-d H:i:s', strtotime($data['date_created']['to'])) : null;

                if (!empty($from) & !empty($to)) {
                    $value = [$from, $to];
                    $operator = 'BETWEEN';
                } else if (!empty($from)) {
                    $value = $from;
                    $operator = '>=';
                } else {
                    $value = $to;
                    $operator = '<=';
                }

                $args['date_created'] = [
                    'value' => $value,
                    'operator' => $operator,
                ];
            }
            if (isset($data['date_modified']) && (!empty($data['date_modified']['from']) || !empty(!empty($data['date_modified']['to'])))) {
                $from = (!empty($data['date_modified']['from'])) ? gmdate('Y-m-d H:i:s', strtotime($data['date_modified']['from'])) : null;
                $to = (!empty($data['date_modified']['to'])) ? gmdate('Y-m-d H:i:s', strtotime($data['date_modified']['to'])) : null;
                if (!empty($from) & !empty($to)) {
                    $value = [$from, $to];
                    $operator = 'BETWEEN';
                } else if (!empty($from)) {
                    $value = $from;
                    $operator = '>=';
                } else {
                    $value = $to;
                    $operator = '<=';
                }

                $args['date_modified'] = [
                    'value' => $value,
                    'operator' => $operator,
                ];
            }
            if (isset($data['date_paid']) && (!empty($data['date_paid']['from']) || !empty(!empty($data['date_paid']['to'])))) {
                $from = (!empty($data['date_paid']['from'])) ? gmdate('Y-m-d H:i:s', strtotime($data['date_paid']['from'])) : null;
                $to = (!empty($data['date_paid']['to'])) ? gmdate('Y-m-d H:i:s', strtotime($data['date_paid']['to'])) : null;
                if (!empty($from) & !empty($to)) {
                    $value = [$from, $to];
                    $operator = 'BETWEEN';
                } else if (!empty($from)) {
                    $value = $from;
                    $operator = '>=';
                } else {
                    $value = $to;
                    $operator = '<=';
                }
                $args['date_paid'] = [
                    'value' => $value,
                    'operator' => $operator,
                ];
            }
            if (!empty($data['customer_ip_address']['value'])) {
                $args['customer_ip_address'] = [
                    'value' => sanitize_text_field($data['customer_ip_address']['value']),
                    'operator' => sanitize_text_field($data['customer_ip_address']['operator']),
                ];
            }
            if (isset($data['billing_address_1']['value']) && $data['billing_address_1']['value'] != '') {
                $args['billing_address_1'] = [
                    'value' => sanitize_text_field($data['billing_address_1']['value']),
                    'operator' => sanitize_text_field($data['billing_address_1']['operator']),
                ];
            }
            if (isset($data['billing_address_2']['value']) && $data['billing_address_2']['value'] != '') {
                $args['billing_address_2'] = [
                    'value' => sanitize_text_field($data['billing_address_2']['value']),
                    'operator' => sanitize_text_field($data['billing_address_2']['operator']),
                ];
            }
            if (isset($data['billing_city']['value']) && $data['billing_city']['value'] != '') {
                $args['billing_city'] = [
                    'value' => sanitize_text_field($data['billing_city']['value']),
                    'operator' => sanitize_text_field($data['billing_city']['operator']),
                ];
            }
            if (isset($data['billing_company']['value']) && $data['billing_company']['value'] != '') {
                $args['billing_company'] = [
                    'value' => sanitize_text_field($data['billing_company']['value']),
                    'operator' => sanitize_text_field($data['billing_company']['operator']),
                ];
            }
            if (isset($data['billing_country']['value']) && $data['billing_country']['value'] != '') {
                $args['billing_country'] = [
                    'value' => sanitize_text_field($data['billing_country']['value']),
                    'operator' => '='
                ];
            }
            if (isset($data['billing_state']['value']) && $data['billing_state']['value'] != '') {
                $args['billing_state'] = [
                    'value' => sanitize_text_field($data['billing_state']['value']),
                    'operator' => '='
                ];
            }
            if (isset($data['billing_email']['value']) && $data['billing_email']['value'] != '') {
                $args['billing_email'] = [
                    'value' => sanitize_text_field($data['billing_email']['value']),
                    'operator' => sanitize_text_field($data['billing_email']['operator']),
                ];
            }
            if (isset($data['billing_phone']['value']) && $data['billing_phone']['value'] != '') {
                $args['billing_phone'] = [
                    'value' => sanitize_text_field($data['billing_phone']['value']),
                    'operator' => sanitize_text_field($data['billing_phone']['operator']),
                ];
            }
            if (isset($data['billing_first_name']['value']) && $data['billing_first_name']['value'] != '') {
                $args['billing_first_name'] = [
                    'value' => sanitize_text_field($data['billing_first_name']['value']),
                    'operator' => sanitize_text_field($data['billing_first_name']['operator']),
                ];
            }
            if (isset($data['billing_last_name']['value']) && $data['billing_last_name']['value'] != '') {
                $args['billing_last_name'] = [
                    'value' => sanitize_text_field($data['billing_last_name']['value']),
                    'operator' => sanitize_text_field($data['billing_last_name']['operator']),
                ];
            }
            if (isset($data['billing_postcode']['value']) && $data['billing_postcode']['value'] != '') {
                $args['billing_postcode'] = [
                    'value' => sanitize_text_field($data['billing_postcode']['value']),
                    'operator' => sanitize_text_field($data['billing_postcode']['operator']),
                ];
            }
            if (isset($data['shipping_address_1']['value']) && $data['shipping_address_1']['value'] != '') {
                $args['shipping_address_1'] = [
                    'value' => sanitize_text_field($data['shipping_address_1']['value']),
                    'operator' => sanitize_text_field($data['shipping_address_1']['operator']),
                ];
            }
            if (isset($data['shipping_address_2']['value']) && $data['shipping_address_2']['value'] != '') {
                $args['shipping_address_2'] = [
                    'value' => sanitize_text_field($data['shipping_address_2']['value']),
                    'operator' => sanitize_text_field($data['shipping_address_2']['operator']),
                ];
            }
            if (isset($data['shipping_city']['value']) && $data['shipping_city']['value'] != '') {
                $args['shipping_city'] = [
                    'value' => sanitize_text_field($data['shipping_city']['value']),
                    'operator' => sanitize_text_field($data['shipping_city']['operator']),
                ];
            }
            if (isset($data['shipping_company']['value']) && $data['shipping_company']['value'] != '') {
                $args['shipping_company'] = [
                    'value' => sanitize_text_field($data['shipping_company']['value']),
                    'operator' => sanitize_text_field($data['shipping_company']['operator']),
                ];
            }
            if (isset($data['shipping_country']['value']) && $data['shipping_country']['value'] != '') {
                $args['shipping_country'] = [
                    'value' => sanitize_text_field($data['shipping_country']['value']),
                    'operator' => '='
                ];
            }
            if (isset($data['shipping_state']['value']) && $data['shipping_state']['value'] != '') {
                $args['shipping_state'] = [
                    'value' => sanitize_text_field($data['shipping_state']['value']),
                    'operator' => '='
                ];
            }
            if (isset($data['shipping_first_name']['value']) && $data['shipping_first_name']['value'] != '') {
                $args['shipping_first_name'] = [
                    'value' => sanitize_text_field($data['shipping_first_name']['value']),
                    'operator' => sanitize_text_field($data['shipping_first_name']['operator']),
                ];
            }
            if (isset($data['shipping_last_name']['value']) && $data['shipping_last_name']['value'] != '') {
                $args['shipping_last_name'] = [
                    'value' => sanitize_text_field($data['shipping_last_name']['value']),
                    'operator' => sanitize_text_field($data['shipping_last_name']['operator']),
                ];
            }
            if (isset($data['shipping_postcode']['value']) && $data['shipping_postcode']['value'] != '') {
                $args['shipping_postcode'] = [
                    'value' => sanitize_text_field($data['shipping_postcode']['value']),
                    'operator' => sanitize_text_field($data['shipping_postcode']['operator']),
                ];
            }
            if (!empty($data['order_currency']['value'])) {
                $args['order_currency'] = [
                    'value' => sanitize_text_field($data['order_currency']['value']),
                    'operator' => '='
                ];
            }
            if (!empty($data['order_total']['from']) || !empty($data['order_total']['to'])) {
                $from = (!empty($data['order_total']['from'])) ? $data['order_total']['from'] : null;
                $to = (!empty($data['order_total']['to'])) ? $data['order_total']['to'] : null;
                if (!empty($from) & !empty($to)) {
                    $value = [$from, $to];
                    $operator = 'BETWEEN';
                } else if (!empty($from)) {
                    $value = $from;
                    $operator = '>=';
                } else {
                    $value = $to;
                    $operator = '<=';
                }
                $args['order_total'] = [
                    'value' => $value,
                    'operator' => $operator,
                ];
            }
            if (!empty($data['order_discount']['from']) || !empty($data['order_discount']['to'])) {
                $from = (!empty($data['order_discount']['from'])) ? $data['order_discount']['from'] : null;
                $to = (!empty($data['order_discount']['to'])) ? $data['order_discount']['to'] : null;
                if (!empty($from) & !empty($to)) {
                    $value = [$from, $to];
                    $operator = 'BETWEEN';
                } else if (!empty($from)) {
                    $value = $from;
                    $operator = '>=';
                } else {
                    $value = $to;
                    $operator = '<=';
                }
                $args['order_discount'] = [
                    'value' => $value,
                    'operator' => $operator,
                ];
            }
            if (!empty($data['order_discount_tax']['from']) || !empty($data['order_discount_tax']['to'])) {
                $from = (!empty($data['order_discount_tax']['from'])) ? $data['order_discount_tax']['from'] : null;
                $to = (!empty($data['order_discount_tax']['to'])) ? $data['order_discount_tax']['to'] : null;
                if (!empty($from) & !empty($to)) {
                    $value = [$from, $to];
                    $operator = 'BETWEEN';
                } else if (!empty($from)) {
                    $value = $from;
                    $operator = '>=';
                } else {
                    $value = $to;
                    $operator = '<=';
                }
                $args['order_discount_tax'] = [
                    'value' => $value,
                    'operator' => $operator,
                ];
            }
            if (!empty($data['created_via']['value'])) {
                $args['created_via'] = [
                    'value' => sanitize_text_field($data['created_via']['value']),
                    'operator' => sanitize_text_field($data['created_via']['operator'])
                ];
            }
            if (!empty($data['payment_method']['value'])) {
                $args['payment_method'] = [
                    'value' => sanitize_text_field($data['payment_method']['value']),
                    'operator' => '='
                ];
            }
            if (!empty($data['shipping_tax']['value'])) {
                $args['order_shipping_tax'] = [
                    'value' => sanitize_text_field($data['shipping_tax']['value']),
                    'operator' => '='
                ];
            }
            if (!empty($data['order_shipping']['value'])) {
                $args['order_shipping'] = [
                    'value' => sanitize_text_field($data['order_shipping']['value']),
                    'operator' => '='
                ];
            }
            if (!empty($data['recorded_coupon_usage_counts']['value'])) {
                $args['wobel_recorded_coupon_usage_counts'] = [
                    'value' => sanitize_text_field($data['recorded_coupon_usage_counts']['value']),
                    'operator' => '='
                ];
            }
            if (!empty($data['order_stock_reduced']['value'])) {
                $args['order_stock_reduced'] = [
                    'value' => sanitize_text_field($data['order_stock_reduced']['value']),
                    'operator' => '='
                ];
            }
            if (!empty($data['prices_include_tax']['value'])) {
                $args['prices_include_tax'] = [
                    'value' => sanitize_text_field($data['prices_include_tax']['value']),
                    'operator' => '='
                ];
            }
            if (!empty($data['recorded_sales']['value'])) {
                $args['recorded_sales'] = [
                    'value' => sanitize_text_field($data['recorded_sales']['value']),
                    'operator' => '='
                ];
            }
            if (!empty($data['products_ids']['value'])) {
                $args['wobel_products_ids'] = [
                    'value' => (is_array($data['products_ids']['value'])) ? array_map('sanitize_text_field', $data['products_ids']['value']) : [sanitize_text_field($data['products_ids']['value'])],
                    'operator' => sanitize_text_field($data['products_ids']['operator']),
                ];
            }
            if (!empty($data['taxonomies']) && is_array($data['taxonomies'])) {
                foreach ($data['taxonomies'] as $taxonomy) {
                    if (!empty($taxonomy['operator']) && !empty($taxonomy['value']) && !empty($taxonomy['taxonomy'])) {
                        $args['wobel_product_taxonomy'][] = [
                            'operator' => sanitize_text_field($taxonomy['operator']),
                            'value' => esc_sql($taxonomy['value']),
                            'taxonomy' => sanitize_text_field($taxonomy['taxonomy'])
                        ];
                    }
                }
            }
            if (isset($data['custom_fields']) && !empty($data['custom_fields'])) {
                $type = 'text';
                foreach ($data['custom_fields'] as $custom_field_item) {
                    switch ($custom_field_item['type']) {
                        case 'from-to-date':
                            $type = 'date';
                            $from = (!empty($custom_field_item['value'][0])) ? gmdate('Y-m-d H:i:s', strtotime($custom_field_item['value'][0])) : null;
                            $to = (!empty($custom_field_item['value'][1])) ? gmdate('Y-m-d H:i:s', strtotime($custom_field_item['value'][1])) : null;
                            if (empty($from) && empty($to)) {
                                $value = null;
                                $operator = null;
                                break;
                            }
                            if (!empty($from) & !empty($to)) {
                                $value = [$from, $to];
                                $operator = 'BETWEEN';
                            } else if (!empty($from)) {
                                $value = $from;
                                $operator = '>=';
                            } else {
                                $value = $to;
                                $operator = '<=';
                            }
                            break;
                        case 'from-to-time':
                            $type = 'time';
                            $from = (!empty($custom_field_item['value'][0])) ? gmdate('H:i', strtotime($custom_field_item['value'][0])) : null;
                            $to = (!empty($custom_field_item['value'][1])) ? gmdate('H:i', strtotime($custom_field_item['value'][1])) : null;
                            if (empty($from) && empty($to)) {
                                $value = null;
                                $operator = null;
                                break;
                            }
                            if (!empty($from) & !empty($to)) {
                                $value = [$from, $to];
                                $operator = 'BETWEEN';
                            } else if (!empty($from)) {
                                $value = $from;
                                $operator = '>=';
                            } else {
                                $value = $to;
                                $operator = '<=';
                            }
                            break;
                        case 'from-to-number':
                            $type = 'number';
                            $from = (!empty($custom_field_item['value'][0])) ? floatval($custom_field_item['value'][0]) : null;
                            $to = (!empty($custom_field_item['value'][1])) ? floatval($custom_field_item['value'][1]) : null;
                            if (empty($from) && empty($to)) {
                                $value = null;
                                $operator = null;
                                break;
                            }
                            if (!empty($from) & !empty($to)) {
                                $value = [$from, $to];
                                $operator = 'BETWEEN';
                            } else if (!empty($from)) {
                                $value = $from;
                                $operator = '>=';
                            } else {
                                $value = $to;
                                $operator = '<=';
                            }
                            break;
                        case 'text':
                            $operator = $custom_field_item['operator'];
                            $value = $custom_field_item['value'];
                            break;
                        case 'select':
                            $operator = "like";
                            $value = $custom_field_item['value'];
                            break;
                    }

                    if (!empty($value)) {
                        if (is_array($value)) {
                            $value = array_filter($value, function ($val) {
                                return !empty($val);
                            });
                        }

                        if (!empty($value)) {
                            $args['wobel_custom_fields'][] = [
                                'key' => $custom_field_item['key'],
                                'value' => $value,
                                'operator' => $operator,
                                'type' => $type
                            ];
                        }
                    }
                }
            }
        }

        return $args;
    }

    public static function apply_operator($old_value, $data)
    {
        if (empty($data['operator'])) {
            return $data['value'];
        }

        $data['value'] = (!empty($data['operator_type'])) ? self::apply_calculator_operator($old_value, $data) : self::apply_default_operator($old_value, $data);
        $data['value'] = (isset($data['round']) && !empty($data['round'])) ? self::round($data['value'], $data['round']) : $data['value'];

        return $data['value'];
    }

    private static function apply_calculator_operator($old_value, $data)
    {
        $old_value = floatval($old_value);
        $data['value'] = floatval($data['value']);
        $data['sale_price'] = (isset($data['sale_price'])) ? floatval($data['sale_price']) : 0;
        $data['regular_price'] = (isset($data['regular_price'])) ? floatval($data['regular_price']) : 0;

        switch ($data['operator_type']) {
            case 'n':
                switch ($data['operator']) {
                    case '+':
                        $data['value'] += $old_value;
                        break;
                    case '-':
                        $data['value'] = $old_value - $data['value'];
                        break;
                    case 'sp+':
                        $data['value'] += $data['sale_price'];
                        break;
                    case 'rp-':
                        $data['value'] = $data['regular_price'] - $data['value'];
                        break;
                }
                break;
            case '%':
                switch ($data['operator']) {
                    case '+':
                        $data['value'] = $old_value + ($old_value * $data['value'] / 100);
                        break;
                    case '-':
                        $data['value'] = $old_value - ($old_value * $data['value'] / 100);
                        break;
                    case 'sp+':
                        $data['value'] = $data['sale_price'] + ($data['sale_price'] * $data['value'] / 100);
                        break;
                    case 'rp-':
                        $data['value'] = $data['regular_price'] - ($data['regular_price'] * $data['value'] / 100);
                        break;
                }
                break;
        }

        return $data['value'];
    }

    private static function apply_default_operator($old_value, $data)
    {
        switch ($data['operator']) {
            case 'text_append':
                $data['value'] = $old_value . $data['value'];
                break;
            case 'text_prepend':
                $data['value'] = $data['value'] . $old_value;
                break;
            case 'text_new':
                $data['value'] = $data['value'];
                break;
            case 'text_delete':
                $data['value'] = str_replace($data['value'], '', $old_value);
                break;
            case 'text_replace':
                if (isset($data['value'])) {
                    $data['value'] = ($data['sensitive'] == 'yes') ? str_replace($data['value'], $data['replace'], $old_value) : str_ireplace($data['value'], $data['replace'], $old_value);
                } else {
                    $data['value'] = $old_value;
                }
                break;
            case 'text_remove_duplicate':
                $data['value'] = $old_value;
                break;
            case 'taxonomy_append':
                $data['value'] = array_unique(array_merge($old_value, $data['value']));
                break;
            case 'taxonomy_replace':
                $data['value'] = $data['value'];
                break;
            case 'taxonomy_delete':
                $data['value'] = array_values(array_diff($old_value, $data['value']));
                break;
            case 'number_new':
                $data['value'] = $data['value'];
                break;
            case 'number_delete':
                $data['value'] = str_replace($data['value'], '', $old_value);
                break;
            case 'text_clear':
                $data['value'] = '';
                break;
            case 'number_clear':
                $data['value'] = '';
                break;
            case 'number_formula':
                $formulaCalculator = new Formula();
                $data['value'] = $formulaCalculator->calculate(strtolower($data['value']), ['x' => $old_value]);
                break;
            case 'increase_by_value':
                $data['value'] = floatval($old_value) + floatval($data['value']);
                break;
            case 'decrease_by_value':
                $data['value'] = floatval($old_value) - floatval($data['value']);
                break;
            case 'increase_by_percent':
                $data['value'] = floatval($old_value) + floatval(floatval($old_value) * floatval($data['value']) / 100);
                break;
            case 'decrease_by_percent':
                $data['value'] = floatval($old_value) - floatval(floatval($old_value) * floatval($data['value']) / 100);
                break;
            case 'increase_by_value_from_sale':
                $data['value'] = (isset($data['sale_price'])) ? floatval($data['sale_price']) + floatval($data['value']) : $data;
                break;
            case 'increase_by_percent_from_sale':
                $data['value'] = (isset($data['sale_price'])) ? floatval($data['sale_price']) + floatval(floatval($data['sale_price']) * floatval($data['value']) / 100) : $data;
                break;
            case 'decrease_by_value_from_regular':
                $data['value'] = (isset($data['regular_price'])) ? floatval($data['regular_price']) - floatval($data['value']) : $data;
                break;
            case 'decrease_by_percent_from_regular':
                $data['value'] = (isset($data['regular_price'])) ? floatval($data['regular_price']) - (floatval($data['regular_price']) * floatval($data['value']) / 100) : $data;
                break;
        }

        return $data['value'];
    }
}