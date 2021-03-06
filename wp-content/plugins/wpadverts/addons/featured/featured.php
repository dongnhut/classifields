<?php
/**
 * Featured Ads Module
 * 
 * This module allows to feature Ads. Featured Ads are (usually) displayed
 * above normal ads and are somehow highlighted for example with different
 * background color.
 * 
 * Note. In order to use this module you need to enable Payments module first.
 *
 * @package Adverts
 * @subpackage Featured
 * @author Grzegorz Winiarski
 * @version 0.1
 */

global $adverts_namespace;

// Add 'bank_transfer'featured' to adverts_namespace, in order to store module options and default options
$adverts_namespace['featured'] = array(
    'option_name' => 'adext_featured_config',
    'default' => array(
        
    )
);

if(is_admin()) {
    add_action( 'post_submitbox_misc_actions', 'adext_featured_meta_box', 15 );
    add_filter( 'wp_insert_post_data', 'adext_featured_post_data', 10, 2 );
    add_filter( 'adverts_form_load', 'adext_featured_form_load' );
    add_filter( 'display_post_states', 'adext_featured_post_state', 1000 );
} else {
    add_filter( 'adverts_css_classes', 'adext_adverts_css_classes', 10, 2 );
}

add_filter("adverts_payments_order_create", "adext_featured_order_create");
add_action("adverts_payment_completed", "adext_featured_completed_publish");

/**
 * Adds "Is Featured" checkbox into Publish metabox.
 * 
 * This function is executed by post_submitbox_misc_actions filter.
 * 
 * @see post_submitbox_misc_actions action
 * 
 * @since 1.0.3
 * @global WP_Post $post
 * @global string $pagenow
 * @return void
 */
function adext_featured_meta_box() {
    global $post, $pagenow;
    
    // Do this for adverts only.
    if($post->post_type != 'advert') {
        return;
    }
    
    $can_publish = true;
    
    if ( $can_publish ):  ?>

    <div class="misc-pub-section">
        <!--<label>
            <?php //wp_nonce_field( "adext_featured_nonce", "_adext_is_featured_nonce", false ) ?>
            <input type="checkbox" name="_is_featured" value="1" <?php checked( $post->menu_order, "1" ) ?>/> 
            <?php //_e( "Display this listing as featured.", "adverts" ) ?>
        </label> -->
        <?php _e( "Display this listing as featured.", "adverts" ) ?>
        <label>
            <?php wp_nonce_field( "adext_featured_nonce", "_adext_is_featured_nonce", false ) ?>
            <select name="_is_featured">
              <option value="0" <?php echo $post->menu_order == '0' ? "selected" : ""; ?> >Chọn tin VIP</option>
              <option value="1" <?php echo $post->menu_order == '1' ? "selected" : ""; ?> >Vip 1</option>
              <option value="2" <?php echo $post->menu_order == '2' ? "selected" : ""; ?> >Vip 2</option>
              <option value="3" <?php echo $post->menu_order == '3' ? "selected" : ""; ?> >Vip 3</option>
              <option value="4" <?php echo $post->menu_order == '4' ? "selected" : ""; ?> >Vip 4</option>
              <option value="5" <?php echo $post->menu_order == '5' ? "selected" : ""; ?> >Vip 5</option>
            </select>
        </label>
    </div>

    <?php endif; 
}

/**
 * Sets menu_order in WP_Post when updating Advert.
 * 
 * @see wp_insert_post_data filter
 * 
 * @param array $data WP_Post data to be saved
 * @param array $postarr $_POST data submitted using form
 * @since 1.0.3
 * @return array Updated WP_Post data
 */
