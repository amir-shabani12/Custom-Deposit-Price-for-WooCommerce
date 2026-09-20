@ -0,0 +1,262 @@
<?php
/**
 * پنل مدیریت افزونه
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'wccbp_add_admin_menu' );
function wccbp_add_admin_menu() {
    add_submenu_page(
        'woocommerce',
        'بج قیمت سفارشی',
        'بج قیمت سفارشی',
        'manage_options',
        'wccbp-settings',
        'wccbp_render_admin_page'
    );
}

add_action( 'admin_enqueue_scripts', 'wccbp_admin_assets' );
function wccbp_admin_assets( $hook ) {
    if ( 'woocommerce_page_wccbp-settings' !== $hook ) {
        return;
    }
    wp_enqueue_style( 'woocommerce_admin_styles' );
    wp_enqueue_script( 'wc-enhanced-select' );
    wp_enqueue_script(
        'wccbp-admin',
        WCCBP_URL . 'admin.js',
        array( 'jquery', 'wc-enhanced-select' ),
        WCCBP_VERSION,
        true
    );
}

function wccbp_render_admin_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings = wccbp_get_settings();

    // ذخیره
    if ( isset( $_POST['wccbp_save'] ) && check_admin_referer( 'wccbp_save_settings' ) ) {
        wccbp_handle_save( $settings );
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
        $settings = wccbp_get_settings();
    }

    // حذف قانون دسته‌بندی
    if ( isset( $_GET['delete_cat'] ) && check_admin_referer( 'wccbp_delete_cat' ) ) {
        $rule_id = sanitize_text_field( $_GET['delete_cat'] );
        if ( isset( $settings['categories'][ $rule_id ] ) ) {
            unset( $settings['categories'][ $rule_id ] );
            update_option( 'wccbp_settings', $settings );
            echo '<div class="notice notice-success is-dismissible"><p>قانون دسته‌بندی حذف شد.</p></div>';
            $settings = wccbp_get_settings();
        }
    }

    // حذف قانون محصول
    if ( isset( $_GET['delete_product'] ) && check_admin_referer( 'wccbp_delete_product' ) ) {
        $rule_id = sanitize_text_field( $_GET['delete_product'] );
        if ( isset( $settings['products'][ $rule_id ] ) ) {
            unset( $settings['products'][ $rule_id ] );
            update_option( 'wccbp_settings', $settings );
            echo '<div class="notice notice-success is-dismissible"><p>قانون محصول حذف شد.</p></div>';
            $settings = wccbp_get_settings();
        }
    }

    $product_cats = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ) );
    ?>
    <div class="wrap">
        <h1>بج قیمت سفارشی ووکامرس</h1>
        <p class="description" style="max-width:800px;">
            قیمت اصلی محصولات دست‌نخورده باقی می‌ماند. فقط یک بج روی صفحه محصول و آرشیو نمایش داده می‌شود
            و همان مبلغ هنگام افزودن به سبد خرید اعمال می‌گردد.
            در فیلد «متن بج» می‌توانید از <code>{price}</code> استفاده کنید تا مبلغ سفارشی جایگزین شود.
        </p>

        <form method="post">
            <?php wp_nonce_field( 'wccbp_save_settings' ); ?>

            <!-- قانون دسته‌بندی -->
            <h2>قانون جدید بر اساس دسته‌بندی</h2>
            <table class="form-table">
                <tr>
                    <th><label>انتخاب دسته‌بندی‌ها</label></th>
                    <td>
                        <select name="new_cat_ids[]" multiple="multiple" class="wc-enhanced-select" style="min-width:400px;width:100%;">
                            <?php foreach ( $product_cats as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                    <?php echo esc_html( $cat->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">می‌توانید چند دسته‌بندی را همزمان انتخاب کنید.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>مبلغ سفارشی</label></th>
                    <td>
                        <input type="number" step="0.01" name="new_cat_price" value="0" class="regular-text">
                        <p class="description">این مبلغ هنگام افزودن به سبد خرید جایگزین قیمت می‌شود.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>متن بج</label></th>
                    <td>
                        <input type="text" name="new_cat_badge_text" value="فقط با پرداخت {price}" class="large-text">
                        <p class="description">مثال: «فقط با پرداخت {price}» — <code>{price}</code> با مبلغ فرمت‌شده جایگزین می‌شود.</p>
                    </td>
                </tr>
            </table>

            <?php if ( ! empty( $settings['categories'] ) ) : ?>
                <h3>قوانین دسته‌بندی فعلی</h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>دسته‌بندی‌ها</th>
                            <th>مبلغ سفارشی</th>
                            <th>متن بج</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $settings['categories'] as $rule_id => $rule ) :
                            $names = array();
                            foreach ( $rule['cats'] as $cid ) {
                                $term = get_term( $cid, 'product_cat' );
                                if ( $term && ! is_wp_error( $term ) ) {
                                    $names[] = $term->name;
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html( implode( '، ', $names ) ); ?></td>
                                <td><?php echo esc_html( $rule['price'] ); ?></td>
                                <td><?php echo esc_html( $rule['badge_text'] ); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=wccbp-settings&delete_cat=' . urlencode( $rule_id ) ), 'wccbp_delete_cat' ); ?>" onclick="return confirm('حذف شود؟');">حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <hr>

            <!-- قانون محصول تکی -->
            <h2>قانون جدید برای محصولات تکی</h2>
            <p class="description">می‌توانید چند محصول را همزمان انتخاب کنید.</p>

            <table class="form-table">
                <tr>
                    <th><label>انتخاب محصولات</label></th>
                    <td>
                        <select name="new_product_ids[]" multiple="multiple" class="wc-product-search" style="min-width:400px;width:100%;"
                            data-placeholder="جستجوی محصول..."
                            data-action="woocommerce_json_search_products_and_variations"
                            data-allow_clear="true">
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>مبلغ سفارشی</label></th>
                    <td>
                        <input type="number" step="0.01" name="new_product_price" value="0" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label>متن بج</label></th>
                    <td>
                        <input type="text" name="new_product_badge_text" value="فقط با پرداخت {price}" class="large-text">
                    </td>
                </tr>
            </table>

            <?php if ( ! empty( $settings['products'] ) ) : ?>
                <h3>قوانین محصولات فعلی</h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>محصولات</th>
                            <th>مبلغ سفارشی</th>
                            <th>متن بج</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $settings['products'] as $rule_id => $rule ) :
                            $names = array();
                            foreach ( $rule['products'] as $pid ) {
                                $title = get_the_title( $pid );
                                if ( $title ) {
                                    $names[] = $title;
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html( implode( '، ', $names ) ); ?></td>
                                <td><?php echo esc_html( $rule['price'] ); ?></td>
                                <td><?php echo esc_html( $rule['badge_text'] ); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=wccbp-settings&delete_product=' . urlencode( $rule_id ) ), 'wccbp_delete_product' ); ?>" onclick="return confirm('حذف شود؟');">حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p class="submit">
                <input type="submit" name="wccbp_save" class="button-primary" value="ذخیره تنظیمات">
            </p>
        </form>
    </div>
    <?php
}

/**
 * ذخیره تنظیمات
 */
