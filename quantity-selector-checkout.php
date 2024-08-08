
<?php
/**
 * Plugin Name: Quantity Input Field in Checkout Page
 * Description: Adds a quantity selector to the checkout page and updates prices dynamically.
 * Version: 1.0
 * Author: Your Name
 */

// Register and enqueue the JavaScript and CSS files
function qsc_enqueue_scripts() {
    wp_enqueue_script(
        'qsc-js', // Handle for the JavaScript file
        plugins_url('quantity-selector-checkout.js', __FILE__), // URL to the script
        array('jquery'), // Ensure jQuery is loaded first
        null, // Version of the script (optional)
        true // Load the script in the footer
    );

    wp_localize_script(
        'qsc-js', // Handle for the script
        'qscParams', // Name of the JavaScript object to contain data
        array(
            'ajax_url' => admin_url('admin-ajax.php'), // AJAX URL to be used in JavaScript
        )
    );

    // Enqueue the CSS file
    wp_enqueue_style(
        'qsc-css', // Handle for the CSS file
        plugins_url('delete-icon.css', __FILE__) // URL to the CSS file
    );
}
add_action('wp_enqueue_scripts', 'qsc_enqueue_scripts');

// Register the callback for cart updates
add_action('woocommerce_blocks_loaded', function() {
    // Register the callback function
    woocommerce_store_api_register_update_callback(
        [
            'namespace' => 'quantity-selector',
            'callback'  => function( $data ) {
                // Check if itemId and quantity are set
                if (isset($data['itemId']) && isset($data['quantity'])) {
                    $item_id = intval($data['itemId']);
                    $quantity = intval($data['quantity']);

                    // Update the cart item quantity
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        if ($cart_item['product_id'] === $item_id) {
                            WC()->cart->set_quantity($cart_item_key, $quantity);
                        }
                    }

                    // Recalculate cart totals
                    WC()->cart->calculate_totals();
                } elseif (isset($data['itemId']) && isset($data['action']) && $data['action'] === 'delete') {
                    // Remove the item from the cart
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        if ($cart_item['product_id'] === intval($data['itemId'])) {
                            WC()->cart->remove_cart_item($cart_item_key);
                        }
                    }

                    // Recalculate cart totals
                    WC()->cart->calculate_totals();
                }
            },
        ]
    );
});

// Filter to add quantity input and delete icon in classic checkout
add_filter('woocommerce_checkout_cart_item_quantity', 'ts_checkout_item_quantity_input', 10, 3);
function ts_checkout_item_quantity_input($product_quantity, $cart_item, $cart_item_key) {
    $product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
    $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
    if (!$product->is_sold_individually()) {
        $product_quantity = '<div class="quantity-and-remove">';
        $product_quantity .= woocommerce_quantity_input(array(
            'input_name'  => 'shipping_method_qty_' . $product_id,
            'input_value' => $cart_item['quantity'],
            'max_value'   => $product->get_max_purchase_quantity(),
            'min_value'   => '0',
        ), $product, false);
        $product_quantity .= '<input type="hidden" name="product_key_' . $product_id . '" value="' . $cart_item_key . '">';
        $product_quantity .= sprintf(
            '<a href="%s" class="delete" title="%s" data-product_id="%s" data-product_sku="%s"><span class="fas fa-trash-alt"></span></a>',
            esc_url(wc_get_cart_remove_url($cart_item_key)),
            __('Remove this item', 'woocommerce'),
            esc_attr($product_id),
            esc_attr($product->get_sku())
        );
        $product_quantity .= '</div>';
    }
    return $product_quantity;
}

// Detect quantity change and recalculate totals
add_action('woocommerce_checkout_update_order_review', 'ts_update_item_quantity_checkout');
function ts_update_item_quantity_checkout($post_data) {
    parse_str($post_data, $post_data_array);
    $updated_qty = false;
    foreach ($post_data_array as $key => $value) {
        if (substr($key, 0, 20) === 'shipping_method_qty_') {
            $id = substr($key, 20);
            WC()->cart->set_quantity($post_data_array['product_key_' . $id], $post_data_array[$key], false);
            $updated_qty = true;
        }
    }
    if ($updated_qty) WC()->cart->calculate_totals();
}
?>
