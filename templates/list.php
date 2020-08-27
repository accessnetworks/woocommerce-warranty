<?php

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Warranty_List_Table extends WP_List_Table {

    private $form;
    private $inputs;
    private $row_reason_injected = false;
    private $num_columns = 0;
    private $statuses = null;

    function __construct( $args = array() ) {
        parent::__construct($args);

        $this->form   = get_option( 'warranty_form' );
        $this->inputs = json_decode($this->form['inputs']);

        add_filter( 'posts_clauses', array($this, 'status_orderby_clauses'), 10, 2 );

        $this->get_statuses();
    }

    function status_orderby_clauses( $clauses, $wp_query ) {
        global $wpdb;

        if ( isset( $wp_query->query['orderby'] ) && 'shop_warranty_status' == $wp_query->query['orderby'] ) {

            $clauses['join'] .= <<<SQL
LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
LEFT OUTER JOIN {$wpdb->terms} USING (term_id)
SQL;

            $clauses['where'] .= " AND (taxonomy = 'shop_warranty_status' OR taxonomy IS NULL)";
            $clauses['groupby'] = "object_id";
            $clauses['orderby']  = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC) ";
            $clauses['orderby'] .= ( 'ASC' == strtoupper( $wp_query->get('order') ) ) ? 'ASC' : 'DESC';
        }

        return $clauses;
    }

    function get_statuses() {
        if ( is_null( $this->statuses ) ) {
            $this->statuses = warranty_get_statuses();
        }

        return $this->statuses;
    }

    function get_columns(){
        $columns = array(
            'rma'           => __('Return Details', 'wc_warranty'),
            'products'      => __('Products', 'wc_warranty'),
            'request_type'  => __('Request Type', 'wc_warranty'),
            'date'          => __('Last Updated', 'wc_warranty'),
            'status'        => __('Status', 'wc_warranty')
        );

        $this->num_columns = count( $columns );

        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'order_id'  => array('order_id',false),
            'status'    => array('shop_warranty_status',false),
            'date'      => array('date',true)
        );
        return $sortable_columns;
    }

    function extra_tablenav( $which ) {
        if ( $which == 'top' ) {

            echo '<form action="admin.php" method="get" style="margin-top: 20px;">';
            echo '  <div class="alignleft actions">';
            echo '      <select name="status" id="status" class="postform">';
            echo '          <option value="">'. __('All Statuses', 'wc_warranty') .'</option>';

            foreach ($this->get_statuses() as $status ) {
                $selected = ( isset($_GET['status']) && $status->slug == $_GET['status'] ) ? 'selected' : '';
                echo '          <option value="'. $status->slug .'" '. $selected .'>'. $status->name .'</option>';
            }

            echo '      </select>';
            echo '      <input type="hidden" name="page" value="warranties" />';
            submit_button( __( 'Filter', 'wc_warranty' ), 'secondary', false, false, array( 'id' => 'post-query-submit' ) );
            echo '  </div>';
            echo '</form>';
        }
    }

    function prepare_items() {
        global $wpdb;

        $columns    = $this->get_columns();
        $hidden     = array();
        $sortable   = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $per_page       = 10;
        $current_page   = $this->get_pagenum();
        $query_args     = array(
            'post_type'         => 'warranty_request',
            'orderby'           => 'date',
            'order'             => 'desc',
            'posts_per_page'    => $per_page,
            'paged'             => $current_page
        );

        if ( !empty( $_GET['orderby'] ) ) {
            $query_args['orderby']  = $_GET['orderby'];
            $query_args['order']    = $_GET['order'];
        }

        // filter by status
        if ( isset($_GET['status']) && !empty($_GET['status']) ) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy'  => 'shop_warranty_status',
                    'field'     => 'slug',
                    'terms'     => $_GET['status']
                )
            );
        }

        if ( isset($_GET['s']) && !empty($_GET['s']) ) {
            $query_args['meta_query'][] = array(
                'key'       => '_code',
                'value'     => $_GET['s'],
                'compare'   => 'LIKE'
            );
        }

        if ( class_exists( 'WC_Product_Vendors_Utils' ) ) {
            $vendor_id = WC_Product_Vendors_Utils::get_logged_in_vendor( 'id' );

            if ( $vendor_id ) {
                $order_ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT order_id
                    FROM {$wpdb->prefix}wcpv_commissions c, {$wpdb->postmeta} pm
                    WHERE c.vendor_id = %d
                    AND c.order_id = pm.meta_value
                    AND pm.meta_key = '_order_id'",
                    $vendor_id
                ) );

                $query_args['meta_query'][] = array(
                    'key'       => '_order_id',
                    'value'     => $order_ids,
                    'compare'   => 'IN'
                );
            }
        }

        $wp_query = new WP_Query();
        $wp_query->query($query_args);

        $total_items = $wp_query->found_posts;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ) );

        $this->items = array();

        while ( $wp_query->have_posts() ) :
            $wp_query->the_post();
            $id = get_the_ID();
            $this->items[] = warranty_load($id);
        endwhile;

        wp_reset_postdata();
    }

    function column_default( $item, $column_name ) {
        $requests_str = array(
            'replacement'   => __('Replacement item', 'wc_warranty'),
            'refund'        => __('Refund', 'wc_warranty'),
            'coupon'        => __('Refund as store credit', 'wc_warranty')
        );

        if ( $column_name == 'request_type' ) {
            if ( empty( $item['request_type'] ) || !array_key_exists( $item['request_type'], $requests_str ) ) {
                $item['request_type'] = 'replacement';
            }

            return $requests_str[ $item['request_type'] ];
        }

        switch( $column_name ) {
            case 'order_id':
            case 'customer':
            case 'rma':
            case 'tracking':
            case 'date':
                return $item[ $column_name ];
                break;
            default:
                break;
        }
    }

    function column_status($item) {
        $statuses   = warranty_get_statuses();

        $order_id   = get_post_meta( $item['ID'], '_order_id', true );
        $order      = wc_get_order( $order_id );
        $permissions= get_option('warranty_permissions', array());
        $returned   = get_option('warranty_returned_status', 'completed');
        $term       = wp_get_post_terms( $item['ID'], 'shop_warranty_status' );
        $status     = $term[0];
        $me         = wp_get_current_user();
        $readonly   = true;

        if (in_array('administrator', $me->roles)) {
            $readonly = false;
        } elseif (!isset($permissions[$status->slug]) || empty($permissions[$status->slug])) {
            $readonly = false;
        } elseif (in_array($me->ID, $permissions[$status->slug])) {
            $readonly = false;
        }

        if ( $readonly ) {
            $content = ucfirst($status->name);
        } else {
            $content    = '<select name="status" id="status_'. $item['ID'] .'">';

            foreach ($statuses as $_status ):
                $sel        = ($status->slug == $_status->slug) ? 'selected' : '';
                $content   .= '<option value="'. $_status->slug .'" '. $sel .'>'. ucfirst($_status->name) .'</option>';
            endforeach;

            $content .= '</select>
            <button class="button update-status" type="button" title="Update" data-request_id="'. $item['ID'] .'"><span>'. __('Update', 'wc_warranty') .'</span></button>
            ';
        }
        return $content;
    }
    
    function column_email($item) {
        $email = get_post_meta( $item['ID'], '_email', true );
        return $email;
    }    
    
    function column_reason($item) {
        return (empty($item['reason'])) ? '-' : $item['reason'];
    }

    function column_products($item) {
        $products = warranty_get_request_items( $item['ID'] );
        $out = '';

        foreach ( $products as $product ) {

	        if ( empty( $product['product_id'] ) && empty( $item['product_name'] ) ) {
		        continue;
	        }

            if ( $product['product_id'] == 0 ) {
                $out .= $item['product_name'] .'<br/>';
            } else {
                $title = warranty_get_product_title( $product['product_id'] );
                $out .= '<a href="post.php?post='. $product['product_id'] .'&action=edit">'. $title .'</a> &times; '. $product['quantity'] .'<br/>';
            }
        }

        return $out;
    }

    function column_note($item) {
        return (isset($item['answer'])) ? $item['answer'] : '-';
    }

    function column_rma($item) {
        $statuses   = warranty_get_statuses();
        $returned   = get_option('warranty_returned_status', 'completed');
        $term       = wp_get_post_terms( $item['ID'], 'shop_warranty_status' );
        $status     = (!empty($term)) ? $term[0]->slug : current($statuses);
        $request_type = empty( $item['request_type'] ) ? 'replacement' : $item['request_type'];
        $order      = wc_get_order( $item['order_id'] );
        $link       = '';

        if ( $order && WC_Warranty_Compatibility::get_order_prop( $order, 'customer_user' ) ) {
            $link = get_edit_user_link( WC_Warranty_Compatibility::get_order_prop( $order, 'customer_user' ) );
        }

        if ( $link ) {
            $customer   = '<a href="'. $link .'">'. $item['first_name'] .' '. $item['last_name'] .'</a><small class="meta">'. $item['email'] .'</small>';
        } else {
            $customer   = '<strong>'. $item['first_name'] .' '. $item['last_name'] .'</strong><small class="meta">'. $item['email'] .'</small>';
        }

        $order_number = ( $order ) ? $order->get_order_number() : '-';

        if (! $order ) {
            if ( class_exists('WC_Seq_Order_Number') ) {
                $order_id = $GLOBALS['wc_seq_order_number']->find_order_by_order_number( $item['order_id'] );

                if ( $order_id ) {
                    $item_order = '<a href="post.php?post='. $order_id .'&action=edit">#'. $item['order_id'] .'</a>';
                } else {
                    $item_order = '#'. $item['order_id'];
                }
            } else {
                $item_order = '#'. $item['order_id'];
            }
        } else {
            $item_order = '<a href="post.php?post='. $item['order_id'] .'&action=edit">#'. $order_number .'</a>';
        }

        $order_str = sprintf( __('Order %s', 'wc_warranty'), $item_order );

        $actions = array(
            'inline-edit' => '<a href="#" class="inline-edit" data-request_id="'. $item['ID'] .'">'. __('Manage', 'wc_warranty') .'</a>'
        );

        $product_id     = get_post_meta( $item['ID'], '_product_id', true );
        $product        = wc_get_product( $product_id );
        $manage_stock   = '';

        if ( $product && $product->is_type('variation') ) {
            $variation_id = ( version_compare( WC_VERSION, '3.0', '<' ) && isset( $product->variation_id ) ) ? $product->variation_id : $product->get_id();
            $stock = get_post_meta( $variation_id, '_stock', true );

            if ($stock > 0)
                $manage_stock = 'yes';
        } else {
            $manage_stock = get_post_meta( $product_id, '_manage_stock', true );
        }

        if ( $status == $returned && $manage_stock == 'yes' ) {
            if ( get_post_meta( $item['ID'], '_returned', true ) == 'yes' ) {
                $actions['inventory-return'] = __('Stock Returned', 'wc_warranty');
            } else {
                $actions['inventory-return'] = '<a href="'. wp_nonce_url( 'admin-post.php?action=warranty_return_inventory&id='. $item['ID'], 'warranty_return_inventory' ) .'">'. __('Return Stock', 'wc_warranty') .'</a>';
            }
        }

        if ( 'completed' === $status ) {
            $refunded           = get_post_meta( $item['ID'], '_refunded', true );
            $amount_refunded    = get_post_meta( $item['ID'], '_refund_amount', true );

            if (! $amount_refunded ) {
                $amount_refunded = 0;
            }

            if ( $refunded == 'yes' ) {
                $request_type = 'refund';
            }

            if ( $request_type == 'refund' ) {
                $actions['item-refund'] = '<a class="thickbox" title="'. __('Refund', 'wc_warranty') .'" href="#TB_inline?width=400&height=250&inlineId=warranty-refund-modal-'. $item['ID'] .'">'. __('Refund Item', 'wc_warranty') .'</a>';
            } elseif ( $request_type == 'coupon' ) {
                $actions['item-coupon'] = '<a class="thickbox" title="'. __('Send Coupon', 'wc_warranty') .'" href="#TB_inline?width=400&height=250&inlineId=warranty-coupon-modal-'. $item['ID'] .'">'. __('Send Coupon', 'wc_warranty') .'</a>';
            }
        }

        $actions['trash'] = '<a href="'. wp_nonce_url( 'admin-post.php?action=warranty_delete&id='. $item['ID'], 'warranty_delete') .'" class="submitdelete warranty-delete">'. __('Delete', 'wc_warranty') .'</a>';

        $content = sprintf(__('<strong>%s</strong> by %s on %s', 'wc_warranty'), $item['code'], $customer, $order_str );

        $content = sprintf('%1$s %2$s', $content, $this->row_actions($actions) );

        return $content;
    }

    function column_tracking($item) {
        $output = '';
        $tracking = warranty_get_tracking_data( $item['ID'] );

        if ( empty( $tracking ) ) {
            $output = '-';
        } else {
            if ( !empty( $tracking['store'] ) ) {
                $output .= '<p><strong>Store:</strong> '. $tracking['store'] .'</p>';
            }

            if ( !empty( $tracking['customer'] ) ) {
                $output .= '<p><strong>Customer:</strong> '. $tracking['customer'] .'</p>';
            }
        }

        return $output;
    }

    function column_date($item) {
        return $item['post_modified'];
    }

    function column_attachment($item) {
        if (! empty($item['attachment']) ) {
            $wp_uploads = wp_upload_dir();
            echo '<a href="'. $wp_uploads['baseurl'] . $item['attachment'] .'">'. basename($item['attachment']) .'</a>';
        } else {
            echo '-';
        }

    }

    function no_items() {
        _e( 'No requests found.', 'wc_warranty' );
    }

    function display() {
        parent::display();

        $update_nonce = wp_create_nonce( 'warranty_update' );

        echo '<form method="get"><table style="display: none"><tbody id="inlineedit">';
        foreach ( $this->items as $request ) {
            $request_id = $request['ID'];
            include WooCommerce_Warranty::$base_path .'templates/list-item-details.php';
        }
        echo '</tbody></table></form>';

        foreach ( $this->items as $request ) {
            $item_amount    = warranty_get_item_amount( $request['ID'] );
            $refunded       = (int) get_post_meta( $request['ID'], '_refund_amount', true );
            $available      = max(0, $item_amount - $refunded);
            $notes          = array();

            include WooCommerce_Warranty::$base_path .'templates/list-item-refunds.php';
        }
    }
}

