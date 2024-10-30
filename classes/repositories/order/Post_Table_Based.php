<?php

namespace wobel\classes\repositories\order;

defined('ABSPATH') || exit(); // Exit if accessed directly

class Post_Table_Based implements Order_Interface
{
    private $methods;
    private $query_args;

    public function __construct()
    {
        $this->methods = $this->get_methods();
    }

    public function get_orders($args)
    {
        $this->query_args = [];
        $this->set_required_args($args);

        if (!empty($args)) {
            foreach ($args as $field => $data) {
                if ((!isset($data['value']) || !isset($data['operator'])) && !in_array($field, ['status', 'wobel_product_taxonomy', 'wobel_custom_fields'])) {
                    continue;
                }

                $method = $this->get_method($field);
                if (empty($method) || !method_exists($this, $method)) {
                    continue;
                }

                $this->$method($data);
            }
        }

        $result = new \WP_Query($this->query_args);
        $result_object = new \stdClass();
        $result_object->orders = $result->posts;
        $result_object->max_num_pages = $result->max_num_pages;
        $result_object->total = $result->found_posts;

        return $result_object;
    }

    private function set_required_args($args)
    {
        $this->query_args['meta_query'] = (isset($args['meta_query'])) ? $args['meta_query'] : [];
        $this->query_args['tax_query'] = (isset($args['tax_query'])) ? $args['tax_query'] : [];
        if (isset($args['meta_key'])) {
            $this->query_args['meta_key'] = sanitize_text_field($args['meta_key']);
        }
        $this->query_args['posts_per_page'] = (isset($args['limit'])) ? intval($args['limit']) : 10;
        $this->query_args['fields'] = (!empty($args['return'])) ? sanitize_text_field($args['return']) : 'ids';
        $this->query_args['order'] = (!empty($args['order'])) ? sanitize_text_field($args['order']) : 'DESC';
        $this->query_args['orderby'] = (!empty($args['orderby'])) ? sanitize_text_field($args['orderby']) : 'id';
        $this->set_orderby();

        $this->query_args['paginate'] = (isset($args['paginate']) && $args['paginate'] === true);
        if ($this->query_args['paginate']) {
            $this->query_args['paged'] = (!empty($args['paged'])) ? intval($args['paged']) : 1;
        }

        if (!empty($args['post_type'])) {
            $this->query_args['post_type'] = (is_array($args['post_type'])) ? array_map('sanitize_text_field', $args['post_type']) : [sanitize_text_field($args['post_type'])];
        } else {
            $this->query_args['post_type'] =  ['shop_order', 'shop_order_placehold'];
        }

        if (!empty($args['status']) && !empty($args['status']['value'])) {
            $this->query_args['post_status'] = (is_array($args['status']['value'])) ? array_map('sanitize_text_field', $args['status']['value']) : [sanitize_text_field($args['status']['value'])];
        } else {
            $this->query_args['post_status'] = array_keys(wc_get_order_statuses());
        }
    }

    private function set_orderby()
    {
        switch ($this->query_args['orderby']) {
            case 'ID':
            case 'id':
                $this->query_args['orderby'] = 'ID';
                break;
            case 'date_created':
                $this->query_args['orderby'] = 'post_date';
                break;
            case 'order_total':
                if (!isset($this->query_args['order_total'])) {
                    $this->query_args['meta_query'][] = [
                        [
                            'key' => '_order_total',
                            'compare' => 'EXISTS'
                        ]
                    ];
                }

                $this->query_args['meta_key'] = '_order_total';
                $this->query_args['orderby'] = 'meta_value_num';
                break;
            case 'order_discount':
                if (!isset($this->query_args['order_discount'])) {
                    $this->query_args['meta_query'][] = [
                        [
                            'key' => '_cart_discount',
                            'compare' => 'EXISTS'
                        ]
                    ];
                }

                $this->query_args['meta_key'] = '_cart_discount';
                $this->query_args['orderby'] = 'meta_value_num';
                break;
            case 'order_discount_tax':
                if (!isset($this->query_args['order_discount_tax'])) {
                    $this->query_args['meta_query'][] = [
                        [
                            'key' => '_cart_discount_tax',
                            'compare' => 'EXISTS'
                        ]
                    ];
                }

                $this->query_args['meta_key'] = '_cart_discount_tax';
                $this->query_args['orderby'] = 'meta_value_num';
                break;
        }
    }

    private function get_method($field)
    {
        return (!empty($this->methods[$field])) ? $this->methods[$field] : false;
    }

