<?php

namespace wobel\classes\services\order\duplicate;

defined('ABSPATH') || exit(); // Exit if accessed directly

use wobel\classes\repositories\Order;

class Duplicate_Order_Service
{
    private $new_order;
    private $original_order;

    public function duplicate($order_ids, $number = 1)
    {
        if (!is_array($order_ids) || empty($order_ids) || !is_numeric($number)) {
            return false;
        }

        $order_repository = Order::get_instance();

        foreach ($order_ids as $order_id) {
            $this->original_order = $order_repository->get_order(intval($order_id));
            if (!($this->original_order instanceof \WC_Order)) {
                return false;
            }

            for ($i = 1; $i <= intval($number); $i++) {
                $this->new_order = new \WC_Order();
                $this->new_order->save();
                $this->set_status();
                $this->duplicate_main_field();
                $this->duplicate_order_meta_fields();
                $this->duplicate_line_items();
                $this->duplicate_shipping_items();
                $this->duplicate_coupons();
                $this->duplicate_order_notes();
                $this->new_order->calculate_taxes();
                $this->new_order->save();
            }
        }

        return true;
    }

    private function set_status()
    {
        $order_repository = Order::get_instance();
        global $wpdb;

        $status = 'wc-' . $this->original_order->get_status();
        $new_order_id = intval($this->new_order->get_id());

        if ($order_repository->hpos_sync_enabled()) {
            $wpdb->update($wpdb->prefix . 'wc_orders', ['status' => $status], ['id' => $new_order_id]);
        } else {
            if ($order_repository->hpos_enabled()) {
                $wpdb->update($wpdb->prefix . 'wc_orders', ['status' => $status], ['id' => $new_order_id]);
            } else {
                $wpdb->update($wpdb->prefix . 'posts', ['post_status' => $status], ['ID' => $new_order_id]);
            }
        }
    }

    private function duplicate_main_field()
    {
        $this->new_order->set_date_created($this->original_order->get_date_created());
        $this->new_order->set_date_completed($this->original_order->get_date_completed());
        $this->new_order->set_date_paid($this->original_order->get_date_paid());
        $this->new_order->set_customer_note($this->original_order->get_customer_note());
        $this->new_order->set_customer_ip_address($this->original_order->get_customer_ip_address());
        $this->new_order->set_customer_id($this->original_order->get_customer_id());
        $this->new_order->set_total($this->original_order->get_total());
        $this->new_order->set_discount_total($this->original_order->get_total_discount());
        $this->new_order->set_discount_tax($this->original_order->get_discount_tax());
        $this->new_order->set_created_via($this->original_order->get_created_via());
        $this->new_order->set_currency($this->original_order->get_currency());
        $this->new_order->set_payment_method($this->original_order->get_payment_method());
        $this->new_order->set_payment_method_title($this->original_order->get_payment_method_title());
        $this->new_order->set_version($this->original_order->get_version());
        $this->new_order->set_prices_include_tax($this->original_order->get_prices_include_tax());
        $this->new_order->set_shipping_total($this->original_order->get_shipping_total());
        $this->new_order->set_shipping_tax($this->original_order->get_shipping_tax());
        $this->new_order->set_billing_address_1($this->original_order->get_billing_address_1());
        $this->new_order->set_billing_address_2($this->original_order->get_billing_address_2());
        $this->new_order->set_billing_city($this->original_order->get_billing_city());
        $this->new_order->set_billing_company($this->original_order->get_billing_company());
        $this->new_order->set_billing_country($this->original_order->get_billing_country());
        $this->new_order->set_billing_email($this->original_order->get_billing_email());
        $this->new_order->set_billing_phone($this->original_order->get_billing_phone());
        $this->new_order->set_billing_first_name($this->original_order->get_billing_first_name());
        $this->new_order->set_billing_last_name($this->original_order->get_billing_last_name());
        $this->new_order->set_billing_postcode($this->original_order->get_billing_postcode());
        $this->new_order->set_billing_state($this->original_order->get_billing_state());
        $this->new_order->set_shipping_address_1($this->original_order->get_shipping_address_1());
        $this->new_order->set_shipping_address_2($this->original_order->get_shipping_address_2());
        $this->new_order->set_shipping_city($this->original_order->get_shipping_city());
        $this->new_order->set_shipping_company($this->original_order->get_shipping_company());
        $this->new_order->set_shipping_country($this->original_order->get_shipping_country());
        $this->new_order->set_shipping_first_name($this->original_order->get_shipping_first_name());
        $this->new_order->set_shipping_last_name($this->original_order->get_shipping_last_name());
        $this->new_order->set_shipping_postcode($this->original_order->get_shipping_postcode());
        $this->new_order->set_shipping_state($this->original_order->get_shipping_state());
        $this->new_order->set_transaction_id($this->original_order->get_transaction_id());
    }

