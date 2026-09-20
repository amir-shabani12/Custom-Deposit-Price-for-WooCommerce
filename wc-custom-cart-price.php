<?php
/**
 * Plugin Name: بج قیمت سفارشی ووکامرس
 * Description: نمایش بج قیمت سفارشی روی محصولات و اعمال آن هنگام افزودن به سبد خرید، بدون تغییر قیمت اصلی.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wc-custom-badge-price
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WCCBP_VERSION', '1.0.0' );
define( 'WCCBP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCCBP_URL', plugin_dir_url( __FILE__ ) );

/**
 * بررسی فعال بودن ووکامرس
 */
add_action( 'plugins_loaded', 'wccbp_check_woocommerce' );
function wccbp_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wccbp_woocommerce_missing_notice' );
    }
}

function wccbp_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>';
    echo 'افزونه «بج قیمت سفارشی ووکامرس» نیازمند نصب و فعال بودن ووکامرس است.';
    echo '</p></div>';
}

/**
 * دریافت تنظیمات
 */
function wccbp_get_settings() {
    $defaults = array(
        'categories' => array(),
        'products'   => array(),
    );
    $settings = get_option( 'wccbp_settings', array() );
    return wp_parse_args( $settings, $defaults );
}

/**
 * یافتن قانون اعمال‌شده برای یک محصول
 * اولویت: قانون محصول تکی > قانون دسته‌بندی
 */
function wccbp_find_rule_for_product( $product_id ) {
    $settings = wccbp_get_settings();

    $parent_id = $product_id;
    $product   = wc_get_product( $product_id );
    if ( $product && $product->get_parent_id() ) {
        $parent_id = $product->get_parent_id();
    }

    // اولویت ۱: قانون محصول تکی
    foreach ( $settings['products'] as $rule ) {
        if ( in_array( $product_id, $rule['products'], true ) || in_array( $parent_id, $rule['products'], true ) ) {
            return $rule;
        }
    }

    // اولویت ۲: قانون دسته‌بندی
    $product_cats = wp_get_post_terms( $parent_id, 'product_cat', array( 'fields' => 'ids' ) );
    if ( ! empty( $product_cats ) ) {
        foreach ( $settings['categories'] as $rule ) {
            if ( array_intersect( $product_cats, $rule['cats'] ) ) {
                return $rule;
            }
        }
    }

    return null;
}

/**
 * ============================================================
 * بخش ۱: نمایش بج روی صفحه محصول و آرشیو
 * ============================================================
 * بج بلافاصله بعد از قیمت اصلی نمایش داده می‌شود.
 * قیمت اصلی دست‌نخورده باقی می‌ماند.
 */
add_filter( 'woocommerce_get_price_html', 'wccbp_append_badge_to_price', 100, 2 );
function wccbp_append_badge_to_price( $price_html, $product ) {

    if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_product() ) ) {
        return $price_html;
    }

    $rule = wccbp_find_rule_for_product( $product->get_id() );
    if ( ! $rule ) {
        return $price_html;
    }

    $badge_text = wccbp_render_badge_text( $rule );
    if ( empty( $badge_text ) ) {
        return $price_html;
    }

    $badge_html = '<span class="wccbp-badge" style="display:inline-block;margin-right:8px;padding:4px 10px;background:#e53935;color:#fff;border-radius:4px;font-size:13px;line-height:1.6;vertical-align:middle;">'
        . esc_html( $badge_text )
        . '</span>';

    return $price_html . $badge_html;
}

/**
 * ساخت متن بج بر اساس تنظیمات قانون
 */
function wccbp_render_badge_text( $rule ) {

    $template = isset( $rule['badge_text'] ) ? $rule['badge_text'] : '';
    $value    = isset( $rule['price'] ) ? floatval( $rule['price'] ) : 0;

    if ( empty( $template ) ) {
        return '';
    }

    // جایگزینی {price} با قیمت فرمت‌شده
    $formatted_price = wccbp_format_price( $value );

    return str_replace( '{price}', $formatted_price, $template );
}

/**
 * فرمت کردن قیمت با واحد پول ووکامرس
 */
function wccbp_format_price( $price ) {
    if ( function_exists( 'wc_price' ) ) {
        return wp_strip_all_tags( wc_price( $price ) );
    }
    return number_format_i18n( $price );
}

/**
 * ============================================================
 * بخش ۲: اعمال قیمت سفارشی هنگام افزودن به سبد خرید
 * ============================================================
 */
add_action( 'woocommerce_before_calculate_totals', 'wccbp_apply_custom_price', 20, 1 );
function wccbp_apply_custom_price( $cart ) {

    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item ) {

        $product    = $cart_item['data'];
        $product_id = $product->get_id();

        $rule = wccbp_find_rule_for_product( $product_id );
        if ( ! $rule ) {
            continue;
        }

        $new_price = floatval( $rule['price'] );
        if ( $new_price > 0 ) {
            $product->set_price( $new_price );
        }
    }
}

require_once WCCBP_PATH . 'admin-page.php';
