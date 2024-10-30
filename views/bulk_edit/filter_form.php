<?php

use wobel\classes\helpers\Sanitizer;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
?>

<div class="wobel-float-side-modal" id="wobel-float-side-modal-filter">
    <div class="wobel-float-side-modal-container">
        <div class="wobel-float-side-modal-box">
            <div class="wobel-float-side-modal-content">
                <input type="hidden" id="filter-form-changed" value="">
                <div class="wobel-float-side-modal-title">
                    <h2><?php esc_html_e('Filter Form', 'ithemeland-woocommerce-bulk-orders-editing-lite'); ?></h2>
                    <button type="button" class="wobel-float-side-modal-close" data-toggle="float-side-modal-close">
                        <i class="wobel-icon-x"></i>
                    </button>
                </div>
                <div class="wobel-float-side-modal-body">
                    <div class="wobel-wrap">
                        <ul class="wobel-tabs-list" data-content-id="wobel-bulk-edit-filter-tabs-contents">
                            <?php if (!empty($filter_form_tabs_title) && is_array($filter_form_tabs_title)) : ?>
                                <?php $filter_tab_title_counter = 1; ?>
                                <?php foreach ($filter_form_tabs_title as $tab_key => $tab_label) : ?>
                                    <li><button type="button" class="wobel-tab-item <?php echo ($filter_tab_title_counter == 1) ? 'selected' : ''; ?>" data-content="<?php echo esc_attr($tab_key); ?>"><?php echo esc_attr($tab_label); ?></button></li>
                                    <?php $filter_tab_title_counter++; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <div class="wobel-tabs-contents" id="wobel-bulk-edit-filter-tabs-contents">
                            <?php if (!empty($filter_form_tabs_content)) : ?>
                                <?php foreach ($filter_form_tabs_content as $tab_key => $filter_tab) : ?>
                                    <?php echo (!empty($filter_tab['wrapper_start'])) ? wp_kses($filter_tab['wrapper_start'], Sanitizer::allowed_html_tags()) : ''; ?>
                                    <?php
                                    if (!empty($filter_tab['fields_top']) && is_array($filter_tab['fields_top'])) {
                                        foreach ($filter_tab['fields_top'] as $top_item) {
                                            echo wp_kses($top_item, Sanitizer::allowed_html_tags());
                                        }
                                    }
                                    ?>
                                    <?php if (!empty($filter_tab['fields']) && is_array($filter_tab['fields'])) : ?>
                                        <?php foreach ($filter_tab['fields'] as $field_key => $field_items) : ?>
                                            <?php if (!empty($field_items) && is_array($field_items)) : ?>
                                                <div class="wobel-form-group" data-name="<?php echo esc_attr($field_key); ?>">
                                                    <?php foreach ($field_items as $field_html) : ?>
                                                        <?php echo wp_kses($field_html, Sanitizer::allowed_html_tags()); ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($filter_tab['grouped_fields']) && is_array($filter_tab['grouped_fields'])) : ?>
                                        <?php foreach ($filter_tab['grouped_fields'] as $group => $grouped_field_items) : ?>
                                            <div class="wobel-filter-form-grouped-fields-item">
                                                <strong><?php echo esc_html(ucfirst($group)); ?></strong>
                                                <hr>
                                                <?php if (!empty($grouped_field_items) && is_array($grouped_field_items)) : ?>
                                                    <?php foreach ($grouped_field_items as $field_key => $field_items) : ?>
                                                        <?php if (!empty($field_items) && is_array($field_items)) : ?>
                                                            <div class="wobel-form-group" data-name="<?php echo esc_attr($field_key); ?>">
                                                                <?php foreach ($field_items as $field_html) : ?>
                                                                    <?php echo wp_kses($field_html, Sanitizer::allowed_html_tags()); ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php echo (!empty($filter_tab['wrapper_end'])) ? wp_kses($filter_tab['wrapper_end'], Sanitizer::allowed_html_tags()) : ''; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="wobel-float-side-modal-footer">
                    <div class="wobel-tab-footer-left">
                        <button type="button" id="wobel-filter-form-get-orders" class="wobel-button wobel-button-blue wobel-filter-form-action" data-search-action="pro_search">
                            <?php esc_html_e('Get Orders', 'ithemeland-woocommerce-bulk-orders-editing-lite'); ?>
                        </button>
                        <button type="button" class="wobel-button wobel-button-white" id="wobel-filter-form-reset">
                            <?php esc_html_e('Reset Filters', 'ithemeland-woocommerce-bulk-orders-editing-lite'); ?>
                        </button>
                    </div>
                    <div class="wobel-tab-footer-right">
                        <input type="text" name="save_filter" id="wobel-filter-form-save-preset-name" placeholder="Filter Name ..." class="" title="Filter Name">
                        <button type="button" id="wobel-filter-form-save-preset" class="wobel-button wobel-button-blue">
                            <?php esc_html_e('Save Profile', 'ithemeland-woocommerce-bulk-orders-editing-lite'); ?>
                        </button>
                    </div>
                    <div class="clear"></div>
                </div>
            </div>
        </div>
    </div>
</div>