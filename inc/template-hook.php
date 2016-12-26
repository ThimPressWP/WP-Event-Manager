<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

// template hook
add_action( 'tp_event_after_loop_event_item', 'event_auth_register' );
add_action( 'tp_event_after_single_event', 'event_auth_register' );
if ( !function_exists( 'event_auth_register' ) ) {

    function event_auth_register() {
        tpe_auth_addon_get_template( 'button-register-event.php' );
    }

}

// filter shortcode
add_filter( 'the_content', 'event_auth_content_filter', 1 );
if ( !function_exists( 'event_auth_content_filter' ) ) {

    function event_auth_content_filter( $content ) {
        global $post;
        if ( ( $login_page_id = tpe_auth_get_page_id( 'login' ) ) && is_page( $login_page_id ) ) {
            $content = do_shortcode( '[event_auth_login]' );
        } else if ( ( $register_page_id = tpe_auth_get_page_id( 'register' ) ) && is_page( $register_page_id ) ) {
            $content = do_shortcode( '[event_auth_register]' );
        } else if ( ( $forgot_page_id = tpe_auth_get_page_id( 'forgot_pass' ) ) && is_page( $forgot_page_id ) ) {
            $content = do_shortcode( '[event_auth_forgot_password]' );
        } else if ( ( $reset_page_id = tpe_auth_get_page_id( 'reset_password' ) ) && is_page( $reset_page_id ) ) {
            $content = do_shortcode( '[event_auth_reset_password]' );
        } else if ( ( $account_page_id = tpe_auth_get_page_id( 'account' ) ) && is_page( $account_page_id ) ) {
            $content = do_shortcode( '[event_auth_my_account]' );
        }

        return $content;
    }

}

add_action( 'event_auth_create_new_booking', 'event_auth_cancel_booking', 10, 1 );
add_action( 'event_auth_updated_status', 'event_auth_cancel_booking', 10, 1 );
if ( !function_exists( 'event_auth_cancel_booking' ) ) {

    function event_auth_cancel_booking( $booking_id ) {
        $post_status = get_post_status( $booking_id );
        if ( $post_status === 'ea-pending' ) {
            wp_clear_scheduled_hook( 'event_auth_cancel_payment_booking', array( $booking_id ) );
            $time = event_get_option( 'cancel_payment', 12 ) * HOUR_IN_SECONDS;
            wp_schedule_single_event( time() + $time, 'event_auth_cancel_payment_booking', array( $booking_id ) );
        }
    }

}

// cancel payment order
add_action( 'event_auth_cancel_payment_booking', 'event_auth_cancel_payment_booking' );
if ( !function_exists( 'event_auth_cancel_payment_booking' ) ) {

    function event_auth_cancel_payment_booking( $booking_id ) {
        $post_status = get_post_status( $booking_id );

        if ( $post_status === 'ea-pending' ) {
            wp_update_post( array(
                'ID' => $booking_id,
                'post_status' => 'ea-cancelled'
            ) );
        }
    }

}