    private function get_methods()
    {
        return [
            'orderby' => 'set_orderby',
            'order_ids' => 'set_order_ids',
            'date_created' => 'set_date_created',
            'date_modified' => 'set_date_modified',
            'date_paid' => 'set_date_paid',
            'customer_ip_address' => 'set_customer_ip_address',
            'order_currency' => 'set_order_currency',
            'order_total' => 'set_order_total',
            'order_discount' => 'set_order_discount',
            'order_discount_tax' => 'set_order_discount_tax',
            'created_via' => 'set_created_via',
            'payment_method' => 'set_payment_method',
            'order_shipping_tax' => 'set_order_shipping_tax',
            'order_shipping' => 'set_order_shipping',
            'prices_include_tax' => 'set_prices_include_tax',
            'billing_address_1' => 'set_billing_address_1',
            'billing_address_2' => 'set_billing_address_2',
            'billing_city' => 'set_billing_city',
            'billing_company' => 'set_billing_company',
            'billing_country' => 'set_billing_country',
            'billing_state' => 'set_billing_state',
            'billing_email' => 'set_billing_email',
            'billing_phone' => 'set_billing_phone',
            'billing_first_name' => 'set_billing_first_name',
            'billing_last_name' => 'set_billing_last_name',
            'billing_postcode' => 'set_billing_postcode',
            'shipping_address_1' => 'set_shipping_address_1',
            'shipping_address_2' => 'set_shipping_address_2',
            'shipping_city' => 'set_shipping_city',
            'shipping_company' => 'set_shipping_company',
            'shipping_country' => 'set_shipping_country',
            'shipping_state' => 'set_shipping_state',
            'shipping_first_name' => 'set_shipping_first_name',
            'shipping_last_name' => 'set_shipping_last_name',
            'shipping_postcode' => 'set_shipping_postcode',
            'wobel_recorded_coupon_usage_counts' => 'set_recorded_coupon_usage_counts',
            'order_stock_reduced' => 'set_order_stock_reduced',
            'recorded_sales' => 'set_recorded_sales',
            'wobel_products_ids' => 'set_products_ids',
            'wobel_product_taxonomy' => 'set_taxonomies',
            'wobel_custom_fields' => 'set_custom_fields',
        ];
    }

    private function set_order_ids($data)
    {
        $ids = $data['value'];
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }

