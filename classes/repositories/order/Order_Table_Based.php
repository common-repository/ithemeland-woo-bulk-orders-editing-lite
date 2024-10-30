<?php

namespace wobel\classes\repositories\order;

defined('ABSPATH') || exit(); // Exit if accessed directly

use wobel\classes\helpers\Others;
use wobel\classes\repositories\Order;

class Order_Table_Based implements Order_Interface
{
    private $wpdb;
    private $methods;
    private $join;
    private $join_tables;
    private $where;
    private $query_args;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        $this->methods = $this->get_methods();
    }

    public function get_orders($args)
    {
        if (!isset($args['limit'])) {
            $args['limit'] = -1;
        }

        if (isset($args['status']) && empty($args['status']['value'])) {
            $args['status']['value'] = $args['status'];
        }

        if (!isset($args['status'])) {
            $args['status']['value'] = array_keys(wc_get_order_statuses());
        }

        if (!isset($args['return'])) {
            $args['return'] = 'ids';
        }

        $this->query_args = $args;
        $this->maybe_remap_args();

        $this->join_tables = [];
        $this->join = '';
        $this->where = 'WHERE 1=1';

        $this->set_orderby();
        $order_type = (!empty($this->query_args['order'])) ? sanitize_text_field($this->query_args['order']) : 'DESC';
        $order = "ORDER BY {$this->query_args['orderby']} {$order_type}";
        $offset = 0;
        if (isset($this->query_args['paginate']) && $this->query_args['paginate'] && isset($this->query_args['limit']) && $this->query_args['limit'] > 0 && $this->query_args['paged'] > 1) {
            $offset = (intval($this->query_args['paged']) - 1) * intval($this->query_args['limit']);
        }
        $limit = (isset($this->query_args['limit']) && $this->query_args['limit'] > 0) ? $this->wpdb->prepare('LIMIT %d OFFSET %d', intval($this->query_args['limit']), intval($offset)) : '';

        if (!empty($this->query_args)) {
            foreach ($this->query_args as $field => $data) {
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

        $this->set_join();
        $result_object = new \stdClass();
        try {
            $orders = $this->wpdb->get_results("SELECT DISTINCT wc_orders.id AS id FROM {$this->wpdb->prefix}wc_orders AS wc_orders {$this->join} {$this->where} {$order} {$limit}", ARRAY_N);
            $total = $this->wpdb->get_row("SELECT DISTINCT COUNT(*) as 'count' FROM {$this->wpdb->prefix}wc_orders AS wc_orders {$this->join} {$this->where} {$order}", ARRAY_A);
        } catch (\Exception $exception) {
            update_option('wobel_get_orders_otb', $exception->getMessage());
            $result_object->orders = [];
            $result_object->max_num_pages = 0;
            $result_object->total = 0;
            return false;
        }

        $total_count = (!empty($total['count'])) ? intval($total['count']) : 0;
        $result_object->orders = Others::array_flatten($orders, 'int');
        $result_object->max_num_pages = ceil($total_count / intval($this->query_args['limit']));
        $result_object->total = $total_count;
        return $result_object;
    }

    private function maybe_remap_args()
    {
        $remap_list = [
            '_billing_address_1' => 'billing_address_1',
            '_billing_address_2' => 'billing_address_2',
            '_billing_city' => 'billing_city',
            '_billing_company' => 'billing_company',
            '_billing_country' => 'billing_country',
            '_billing_state' => 'billing_state',
            '_billing_email' => 'billing_email',
            '_billing_phone' => 'billing_phone',
            '_billing_first_name' => 'billing_first_name',
            '_billing_last_name' => 'billing_last_name',
            '_billing_postcode' => 'billing_postcode',
            '_shipping_address_1' => 'shipping_address_1',
            '_shipping_address_2' => 'shipping_address_2',
            '_shipping_city' => 'shipping_city',
            '_shipping_company' => 'shipping_company',
            '_shipping_country' => 'shipping_country',
            '_shipping_state' => 'shipping_state',
            '_shipping_first_name' => 'shipping_first_name',
            '_shipping_last_name' => 'shipping_last_name',
            '_shipping_postcode' => 'shipping_postcode',
        ];

        if (!empty($this->query_args['wobel_custom_fields'])) {
            $custom_fields = [];
            foreach ($this->query_args['wobel_custom_fields'] as $item) {
                if (isset($item['key']) && isset($remap_list[$item['key']])) {
                    if (!isset($this->query_args[$remap_list[$item['key']]])) {
                        $this->query_args[$remap_list[$item['key']]] = [
                            'value' => sanitize_text_field($item['value']),
                            'operator' => sanitize_text_field($item['operator']),
                        ];
                    }
                } else {
                    $custom_fields[] = $item;
                }
            }

            $this->query_args['wobel_custom_fields'] = $custom_fields;
        }
    }

    private function set_orderby()
    {
        $order_column = (!empty($this->query_args['orderby'])) ? sanitize_text_field($this->query_args['orderby']) : 'id';
        switch ($order_column) {
            case 'ID':
            case 'id':
                $this->query_args['orderby'] = 'wc_orders.id';
                break;
            case 'date_created':
                $this->query_args['orderby'] = 'wc_orders.date_created_gmt';
                break;
            case 'order_total':
                $this->query_args['orderby'] = 'wc_orders.total_amount';
                break;
            case 'order_discount':
                if (!isset($this->join_tables['wc_order_operational_data'])) {
                    $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
                }
                $this->query_args['orderby'] = 'operational_data.discount_total_amount';
                break;
            case 'order_discount_tax':
                if (!isset($this->join_tables['wc_order_operational_data'])) {
                    $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
                }
                $this->query_args['orderby'] = 'operational_data.discount_tax_amount';
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
            'order_ids' => 'set_order_ids',
            'status' => 'set_status',
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

    private function set_join()
    {
        $tables = [
            'wc_order_operational_data' => " LEFT JOIN {$this->wpdb->prefix}wc_order_operational_data AS operational_data ON (wc_orders.id = operational_data.order_id)",
            'wc_order_stats' => " LEFT JOIN {$this->wpdb->prefix}wc_order_stats AS order_stats ON (wc_orders.id = order_stats.order_id)",
            'wc_order_product_lookup' => " LEFT JOIN {$this->wpdb->prefix}wc_order_product_lookup AS order_product_lookup ON (wc_orders.id = order_product_lookup.order_id)",
            'wc_order_coupon_lookup' => " LEFT JOIN {$this->wpdb->prefix}wc_order_coupon_lookup AS order_coupon_lookup ON (wc_orders.id = order_coupon_lookup.order_id)",
        ];

        if (!empty($this->join_tables)) {
            foreach ($this->join_tables as $table_name => $join) {
                if (isset($tables[$table_name])) {
                    $this->join .= $tables[$table_name];
                } else {
                    $this->join .= $join;
                }
            }
        }
    }

    private function set_order_ids($data)
    {
        $ids = (is_array($data['value'])) ? sanitize_text_field(implode(',', $data['value'])) : sanitize_text_field($data['value']);
        $this->where .= " AND (wc_orders.id IN ({$ids}))";
    }

    private function set_status($data)
    {
        if (is_array($data['value'])) {
            $statuses = '';
            $i = 1;
            foreach ($data['value'] as $status) {
                $statuses .= "'{$status}'";
                if (count($data['value']) > $i) {
                    $statuses .= ',';
                }
                $i++;
            }
        } else {
            $statuses = '';
        }

        if (!empty($statuses)) {
            $this->where .= " AND (wc_orders.status IN ({$statuses}))";
        }
    }

    private function set_date_created($data)
    {
        switch ($data['operator']) {
            case 'BETWEEN':
                if (!empty($data['value'][0]) && !empty($data['value'][1])) {
                    $from = sanitize_text_field($data['value'][0]);
                    $to = sanitize_text_field($data['value'][1]);
                    $this->where .= " AND (wc_orders.date_created_gmt BETWEEN '{$from}' AND '{$to}')";
                }
                break;
            case '>=':
                $from = sanitize_text_field($data['value']);
                $this->where .= " AND (wc_orders.date_created_gmt >= '{$from}')";
                break;
            case '<=':
                $to = sanitize_text_field($data['value']);
                $this->where .= " AND (wc_orders.date_created_gmt <= '{$to}')";
                break;
        }
    }

    private function set_date_modified($data)
    {
        switch ($data['operator']) {
            case 'BETWEEN':
                if (!empty($data['value'][0]) && !empty($data['value'][1])) {
                    $from = sanitize_text_field($data['value'][0]);
                    $to = sanitize_text_field($data['value'][1]);
                    $this->where .= " AND (wc_orders.date_updated_gmt BETWEEN '{$from}' AND '{$to}')";
                }
                break;
            case '>=':
                $from = sanitize_text_field($data['value']);
                $this->where .= " AND (wc_orders.date_updated_gmt >= '{$from}')";
                break;
            case '<=':
                $to = sanitize_text_field($data['value']);
                $this->where .= " AND (wc_orders.date_updated_gmt <= '{$to}')";
                break;
        }
    }

    private function set_date_paid($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        switch ($data['operator']) {
            case 'BETWEEN':
                if (!empty($data['value'][0]) && !empty($data['value'][1])) {
                    $from = sanitize_text_field($data['value'][0]);
                    $to = sanitize_text_field($data['value'][1]);
                    $this->where .= " AND (operational_data.date_paid_gmt BETWEEN '{$from}' AND '{$to}')";
                }
                break;
            case '>=':
                $date = sanitize_text_field($data['value']);
                $this->where .= " AND (operational_data.date_paid_gmt >= '{$date}')";
                break;
            case '<=':
                $date = sanitize_text_field($data['value']);
                $this->where .= " AND (operational_data.date_paid_gmt <= '{$date}')";
                break;
        }
    }

    private function set_customer_ip_address($data)
    {
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_orders.ip_address', $value);
        if (!empty($query)) {
            $this->where .= " AND ({$query})";
        }
    }

    private function set_order_currency($data)
    {
        $value = sanitize_text_field($data['value']);
        $this->where .= " AND (wc_orders.currency = '{$value}')";
    }

    private function set_order_total($data)
    {
        switch ($data['operator']) {
            case 'BETWEEN':
                if (!empty($data['value'][0]) && !empty($data['value'][1])) {
                    $from = floatval($data['value'][0]);
                    $to = floatval($data['value'][1]);
                    $this->where .= " AND (wc_orders.total_amount BETWEEN {$from} AND {$to})";
                }
                break;
            case '>=':
                $value = floatval($data['value']);
                $this->where .= " AND (wc_orders.total_amount >= {$value})";
                break;
            case '<=':
                $value = floatval($data['value']);
                $this->where .= " AND (wc_orders.total_amount <= {$value})";
                break;
        }
    }

    private function set_order_discount($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        switch ($data['operator']) {
            case 'BETWEEN':
                if (!empty($data['value'][0]) && !empty($data['value'][1])) {
                    $from = floatval($data['value'][0]);
                    $to = floatval($data['value'][1]);
                    $this->where .= " AND (operational_data.discount_total_amount BETWEEN {$from} AND {$to})";
                }
                break;
            case '>=':
                $value = floatval($data['value']);
                $this->where .= " AND (operational_data.discount_total_amount >= {$value})";
                break;
            case '<=':
                $value = floatval($data['value']);
                $this->where .= " AND (operational_data.discount_total_amount <= {$value})";
                break;
        }
    }

    private function set_order_discount_tax($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        switch ($data['operator']) {
            case 'BETWEEN':
                if (!empty($data['value'][0]) && !empty($data['value'][1])) {
                    $from = floatval($data['value'][0]);
                    $to = floatval($data['value'][1]);
                    $this->where .= " AND (operational_data.discount_tax_amount BETWEEN {$from} AND {$to})";
                }
                break;
            case '>=':
                $value = floatval($data['value']);
                $this->where .= " AND (operational_data.discount_tax_amount >= {$value})";
                break;
            case '<=':
                $value = floatval($data['value']);
                $this->where .= " AND (operational_data.discount_tax_amount <= {$value})";
                break;
        }
    }

    private function set_created_via($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        $query = $this->apply_text_operator(sanitize_text_field($data['operator']), 'operational_data.created_via', sanitize_text_field($data['value']));
        $this->where .= " AND ({$query})";
    }

    private function set_payment_method($data)
    {
        $value = sanitize_text_field($data['value']);
        $this->where .= " AND (wc_orders.payment_method = '{$value}')";
    }

    private function set_order_shipping_tax($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        switch ($data['value']) {
            case 'yes':
                $this->where .= " AND (operational_data.shipping_tax_amount > 0)";
                break;
            case 'no':
                $this->where .= " AND (operational_data.shipping_tax_amount = 0)";
                break;
        }
    }

    private function set_order_shipping($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        switch ($data['value']) {
            case 'yes':
                $this->where .= " AND (operational_data.shipping_total_amount > 0)";
                break;
            case 'no':
                $this->where .= " AND (operational_data.shipping_total_amount = 0)";
                break;
        }
    }

    private function set_prices_include_tax($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        $value = intval($data['value']);
        $this->where .= " AND (operational_data.prices_include_tax = {$value})";
    }

    private function set_billing_address_1($data)
    {
        $this->join_tables['wc_order_addresses_ba1'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS order_addresses_ba1 ON (wc_orders.id = order_addresses_ba1.order_id)";

        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'order_addresses_ba1.address_1', $value);

        if (!empty($query)) {
            $this->where .= " AND (order_addresses_ba1.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_address_2($data)
    {
        $this->join_tables['wc_order_addresses_ba2'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS order_addresses_ba2 ON (wc_orders.id = order_addresses_ba2.order_id)";

        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'order_addresses_ba2.address_2', $value);

        if (!empty($query)) {
            $this->where .= " AND (order_addresses_ba2.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_city($data)
    {
        $this->join_tables['wc_order_addresses_bct'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bct ON (wc_orders.id = wc_order_addresses_bct.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bct.city', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bct.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_company($data)
    {
        $this->join_tables['wc_order_addresses_bco'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bco ON (wc_orders.id = wc_order_addresses_bco.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bco.company', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bco.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_country($data)
    {
        $this->join_tables['wc_order_addresses_bcu'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bcu ON (wc_orders.id = wc_order_addresses_bcu.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bcu.country', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bcu.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_state($data)
    {
        $this->join_tables['wc_order_addresses_bst'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bst ON (wc_orders.id = wc_order_addresses_bst.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bst.state', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bst.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_email($data)
    {
        $this->join_tables['wc_order_addresses_bem'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bem ON (wc_orders.id = wc_order_addresses_bem.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bem.email', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bem.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_phone($data)
    {
        $this->join_tables['wc_order_addresses_bph'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bph ON (wc_orders.id = wc_order_addresses_bph.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bph.phone', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bph.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_first_name($data)
    {
        $this->join_tables['wc_order_addresses_bfn'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bfn ON (wc_orders.id = wc_order_addresses_bfn.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bfn.first_name', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bfn.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_last_name($data)
    {
        $this->join_tables['wc_order_addresses_bln'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bln ON (wc_orders.id = wc_order_addresses_bln.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bln.last_name', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bln.address_type = 'billing' AND {$query})";
        }
    }

    private function set_billing_postcode($data)
    {
        $this->join_tables['wc_order_addresses_bpost'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_bpost ON (wc_orders.id = wc_order_addresses_bpost.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_bpost.postcode', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_bpost.address_type = 'billing' AND {$query})";
        }
    }

    private function set_shipping_address_1($data)
    {
        $this->join_tables['wc_order_addresses_sa1'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_sa1 ON (wc_orders.id = wc_order_addresses_sa1.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_sa1.address_1', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_sa1.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_shipping_address_2($data)
    {
        $this->join_tables['wc_order_addresses_sa2'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_sa2 ON (wc_orders.id = wc_order_addresses_sa2.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_sa2.address_2', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_sa2.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_shipping_city($data)
    {
        $this->join_tables['wc_order_addresses_sct'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_sct ON (wc_orders.id = wc_order_addresses_sct.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_sct.city', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_sct.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_shipping_company($data)
    {
        $this->join_tables['wc_order_addresses_sco'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_sco ON (wc_orders.id = wc_order_addresses_sco.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_sco.company', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_sco.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_shipping_country($data)
    {
        $this->join_tables['wc_order_addresses_scu'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_scu ON (wc_orders.id = wc_order_addresses_scu.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_scu.country', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_scu.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_shipping_state($data)
    {
        $this->join_tables['wc_order_addresses_sst'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_sst ON (wc_orders.id = wc_order_addresses_sst.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_sst.state', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_sst.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_shipping_first_name($data)
    {
        $this->join_tables['wc_order_addresses_sfn'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_sfn ON (wc_orders.id = wc_order_addresses_sfn.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_sfn.first_name', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_sfn.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_shipping_last_name($data)
    {
        $this->join_tables['wc_order_addresses_sln'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_sln ON (wc_orders.id = wc_order_addresses_sln.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_sln.last_name', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_sln.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_shipping_postcode($data)
    {
        $this->join_tables['wc_order_addresses_spost'] = " LEFT JOIN {$this->wpdb->prefix}wc_order_addresses AS wc_order_addresses_spost ON (wc_orders.id = wc_order_addresses_spost.order_id)";
        $value = sanitize_text_field($data['value']);
        $query = $this->apply_text_operator($data['operator'], 'wc_order_addresses_spost.postcode', $value);

        if (!empty($query)) {
            $this->where .= " AND (wc_order_addresses_spost.address_type = 'shipping' AND {$query})";
        }
    }

    private function set_order_stock_reduced($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        $value = ($data['value'] == 'yes') ? 1 : 0;
        $this->where .= " AND (operational_data.order_stock_reduced = {$value})";
    }

    private function set_recorded_sales($data)
    {
        if (!isset($this->join_tables['wc_order_operational_data'])) {
            $this->join_tables['wc_order_operational_data'] = 'wc_order_operational_data';
        }

        $value = ($data['value'] == 'yes') ? 1 : 0;
        $this->where .= " AND (operational_data.recorded_sales = {$value})";
    }

    private function set_recorded_coupon_usage_counts($data)
    {
        if (!isset($this->join_tables['wc_order_coupon_lookup'])) {
            $this->join_tables['wc_order_coupon_lookup'] = 'wc_order_coupon_lookup';
        }

        switch ($data['value']) {
            case 'yes':
                $this->where .= " AND (order_coupon_lookup.discount_amount > 0)";
                break;
            case 'no':
                $this->where .= " AND NOT EXISTS(SELECT order_id FROM {$this->wpdb->prefix}wc_order_coupon_lookup AS coupon_lookup WHERE coupon_lookup.order_id = wc_orders.id)";
                break;
        }
    }

    private function set_products_ids($data)
    {
        if (!isset($this->join_tables['wc_order_product_lookup'])) {
            $this->join_tables['wc_order_product_lookup'] = 'wc_order_product_lookup';
        }

        $query = "";
        if (!empty($data['value'])) {
            if ($data['operator'] == 'or') {
                $i = 1;
                foreach ($data['value'] as $item) {
                    if ($i > 1) {
                        $query .= " OR ";
                    }

                    $product_id_array = explode('__', $item);
                    if (!isset($product_id_array[0]) || !isset($product_id_array[1])) {
                        continue;
                    }

                    $variation_id = 0;
                    if (intval($product_id_array[0]) == 0) {
                        $variation_id = 0;
                        $product_id = intval($product_id_array[1]);
                    } else {
                        $product_id = intval($product_id_array[0]);
                        $variation_id = intval($product_id_array[1]);
                    }

                    $query .= "(order_product_lookup.product_id = {$product_id} AND order_product_lookup.variation_id = {$variation_id})";
                    $i++;
                }
            } else {
                $i = 1;
                foreach ($data['value'] as $item) {
                    $product_id_array = explode('__', $item);
                    if (!isset($product_id_array[0]) || !isset($product_id_array[1])) {
                        continue;
                    }

                    $variation_ids = "0";
                    $product_ids = "0";

                    if (intval($product_id_array[0]) == 0) {
                        $product_ids .= sanitize_text_field($product_id_array[1]);
                        if (count($data['value']) > $i) {
                            $product_ids .= ',';
                        }
                    } else {
                        $product_ids .= sanitize_text_field($product_id_array[0]);
                        $variation_ids .= sanitize_text_field($product_id_array[1]);
                        if (count($data['value']) > $i) {
                            $variation_ids .= ',';
                            $product_ids .= ',';
                        }
                    }

                    $i++;
                }

                if ($data['operator'] == 'and') {
                    $query .= "(order_product_lookup.product_id IN ({$product_ids}) AND order_product_lookup.variation_id IN ({$variation_ids}))";
                } else {
                    $query .= "(order_product_lookup.product_id NOT IN ({$product_ids}) AND order_product_lookup.variation_id NOT IN ({$variation_ids}))";
                }
            }
        }

        if (!empty($query)) {
            $this->where .= " AND ({$query})";
        }
    }

    private function set_taxonomies($data)
    {
        if (!isset($this->join_tables['wc_order_product_lookup'])) {
            $this->join_tables['wc_order_product_lookup'] = 'wc_order_product_lookup';
        }

        $product_ids = "";

        foreach ($data as $item) {
            if (!empty($item['value']) && is_array($item['value']) && isset($item['value'][0])) {
                $tax_query['relation'] = 'AND';

                switch ($item['operator']) {
                    case 'or':
                    case 'and':
                        $operator = 'IN';
                        break;
                    default:
                        $operator = 'NOT IN';
                        break;
                }

                if (!empty($item['taxonomy'])) {
                    if (is_array($item['value']) && !empty($item['value'])) {
                        $items = [];
                        foreach ($item['value'] as $value_item) {
                            $items['relation'] = ($item['operator'] == 'or' && $item['value'] > 1) ? 'OR' : 'AND';
                            $items[] = [
                                'taxonomy' => sanitize_text_field($item['taxonomy']),
                                'field' => (in_array($item['taxonomy'], ['product_tag'])) ? 'slug' : 'term_id',
                                'terms' => [sanitize_text_field($value_item)],
                                'operator' => $operator
                            ];
                        }
                        $tax_query[] = $items;
                    }
                } else {
                    $items = [];
                    foreach ($item['value'] as $term) {
                        $taxonomy = explode('__', $term);
                        if (!empty($taxonomy[0])) {
                            $items['relation'] = ($item['operator'] == 'or' && $item['value'] > 1) ? 'OR' : 'AND';
                            $items[] = [
                                'taxonomy' => sanitize_text_field($taxonomy[0]),
                                'field' => (in_array($item['taxonomy'], ['product_tag'])) ? 'slug' : 'term_id',
                                'terms' => [sanitize_text_field($taxonomy[1])],
                                'operator' => $operator
                            ];
                        }
                    }

                    if (!empty($items)) {
                        $tax_query[] = $items;
                    }
                }
            }
        }

        if (!empty($tax_query)) {
            $query_result = new \WP_Query([
                'post_type' => ['product'],
                'posts_per_page' => -1,
                'post_status' => 'any',
                'fields' => 'ids',
                'tax_query' => $tax_query
            ]);

            if (!empty($query_result->posts)) {
                foreach ($query_result->posts as $id) {
                    $product_ids .= (!empty($product_ids)) ? ',' . $id : $id;
                }
            }
        }

        $this->where .= " AND (order_product_lookup.product_id IN ({$product_ids}))";
    }

    private function set_custom_fields($data)
    {
        if (!empty($data)) {
            $order_repository = Order::get_instance();
            if ($order_repository->hpos_sync_enabled()) {
                $meta_table = "{$this->wpdb->prefix}postmeta";
                $meta_table_ley = "post_id";
            } else {
                $meta_table = "{$this->wpdb->prefix}wc_orders_meta";
                $meta_table_ley = "order_id";
            }

            foreach ($data as $item) {
                if (!empty($item['key']) && !empty($item['value']) && !empty($item['operator'])) {
                    $key = sanitize_text_field($item['key']);
                    $value = sanitize_text_field($item['value']);
                    $query = "";
                    $postmeta = "postmeta_{$key}";
                    $this->join_tables['wobel_custom_field'] = " LEFT JOIN {$meta_table} AS {$postmeta} ON (wc_orders.id = {$postmeta}.{$meta_table_ley})";

                    switch ($item['operator']) {
                        case 'BETWEEN':
                            if (!empty($item['value'][0]) && !empty($item['value'][1])) {
                                $from = sanitize_text_field($item['value'][0]);
                                $to = sanitize_text_field($item['value'][1]);
                                if (!empty($item['type']) && $item['type'] == 'number') {
                                    $query .= "{$postmeta}.meta_value BETWEEN {$from} AND {$to}";
                                } else {
                                    $query .= "{$postmeta}.meta_value BETWEEN '{$from}' AND '{$to}'";
                                }
                            }
                            break;
                        case '>=':
                            $from = sanitize_text_field($item['value']);
                            if (!empty($item['type']) && $item['type'] == 'number') {
                                $query .= " AND ({$postmeta}.meta_value >= {$from})";
                            } else {
                                $query .= " AND ({$postmeta}.meta_value >= '{$from}')";
                            }
                            break;
                        case '<=':
                            $to = sanitize_text_field($item['value']);
                            if (!empty($item['type']) && $item['type'] == 'number') {
                                $query .= " AND ({$postmeta}.meta_value <= {$to})";
                            } else {
                                $query .= " AND ({$postmeta}.meta_value <= '{$to}')";
                            }
                            break;
                        default:
                            $query .= $this->apply_text_operator($item['operator'], "{$postmeta}.meta_value", $value);
                    }

                    if (!empty($query)) {
                        $this->where .= " AND ({$postmeta}.meta_key = '{$key}' AND {$query})";
                    }
                }
            }
        }
    }

    private function apply_text_operator($operator, $column, $value)
    {
        $query = '';
        switch ($operator) {
            case 'like':
                $query = "{$column} LIKE '%{$value}%'";
                break;
            case 'exact':
                $query = "{$column} = '{$value}'";
                break;
            case 'not':
                $query = "{$column} != '{$value}'";
                break;
            case 'begin':
                $query = "{$column} LIKE '{$value}%'";
                break;
            case 'end':
                $query = "{$column} LIKE '%{$value}'";
                break;
        }

        return $query;
    }
}
