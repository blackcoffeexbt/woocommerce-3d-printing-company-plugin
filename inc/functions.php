<?php
/**
 * Add menu item
 */
function threedp_admin_menu()
{
    add_menu_page('Printing List', 'Printing List', 'manage_options', 'printing-list.php', 'printing_list_admin_page', 'dashicons-hammer', 6);
}

/**
 * Display the print list admin page
 */
function printing_list_admin_page()
{
    $url = "/wp-admin/admin.php?page=printing-list.php";

    $status_filter = sanitize_text_field($_GET['status']);
    ?>
    <div class="wrap">
        <h1>Printing List</h1>
        <p><strong>Filter By</strong>
            <?php if ($status_filter == 'pending'): ?>
                <strong>Pending</strong>
            <?php else: ?>
                <a href="<?php echo $url ?>&status=pending">Pending</a>
            <?php endif; ?>
            |
            <?php if ($status_filter == 'printing'): ?>
                <strong>Printing</strong>
            <?php else: ?>
                <a href="<?php echo $url ?>&status=printing">Printing</a>
            <?php endif; ?>
            |
            <?php if ($status_filter == 'completed'): ?>
                <strong>Completed</strong>
            <?php else: ?>
                <a href="<?php echo $url ?>&status=completed">Completed</a>
            <?php endif; ?>
        </p>
        <?php
        //    threedp_set_product_print_status(958, 441, 743, 'printing');
        threedp_get_orders_with_printed_parts();
        ?>
    </div>
    <?php
}

/**
 * Get orders and print out a list of items and their print statuses
 * @throws Exception
 */