    private function duplicate_order_meta_fields()
    {
        $original_meta_data = $this->original_order->get_meta_data();
        if (!empty($original_meta_data) && is_array($original_meta_data)) {
            foreach ($original_meta_data as $meta_data_object) {
                $meta_data = $meta_data_object->get_data();
                if (isset($meta_data['key']) && isset($meta_data['value'])) {
                    $this->new_order->update_meta_data($meta_data['key'], $meta_data['value']);
                }
            }
        }
    }

    private function duplicate_line_items()
    {
        foreach ($this->original_order->get_items() as $originalOrderItem) {
            $itemName = $originalOrderItem['name'];
            $qty = $originalOrderItem['qty'];
            $lineTotal = $originalOrderItem['line_total'];
            $lineTax = $originalOrderItem['line_tax'];
            $productID = $originalOrderItem['product_id'];

            $item_id = wc_add_order_item($this->new_order->get_id(), array(
                'order_item_name' => $itemName,
                'order_item_type' => 'line_item'
            ));

            wc_add_order_item_meta($item_id, '_qty', $qty);
            wc_add_order_item_meta($item_id, '_tax_class', $originalOrderItem['tax_class']);
            wc_add_order_item_meta($item_id, '_product_id', $productID);
            wc_add_order_item_meta($item_id, '_variation_id', $originalOrderItem['variation_id']);
            wc_add_order_item_meta($item_id, '_line_subtotal', wc_format_decimal($lineTotal));
            wc_add_order_item_meta($item_id, '_line_total', wc_format_decimal($lineTotal));
            wc_add_order_item_meta($item_id, '_line_tax', wc_format_decimal($lineTax));
            wc_add_order_item_meta($item_id, '_line_subtotal_tax', wc_format_decimal($originalOrderItem['line_subtotal_tax']));
        }
    }

    private function duplicate_shipping_items()
    {
        $original_order_shipping_items = $this->original_order->get_items('shipping');

        foreach ($original_order_shipping_items as $original_order_shipping_item) {
            $item_id = wc_add_order_item($this->new_order->get_id(), array(
                'order_item_name' => $original_order_shipping_item['name'],
                'order_item_type' => 'shipping'
            ));
            if ($item_id) {
                wc_add_order_item_meta($item_id, 'method_id', $original_order_shipping_item['method_id']);
                wc_add_order_item_meta($item_id, 'cost', wc_format_decimal($original_order_shipping_item['cost']));
            }
        }
    }

    private function duplicate_coupons()
    {
        $original_order_coupons = $this->original_order->get_items('coupon');
        foreach ($original_order_coupons as $original_order_coupon) {
            $item_id = wc_add_order_item($this->new_order->get_id(), array(
                'order_item_name' => $original_order_coupon['name'],
                'order_item_type' => 'coupon'
            ));
            if ($item_id) {
                wc_add_order_item_meta($item_id, 'discount_amount', $original_order_coupon['discount_amount']);
            }
        }
    }

    private function duplicate_order_notes()
    {
        $args = array(
            'post_id' => $this->original_order->get_id(),
            'orderby' => 'comment_ID',
            'order' => 'ASC',
            'approve' => 'approve',
            'type' => 'order_note'
        );

        remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'), 10, 1);

        $order_notes = get_comments($args);

        if (!empty($order_notes) && is_array($order_notes)) {
            foreach ($order_notes as $order_note) {
                if ($order_note instanceof \WP_Comment) {
                    $comment_array = $order_note->to_array();
                    $original_comment_id = $comment_array['comment_ID'];
                    unset($comment_array['comment_ID']);
                    unset($comment_array['children']);
                    unset($comment_array['populated_children']);
                    unset($comment_array['post_fields']);
                    $comment_array['comment_post_ID'] = $this->new_order->get_id();
                    $new_comment_id = wp_insert_comment($comment_array);

                    if ($new_comment_id) {
                        $original_comment_meta = get_comment_meta($original_comment_id);
                        if (!empty($original_comment_meta) && is_array($original_comment_meta)) {
                            foreach ($original_comment_meta as $meta_key => $meta_value) {
                                if (isset($meta_value[0])) {
                                    add_comment_meta($new_comment_id, $meta_key, $meta_value[0]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