        $ids = (is_array($ids)) ? array_map('intval', $ids) : [intval($ids)];
        $this->query_args['post__in'] = $ids;
    }

    private function set_date_created($data)
    {
        switch ($data['operator']) {
            case 'between':
                if (!empty($data['value'][0]) && !empty($data['value'][1])) {
                    $this->query_args['date_query'] = [
                        [
                            'column' => 'post_date',
                            'after' => gmdate('Y-m-d H:i', strtotime($data['value'][0])), // from
                            'before' => gmdate('Y-m-d H:i', strtotime($data['value'][1])), // to
                            'inclusive' => true
                        ]
                    ];
                }
                break;
            case '>=':
                if (!empty($data['value'])) {
                    $this->query_args['date_query'] = [
                        [
                            'column' => 'post_date',
                            'after' => gmdate('Y-m-d H:i', strtotime($data['value'])),
                            'inclusive' => true
                        ]
                    ];
                }
                break;
            case '<=':
                if (!empty($data['value'])) {
                    $this->query_args['date_query'] = [
                        [
                            'column' => 'post_date',
                            'before' => gmdate('Y-m-d H:i', strtotime($data['value'])),
                            'inclusive' => true
                        ]
                    ];
                }
                break;
        }
    }

    private function set_date_modified($data)
    {
        switch ($data['operator']) {
            case 'between':
                if (!empty($data['value'][0]) && !empty($data['value'][1])) {
                    $this->query_args['date_query'] = [
                        [
                            'column' => 'post_modified',
                            'after' => gmdate('Y-m-d H:i', strtotime($data['value'][0])), // from
                            'before' => gmdate('Y-m-d H:i', strtotime($data['value'][1])), // to
                            'inclusive' => true
                        ]
                    ];
                }
                break;
            case '>=':
                if (!empty($data['value'])) {
                    $this->query_args['date_query'] = [
                        [
                            'column' => 'post_modified',
                            'after' => gmdate('Y-m-d H:i', strtotime($data['value'])),
                            'inclusive' => true
                        ]
                    ];
                }
                break;
            case '<=':
                if (!empty($data['value'])) {
                    $this->query_args['date_query'] = [
                        [
                            'column' => 'post_modified',
                            'before' => gmdate('Y-m-d H:i', strtotime($data['value'])),
                            'inclusive' => true
                        ]
                    ];
                }
                break;
        }
    }

    private function set_date_paid($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_paid_date',
            'value' => (is_array($data['value'])) ? array_map('sanitize_text_field', $data['value']) : sanitize_text_field($data['value']),
            'compare' => sanitize_text_field($data['operator']),
            'type' => 'DATETIME'
        ];
    }

    private function set_customer_ip_address($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_customer_ip_address', $data['operator'], $data['value']);
    }

    private function set_order_currency($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_order_currency',
            'value' => sanitize_text_field($data['value']),
            'compare' => sanitize_text_field($data['operator']),
        ];
    }

    private function set_order_total($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_order_total',
            'value' => (is_array($data['value'])) ? array_map('floatval', $data['value']) : floatval($data['value']),
            'compare' => sanitize_text_field($data['operator']),
            'type' => 'DECIMAL'
        ];
    }

    private function set_order_discount($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_cart_discount',
            'value' => (is_array($data['value'])) ? array_map('floatval', $data['value']) : floatval($data['value']),
            'compare' => sanitize_text_field($data['operator']),
            'type' => 'DECIMAL'
        ];
    }

    private function set_order_discount_tax($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_cart_discount_tax',
            'value' => (is_array($data['value'])) ? array_map('floatval', $data['value']) : floatval($data['value']),
            'compare' => sanitize_text_field($data['operator']),
            'type' => 'DECIMAL'
        ];
    }

    private function set_created_via($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_created_via', $data['operator'], $data['value']);
    }

    private function set_payment_method($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_payment_method',
            'value' => sanitize_text_field($data['value']),
            'compare' => sanitize_text_field($data['operator']),
        ];
    }

    private function set_order_shipping_tax($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_order_shipping_tax',
            'value' => ($data['value'] == 'yes') ? 1 : 0,
            'compare' => sanitize_text_field($data['operator']),
        ];
    }

    private function set_order_shipping($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_order_shipping',
            'value' => ($data['value'] == 'yes') ? 1 : 0,
            'compare' => sanitize_text_field($data['operator']),
        ];
    }

    private function set_prices_include_tax($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_prices_include_tax',
            'value' => sanitize_text_field($data['value']),
            'compare' => sanitize_text_field($data['operator']),
        ];
    }

    private function set_billing_address_1($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_address_1', $data['operator'], $data['value']);
    }

    private function set_billing_address_2($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_address_2', $data['operator'], $data['value']);
    }

    private function set_billing_city($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_city', $data['operator'], $data['value']);
    }

    private function set_billing_company($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_company', $data['operator'], $data['value']);
    }

    private function set_billing_country($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_country', $data['operator'], $data['value']);
    }

    private function set_billing_state($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_state', $data['operator'], $data['value']);
    }

    private function set_billing_email($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_email', $data['operator'], $data['value']);
    }

    private function set_billing_phone($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_phone', $data['operator'], $data['value']);
    }

    private function set_billing_first_name($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_first_name', $data['operator'], $data['value']);
    }

    private function set_billing_last_name($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_last_name', $data['operator'], $data['value']);
    }

    private function set_billing_postcode($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_billing_postcode', $data['operator'], $data['value']);
    }

    private function set_shipping_address_1($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_address_1', $data['operator'], $data['value']);
    }

    private function set_shipping_address_2($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_address_2', $data['operator'], $data['value']);
    }

    private function set_shipping_city($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_city', $data['operator'], $data['value']);
    }

    private function set_shipping_company($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_company', $data['operator'], $data['value']);
    }

    private function set_shipping_country($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_country', $data['operator'], $data['value']);
    }

    private function set_shipping_state($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_state', $data['operator'], $data['value']);
    }

    private function set_shipping_first_name($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_first_name', $data['operator'], $data['value']);
    }

    private function set_shipping_last_name($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_last_name', $data['operator'], $data['value']);
    }

    private function set_shipping_postcode($data)
    {
        $this->query_args['meta_query'][] = $this->get_meta_query('_shipping_postcode', $data['operator'], $data['value']);
    }

    private function set_order_stock_reduced($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_order_stock_reduced',
            'value' => sanitize_text_field($data['value']),
            'compare' => sanitize_text_field($data['operator']),
        ];
    }

    private function set_recorded_sales($data)
    {
        $this->query_args['meta_query'][] = [
            'key' => '_recorded_sales',
            'value' => sanitize_text_field($data['value']),
            'compare' => sanitize_text_field($data['operator']),
        ];
    }

    private function set_recorded_coupon_usage_counts($data)
    {
        $this->query_args['wobel_coupon_used_filter'][] = $data;
    }

    private function set_products_ids($data)
    {
        $this->query_args['wobel_product_filter'][] = $data;
    }

    private function set_taxonomies($data)
    {
        $this->query_args['wobel_product_taxonomy_filter'] = $data;
    }

    private function set_custom_fields($data)
    {
        if (!empty($data)) {
            foreach ($data as $item) {
                if (!empty($item['key']) && !empty($item['value']) && !empty($item['operator'])) {
                    $this->query_args['meta_query'][] = [
                        'key' => sanitize_text_field($item['key']),
                        'value' => (is_array($item['value'])) ? array_map('sanitize_text_field', $item['value']) : sanitize_text_field($item['value']),
                        'compare' => sanitize_text_field($item['operator']),
                    ];
                }
            }
        }
    }

    private function get_meta_query($key, $operator, $value)
    {
        if (is_array($value)) {
            return [];
        } else {
            $value = sanitize_text_field($value);
            $compare = '=';
            switch ($operator) {
                case 'like':
                    $compare = "LIKE";
                    break;
                case 'exact':
                    $compare = "=";
                    break;
                case 'not':
                    $compare = "!=";
                    break;
                case 'begin':
                    $compare = "RLIKE";
                    $value = '^' . $value;
                    break;
                case 'end':
                    $compare = "RLIKE";
                    $value = $value . '$';
                    break;
            }

            return [
                'key' => sanitize_text_field($key),
                'value' => $value,
                'compare' => $compare,
            ];
        }
    }
}
