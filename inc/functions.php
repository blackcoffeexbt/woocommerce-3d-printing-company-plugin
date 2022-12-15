<?php
/**
 * Add menu item
 */
function bc_admin_menu()
{
    add_menu_page('Printing List', 'Printing List', 'manage_options', 'printing-list.php', 'printing_list_admin_page', 'dashicons-hammer', 6);
}

/**
 * Display the print list admin page
 */
function printing_list_admin_page()
{
    ?>
    <div class="wrap">
        <h1>Printing List</h1>
        <?php
        //    bc_set_product_print_status(958, 441, 743, 'printing');
        bc_get_orders_with_printed_parts();
        ?>
    </div>
    <?php
}

/**
 * Get orders and print out a list of items and their print statuses
 * @throws Exception
 */
function bc_get_orders_with_printed_parts()
{
    $query  = new WC_Order_Query(array(
        'limit'   => - 1,
        'orderby' => 'date',
        'order'   => 'ASC',
        'return'  => 'ids',
        'status'  => array('wc-processing'),
    ));
    $orders = array_reverse($query->get_orders());

    $products_without_acf_field_set = [];

    foreach($orders as $order_id) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();

        echo "<div class='bc-printing-list__order' id=\"order-{$order_id}\">";
        $i = 0;
        foreach($items as $item) {
            $product_id   = $item->get_product_id();
            $product_name = "";
            $product = null;
            // Does it have 3D printed parts?
            $has_3d_printed_components = get_field('has_3d_printed_components', $product_id);
            if ($has_3d_printed_components === 'yes') {
                if($i === 0) {
                    echo "<h2><a href=\"/wp-admin/post.php?post={$order_id}&action=edit\">Order {$order_id}</a></h2>";
                    $i++;
                }
                if ($product_variation_id = $item->get_variation_id()) {
                    $product      = wc_get_product($product_variation_id);
                    $product_name = $product->get_title();
                } else {
                    $product      = wc_get_product($product_id);
                    $product_name = $product->get_title();
                }
                $status = bc_get_product_print_status($order_id, $product_id, $product_variation_id);
                echo "<div  class=\"bc-printing-list__status bc-printing-list__status--" . sanitize_title($status) . "\">";
                for($i =0; $i < $item->get_quantity(); $i++) {
                    echo "<p>{$product_name}</p>";
                    if ($product->is_type('variation')) {
                        // Get the variation attributes
                        $variation_attributes = $product->get_variation_attributes();
                        // Loop through each selected attributes
                        foreach($variation_attributes as $attribute_taxonomy => $term_slug) {
                            // Get product attribute name or taxonomy
                            $taxonomy = str_replace('attribute_', '', $attribute_taxonomy);
                            // The label name from the product attribute
                            echo "<p><strong>" . wc_attribute_label($taxonomy, $product) . ":</strong> ";
                            // The term name (or value) from this attribute
                            if (taxonomy_exists($taxonomy)) {
                                $attribute_value = get_term_by('slug', $term_slug, $taxonomy)->name;
                            } else {
                                $attribute_value = $term_slug; // For custom product attributes
                            }
                            echo $attribute_value;
                            echo "</p>";
                        }
                    }
                    echo bc_get_status_form($order_id, $product_id, $product_variation_id);
                }
                echo "</div>";
            } else if ($has_3d_printed_components === null) {
                if ( ! in_array($product_id, $products_without_acf_field_set)) {
                    $products_without_acf_field_set[] = $product_id;
                }
            }
        }
        echo "</div>";

    }

    foreach($products_without_acf_field_set as $product_id) {
        $product      = wc_get_product($product_id);
        $product_name = $product->get_title();
        echo "<p>\"Has 3D printed parts?\" field is not set for \"$product_name\".<br>
        <a class=\"button button-primary button-large\" href=\"/wp-admin/post.php?post={$product_id}&action=edit\">Set it now</a>
        </p>";
    }
}

/**
 * Set the print status for an order item
 * @param $order_id
 * @param $product_id
 * @param $variation_id
 * @param $status
 *
 * @return bool|int
 */
function bc_set_product_print_status($order_id, $product_id, $variation_id, $status)
{
    return update_post_meta($order_id, bc_get_print_status_meta_key($product_id, $variation_id), $status);
}

/**
 * Get the print status of an order item
 * @param $order_id
 * @param $product_id
 * @param $variation_id
 *
 * @return mixed|string
 */
function bc_get_product_print_status($order_id, $product_id, $variation_id)
{
    $meta = get_post_meta($order_id, bc_get_print_status_meta_key($product_id, $variation_id));
    if (sizeof($meta)) {
        return $meta[0];
    }

    return 'pending';
}

/**
 * Generate a slug for a product variation
 * @param $product_id
 * @param $variation_id
 *
 * @return string
 */
function bc_get_product_variation_slug($product_id, $variation_id)
{
    return implode('', func_get_args());
}

/**
 * Get the meta key for an order's item
 * @param $product_id
 * @param $variation_id
 *
 * @return string
 */
function bc_get_print_status_meta_key($product_id, $variation_id)
{
    return "print-status-" . bc_get_product_variation_slug($product_id, $variation_id);
}

/**
 * Get the HTML snippet for the status update form
 * @param $order_id
 * @param $product_id
 * @param null $variation_id
 */
function bc_get_status_form($order_id, $product_id, $variation_id = null)
{
    $status             = bc_get_product_print_status($order_id, $product_id, $variation_id);
    $available_statuses = [
        'pending',
        'printing',
        'completed',
    ];
    ?>
    <form method="POST" action="<?php echo esc_html(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="bc_print_status_update">
        <?php wp_nonce_field('bc_print_status_update', 'bc_admin'); ?>
        <input type="hidden" name="order_id" value="<?php echo $order_id ?>">
        <input type="hidden" name="product_id" value="<?php echo $product_id ?>">
        <input type="hidden" name="variation_id" value="<?php echo $variation_id ?>">
        <input type="hidden" name="redirect_to_url"
               value="/wp-admin/admin.php?page=printing-list.php#order-<?php echo $order_id ?>">
        <select name="status">
            <?php foreach($available_statuses as $available_status): ?>
                <option value="<?php echo $available_status ?>" <?php echo $available_status === $status ? 'selected' : '' ?>><?php echo ucfirst($available_status) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="submit" class="button button-primary button-large" value="Update">
    </form>
    <?php
}

/**
 * Update an items print status
 */
function bc_update_status()
{
//    $nonce = sanitize_text_field($_POST[NONCE_KEY]);
//    $action = sanitize_text_field($_POST['action']);
//    if (!isset($nonce) || !wp_verify_nonce($nonce, $action)) {
//        print 'Sorry, your nonce did not verify.';
//        exit;
//    }

    $order_id     = intval(sanitize_text_field($_POST['order_id']));
    $product_id   = intval(sanitize_text_field($_POST['product_id']));
    $variation_id = intval(sanitize_text_field($_POST['variation_id']));
    $status       = sanitize_text_field($_POST['status']);

    if ( ! current_user_can('manage_options')) {
        print 'You can\'t manage options';
        exit;
    }
    bc_set_product_print_status($order_id, $product_id, $variation_id, $status);
    $redirect_to = $_POST['redirect_to_url'];
    if ($redirect_to) {
        wp_safe_redirect($redirect_to);
        exit;
    }
}

/**
 * Admin CSS
 */
function bc_load_css() {
    $url = plugins_url( 'css/style.css' , realpath(__DIR__ ) );
    wp_enqueue_style( 'print_admin_css', $url, false, '1.0.0' );
}