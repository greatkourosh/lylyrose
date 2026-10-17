<?php
/**
 * Plugin Name: Digikala Product Link Display
 * Description: Shows Digikala product link in WooCommerce product admin
 * Version: 1.0
 */

// Add custom field to product general tab
add_action('woocommerce_product_options_general_product_data', 'digikala_product_link_field');

function digikala_product_link_field() {
    global $post;

    $digikala_url = get_post_meta($post->ID, '_digikala_url', true);
    $digikala_id = get_post_meta($post->ID, '_digikala_id', true);
    $digikala_title = get_post_meta($post->ID, '_digikala_title', true);

    if (empty($digikala_url)) {
        return;
    }

    echo '<div class="options_group digikala-link-section" style="padding: 12px; background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 4px; margin: 12px;">';
    echo '<h4 style="margin: 0 0 8px 0; color: #0c4a6e; display: flex; align-items: center; gap: 8px;">';
    echo '<span class="dashicons dashicons-admin-links" style="color: #0ea5e9;"></span>';
    echo 'محصول در دیجی‌کالا';
    echo '</h4>';

    if ($digikala_title) {
        echo '<p style="margin: 0 0 8px 0; font-size: 13px; color: #475569;">' . esc_html($digikala_title) . '</p>';
    }

    echo '<p style="margin: 0;">';
    echo '<a href="' . esc_url($digikala_url) . '" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #0ea5e9; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">';
    echo '<span class="dashicons dashicons-external" style="font-size: 16px;"></span>';
    echo 'مشاهده در دیجی‌کالا';
    echo '</a>';

    if ($digikala_id) {
        echo '<span style="margin-right: 12px; color: #64748b; font-size: 12px;">کد: ' . esc_html($digikala_id) . '</span>';
    }

    echo '</p>';
    echo '</div>';
}

// Display in product list table
add_filter('manage_edit-product_columns', 'digikala_product_column');

function digikala_product_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'name') {
            $new_columns['digikala'] = '<span class="dashicons dashicons-admin-links" title="دیجی‌کالا"></span>';
        }
    }
    return $new_columns;
}

add_action('manage_product_posts_custom_column', 'digikala_product_column_content', 10, 2);

function digikala_product_column_content($column, $post_id) {
    if ($column === 'digikala') {
        $digikala_url = get_post_meta($post_id, '_digikala_url', true);
        if ($digikala_url) {
            echo '<a href="' . esc_url($digikala_url) . '" target="_blank" rel="noopener" title="مشاهده در دیجی‌کالا" style="color: #0ea5e9;">';
            echo '<span class="dashicons dashicons-external"></span>';
            echo '</a>';
        } else {
            echo '<span style="color: #cbd5e1;">—</span>';
        }
    }
}