function wccbp_handle_save( $settings ) {

    // قانون دسته‌بندی
    if ( ! empty( $_POST['new_cat_ids'] ) && is_array( $_POST['new_cat_ids'] ) ) {
        $cats = array_filter( array_map( 'intval', $_POST['new_cat_ids'] ) );
        if ( ! empty( $cats ) ) {
            $rule_id = 'cat_' . uniqid();
            $settings['categories'][ $rule_id ] = array(
                'cats'       => array_values( $cats ),
                'price'      => floatval( $_POST['new_cat_price'] ?? 0 ),
                'badge_text' => sanitize_text_field( $_POST['new_cat_badge_text'] ?? '' ),
            );
        }
    }

    // قانون محصول
    if ( ! empty( $_POST['new_product_ids'] ) && is_array( $_POST['new_product_ids'] ) ) {
        $pids = array_filter( array_map( 'intval', $_POST['new_product_ids'] ) );
        if ( ! empty( $pids ) ) {
            $rule_id = 'prod_' . uniqid();
            $settings['products'][ $rule_id ] = array(
                'products'   => array_values( $pids ),
                'price'      => floatval( $_POST['new_product_price'] ?? 0 ),
                'badge_text' => sanitize_text_field( $_POST['new_product_badge_text'] ?? '' ),
            );
        }
    }

    update_option( 'wccbp_settings', $settings );
}