?>
<div class="wrap woocommerce">
    <style type="text/css">
        table.toplevel_page_warranties #status { width: 200px; }
        .wc-updated {width: 95%; margin: 5px 0 15px; background-color: #ffffe0; border-color: #e6db55; padding: 0 .6em; -webkit-border-radius: 3px; border-radius: 3px; border-width: 1px; border-style: solid;}
        .wc-updated p {margin: .5em 0 !important; padding: 2px;}
        #tiptip_holder #tiptip_content { max-width: 350px; }
        .inline-edit-col h4 {margin-top: 15px;}
    </style>
    <h2><?php _e('RMA Requests', 'wc_warranty'); ?></h2>
<?php
if ( isset($_GET['updated']) ) {
    echo '<div class="updated"><p>'. $_GET['updated'] .'</p></div>';
}
$warranty_table = new Warranty_List_Table();
$warranty_table->prepare_items();
?>
    <form action="admin.php" method="get" style="margin-top: 20px;">
        <input type="hidden" name="page" value="warranties" />

        <p class="search-box">
            <label class="screen-reader-text" for="search"><?php _e('Search', 'wc_warranty') ?>:</label>
            <input type="search" id="search" name="s" value="<?php _admin_search_query(); ?>" placeholder="RMA #" />
            <?php submit_button( __('Search', 'wc_warranty'), 'button', false, false, array('id' => 'search-submit') ); ?>
        </p>
    </form>
</div>
<?php $warranty_table->display(); ?>