function threedp_get_orders_with_printed_parts()
{
    $query = new WC_Order_Query(array(
        'limit'   => - 1,
        'orderby' => 'date',
        'order'   => 'ASC',
        'return'  => 'ids',
        'status'  => array('wc-processing'),
    ));

    $status_filter = sanitize_text_field($_GET['status']);
    $orders        = array_reverse($query->get_orders());

    $products_without_acf_field_set = [];

    foreach($orders as $order_id) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();

        echo "<div class='bc-printing-list__order' id=\"order-{$order_id}\">";
        $i = 0;
        foreach($items as $item) {
            $product_id   = $item->get_product_id();
            $product_name = "";
            $product      = null;
            // Does it have 3D printed parts?
            $has_3d_printed_components = get_field('has_3d_printed_components', $product_id);
            if ($has_3d_printed_components === 'yes') {
                if ($i === 0) {
                    echo "<h2><a href=\"/wp-admin/post.php?post={$order_id}&action=edit\">Order {$order_id}</a></h2>";
                    $i ++;
                }
                if ($product_variation_id = $item->get_variation_id()) {
                    $product      = wc_get_product($product_variation_id);
                    $product_name = $product->get_title();
                } else {
                    $product      = wc_get_product($product_id);
                    $product_name = $product->get_title();
                }
                for($i = 0; $i < $item->get_quantity(); $i ++) {
                    $status = threedp_get_product_print_status($order_id, $product_id, $product_variation_id, $i);
                    if ($status_filter == "" || $status_filter === $status || ($status_filter === 'pending' && $status === null)) {
                        echo "<div  class=\"bc-printing-list__status bc-printing-list__status--" . sanitize_title($status) . "\">";
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
                        echo threedp_get_status_form($order_id, $product_id, $product_variation_id, $i);
                        echo "</div>";
                    }
                }
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
 *
 * @param $order_id
 * @param $product_id
 * @param $variation_id
 * @param $status
 *
 * @return bool|int
 */
function threedp_set_product_print_status($order_id, $product_id, $variation_id, $index, $status)
{
    return update_post_meta($order_id, threedp_get_print_status_meta_key($product_id, $variation_id, $index), $status);
}

/**
 * Get the print status of an order item
 *
 * @param $order_id
 * @param $product_id
 * @param $variation_id
 *
 * @return mixed|string
 */
function threedp_get_product_print_status($order_id, $product_id, $variation_id, $index)
{
    $meta = get_post_meta($order_id, threedp_get_print_status_meta_key($product_id, $variation_id, $index));
    if (sizeof($meta)) {
        return $meta[0];
    }

    return 'pending';
}

/**
 * Generate a slug for a product variation
 *
 * @param $product_id
 * @param $variation_id
 *
 * @return string
 */
function threedp_get_product_variation_slug($product_id, $variation_id, $index = 0)
{
    $str = implode('', func_get_args());

    return $str;
}

/**
 * Get the meta key for an order's item
 *
 * @param $product_id
 * @param $variation_id
 *
 * @return string
 */
function threedp_get_print_status_meta_key($product_id, $variation_id, $index = 0)
{
    return "print-status-" . threedp_get_product_variation_slug($product_id, $variation_id, $index);
}

/**
 * Get the HTML snippet for the status update form
 *
 * @param $order_id
 * @param $product_id
 * @param null $variation_id
 */
function threedp_get_status_form($order_id, $product_id, $variation_id = null, $index = 0)
{
    $status             = threedp_get_product_print_status($order_id, $product_id, $variation_id, $index);
    $available_statuses = [
        'pending',
        'printing',
        'completed',
    ];

    $status_filter = sanitize_text_field($_GET['status']);
    ?>
    <form method="POST" action="<?php echo esc_html(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="threedp_print_status_update">
        <?php wp_nonce_field('threedp_print_status_update', 'threedp_admin'); ?>
        <input type="hidden" name="order_id" value="<?php echo $order_id ?>">
        <input type="hidden" name="product_id" value="<?php echo $product_id ?>">
        <input type="hidden" name="variation_id" value="<?php echo $variation_id ?>">
        <input type="hidden" name="index" value="<?php echo $index ?>">
        <input type="hidden" name="redirect_to_url"
               value="/wp-admin/admin.php?page=printing-list.php<?php echo $status_filter ? "&status={$status_filter}" : '' ?>#order-<?php echo $order_id ?>">
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
function threedp_update_status()
{
//    $nonce = sanitize_text_field($_POST[NONCE_KEY]);
//    $action = sanitize_text_field($_POST['action']);
//    if (!isset($nonce) || !wp_verify_nonce($nonce, $action)) {
//        print 'Sorry, your nonce did not verify.';
//        exit;
//    }

    $order_id      = intval(sanitize_text_field($_POST['order_id']));
    $product_id    = intval(sanitize_text_field($_POST['product_id']));
    $variation_id  = intval(sanitize_text_field($_POST['variation_id']));
    $status_filter = intval(sanitize_text_field($_POST['status_filter']));
    $index         = intval(sanitize_text_field($_POST['index']));
    $status        = sanitize_text_field($_POST['status']);

    if ( ! current_user_can('manage_options')) {
        print 'You can\'t manage options';
        exit;
    }
    threedp_set_product_print_status($order_id, $product_id, $variation_id, $index, $status);

    $product      = wc_get_product($variation_id);
    $product_description = $product->get_title();

    if ($product->is_type('variation')) {
        // Get the variation attributes
        $variation_attributes = $product->get_variation_attributes();
        // Loop through each selected attributes
        foreach($variation_attributes as $attribute_taxonomy => $term_slug) {
            // Get product attribute name or taxonomy
            $taxonomy = str_replace('attribute_', '', $attribute_taxonomy);
            // The label name from the product attribute
//            $product_description .= " " . wc_attribute_label($taxonomy, $product) . ": ";
            // The term name (or value) from this attribute
            if (taxonomy_exists($taxonomy)) {
                $attribute_value = get_term_by('slug', $term_slug, $taxonomy)->name;
            } else {
                $attribute_value = $term_slug; // For custom product attributes
            }
            $product_description .= " - " . $attribute_value;
        }
    }

    $status = "";
    switch($status_filter) {
        case 'printing':
            $status = " has started printing!";
            break;
        case 'completed':
            $status = " has finished printing and is ready to ship!";
            break;
    }
    $nostr_message = "{$product_description} for order {$order_id} " . $status;

    threedp_send_nostr_note($nostr_message);

    $redirect_to = $_POST['redirect_to_url'];
    if ($redirect_to) {
        wp_safe_redirect($redirect_to);
        exit;
    }
}

function threedp_send_nostr_note($message){

    $url = 'http://sats.pw:3000/api/send';

    $curl = curl_init($url);

    $data = "{
  \"message\": \"{$message}\",
}";

    $postData = array(
        'message' => $message
    );

    curl_setopt_array($curl, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        CURLOPT_POSTFIELDS => json_encode($postData)
    ));

    $resp = curl_exec($curl);
    echo $resp;
    curl_close($curl);

    return $resp;
}

/**
 * Admin CSS
 */
function threedp_load_css()
{
    $url = plugins_url('css/style.css', realpath(__DIR__));
    wp_enqueue_style('print_admin_css', $url, false, '1.0.0');
}
