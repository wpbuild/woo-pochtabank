<?php

// Register new custom order status
add_action('init', 'register_custom_order_statuses');
function register_custom_order_statuses() {
    register_post_status('wc-pochta-bank ', array(
        'label' => __( 'ПочтаБанк', 'woocommerce' ),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('ПочтаБанк <span class="count">(%s)</span>', 'ПочтаБанк <span class="count">(%s)</span>')
    ));
}


// Add new custom order status to list of WC Order statuses
add_filter('wc_order_statuses', 'add_custom_order_statuses');
function add_custom_order_statuses($order_statuses) {
    $new_order_statuses = array();

    // add new order status before processing
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
            $new_order_statuses['wc-pochta-bank'] = __('Кредит ПочтаБанк', 'woocommerce' );
        }
    }
    return $new_order_statuses;
}


// Adding new custom status to admin order list bulk dropdown
add_filter( 'bulk_actions-edit-shop_order', 'custom_dropdown_bulk_actions_shop_order', 50, 1 );
function custom_dropdown_bulk_actions_shop_order( $actions ) {
    $new_actions = array();

    // add new order status before processing
    foreach ($actions as $key => $action) {
        if ('mark_processing' === $key)
            $new_actions['mark_pochta-bank'] = __( 'Кредит Почта Банк', 'woocommerce' );

        $new_actions[$key] = $action;
    }
    return $new_actions;
}


add_filter( 'woocommerce_admin_order_actions', 'add_new_list_for_action', 1, 2);

function add_new_list_for_action($actions, $the_order) {
	if ( $the_order->has_status( array( 'pochta-bank' ) ) ) {
    	$status = $_GET['status'];
        $order_id = method_exists($the_order, 'get_id') ? $the_order->get_id() : $the_order->id;

			$actions['processing'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=processing&order_id=' . $order_id ), 'woocommerce-mark-order-status' ),
				'name'   => __( 'Processing', 'woocommerce' ),
				'action' => 'processing',
			);
			$actions['complete'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $order_id ), 'woocommerce-mark-order-status' ),
				'name'   => __( 'Complete', 'woocommerce' ),
				'action' => 'complete',
			);

	}
    return $actions;
}