function adext_featured_post_data( $data, $postarr ) {
    
    global $pagenow;
    $nonce = 'adext_featured_nonce';
    $nonce_action = '_adext_is_featured_nonce';
    
    if ( !in_array($pagenow, array("post.php", "post-new.php") ) ) {
        return $data;
    }
    
    if ( !isset( $_POST[$nonce_action] ) || !wp_verify_nonce( $_POST[$nonce_action], $nonce ) ) {
        return $data;
    }
        
    if ( $data["post_type"] != 'advert' ) {
        return $data;
    }
    
    if ( defined( "DOING_AJAX" ) && DOING_AJAX ) {
        return $data;
    }
    
    $data["menu_order"] = intval( adverts_request( "_is_featured" ) );
    
    return $data;
}

/**
 * Appends classes to single advert on ads list.
 * 
 * This function is executed using adverts_css_classes filter
 * 
 * @uses adverts_css_classes filter
 * 
 * @since 1.0.3
 * @param string $classes List of classes
 * @param int $post_id WP_Post ID
 * @return string Updated list of CSS classes
 */
function adext_adverts_css_classes( $classes, $post_id ) {
    $post = get_post( $post_id );
    
    if( $post->menu_order ) {
        $classes .= " advert-is-featured";
    }
    
    return $classes;
}

/**
 * Adds is_featured field to Add/Edit Pricing form
 * 
 * @see adverts_form_load filter
 * @see wpadverts/addons/payments/includes/admin-pages.php
 * 
 * @since 1.0.3
 * @param array $form Form structure
 * @return array Updated form structure.
 */
function adext_featured_form_load( $form ) {
    if( $form["name"] != "payment" ) {
        return $form;
    }
    
    $form["field"][] = array(            
        "name" => "is_featured",
        "type" => "adverts_field_checkbox",
        "order" => 25,
        "label" => "",
        "is_required" => false,
        "validator" => array( ),
        "max_choices" => 1,
        "options" => array(
            array( "value"=> "1", "text"=> __( "Feature this ad - it will be styled differently and sticky", "adverts" ) ),
        )
    );
    
    return $form;
}

/**
 * Make Ad featured on successfull posting
 * 
 * It is executed in adverts_payment_completed action.
 * 
 * @see adverts_payment_completed action
 * 
 * @param WP_Post $payment
 * @since 1.0.3
 * @return void
 */
function adext_featured_completed_publish( WP_Post $payment ) {
    
    $object_id = get_post_meta( $payment->ID, "_adverts_object_id", true );
    
    $meta = maybe_unserialize( get_post_meta( $payment->ID, "_adverts_payment_meta", true ) );
    
    if(isset($meta["is_featured"])) {
        $is_featured = 1;
    } else {
        $is_featured = 0;
    }
    
    wp_update_post( array(
        "ID" => $object_id,
        "menu_order" => $is_featured
    ));
}

function adext_featured_order_create( $data ) {
    
    $is_featured = intval( get_post_meta( $data["listing_id"], "is_featured", true ) );
    
    if( !$is_featured ) {
        return $data;
    }
    
    $meta = maybe_unserialize( get_post_meta( $data["payment_id"], "_adverts_payment_meta", true ) );
    $meta["is_featured"] = 1;
    
    update_post_meta( $data["payment_id"], "_adverts_payment_meta", $meta);
    wp_update_post(array(
        "ID" => $data["object_id"],
        "menu_order" => 1
    ));
    
    return $data;
}

/**
 * Display 'Pending' state on Classifieds list
 * 
 * This functions shows Expired state in the wp-admin / Classifieds panel
 * 
 * @see display_post_states filter
 * 
 * @global WP_Post $post
 * @param array $states
 * @return array
 */
function adext_featured_post_state( $states ) {
    global $post;

    if( $post->post_type == 'advert' && $post->menu_order > 0 ) {

        $span = new Adverts_Html("span", array(
            "class" => "dashicons dashicons-flag",
            "title" => __( 'Featured', 'adverts' ),
            "style" => "font-size: 18px"
        ));
        $span->forceLongClosing(true);
        
        $states[] = $span->render();
    }
    
    return $states;
}