<?php
/**
 * Custom post types and admin fields: shoes (for sale) and orders (paid).
 *
 * @package NinjaShows
 */

namespace NinjaShows;

use WP_Post;

final class PostTypes
{
    public const SHOE = 'ninja_shoe';
    public const ORDER = 'ninja_order';

    // Shoe meta. The price is stored in cents. Each size is its own listing.
    public const META_PRICE = '_ninja_shoe_price_cents';
    public const META_SIZE = '_ninja_shoe_size';
    public const META_COLOUR = '_ninja_shoe_colour';
    public const META_STOCK = '_ninja_shoe_stock';

    // Order meta. No card number or security code is ever stored.
    public const META_ORDER_KEY = '_ninja_order_key';
    public const META_ORDER_REFERENCE = '_ninja_order_reference';
    public const META_ORDER_SHOE = '_ninja_order_shoe_id';
    public const META_ORDER_SIZE = '_ninja_order_size';
    public const META_ORDER_QUANTITY = '_ninja_order_quantity';
    public const META_ORDER_TOTAL = '_ninja_order_total_cents';
    public const META_ORDER_NAME = '_ninja_order_customer_name';
    public const META_ORDER_EMAIL = '_ninja_order_customer_email';
    public const META_ORDER_TRANSACTION = '_ninja_order_transaction_id';
    public const META_ORDER_CARD_LAST4 = '_ninja_order_card_last4';
    public const META_ORDER_STATUS = '_ninja_order_status';

    private const NONCE_ACTION = 'ninja_shoe_details';
    private const NONCE_FIELD = 'ninja_shoe_nonce';
    private const ERRORS_PREFIX = 'ninja_shoe_errors_';
    private const MAX_COLOUR_LENGTH = 60;

    public static function register(): void
    {
        add_action('init', [self::class, 'registerTypes']);
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post_' . self::SHOE, [self::class, 'saveShoe'], 10, 2);
        add_action('admin_notices', [self::class, 'showAdminNotices']);
    }

    public static function registerTypes(): void
    {
        register_post_type(self::SHOE, [
            'labels' => [
                'name' => 'Shoes',
                'singular_name' => 'Shoe',
                'add_new_item' => 'Add new shoe',
                'edit_item' => 'Edit shoe',
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'exclude_from_search' => true,
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-products',
        ]);

        register_post_type(self::ORDER, [
            'labels' => [
                'name' => 'Orders',
                'singular_name' => 'Order',
                'edit_item' => 'View order',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'rewrite' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
            // Orders come from the checkout only, never from the admin screen.
            'capabilities' => ['create_posts' => 'do_not_allow'],
            'menu_icon' => 'dashicons-cart',
        ]);
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box('ninja_shoe_details', 'Shoe details', [self::class, 'renderShoeDetails'], self::SHOE, 'normal', 'high');
    }

    public static function renderShoeDetails(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $priceCents = (int) get_post_meta($post->ID, self::META_PRICE, true);
        $price = $priceCents > 0 ? number_format($priceCents / 100, 2, '.', '') : '';
        $size = (string) get_post_meta($post->ID, self::META_SIZE, true);
        $colour = (string) get_post_meta($post->ID, self::META_COLOUR, true);
        $stock = (int) get_post_meta($post->ID, self::META_STOCK, true);
        ?>
        <p>
            <label for="ninja_shoe_price">Price (USD, for example 89.00)</label><br>
            <input type="text" id="ninja_shoe_price" name="ninja_shoe_price" class="regular-text" value="<?php echo esc_attr($price); ?>">
        </p>
        <p>
            <label for="ninja_shoe_size">Size (for example US 9, UK 8.5 or EU 43)</label><br>
            <input type="text" id="ninja_shoe_size" name="ninja_shoe_size" class="regular-text" maxlength="<?php echo esc_attr((string) ShoeFields::MAX_SIZE_LENGTH); ?>" value="<?php echo esc_attr($size); ?>">
        </p>
        <p>
            <label for="ninja_shoe_colour">Colour</label><br>
            <input type="text" id="ninja_shoe_colour" name="ninja_shoe_colour" class="regular-text" maxlength="<?php echo esc_attr((string) self::MAX_COLOUR_LENGTH); ?>" value="<?php echo esc_attr($colour); ?>">
        </p>
        <p>
            <label for="ninja_shoe_stock">Pairs in stock (0 means sold out)</label><br>
            <input type="number" min="0" step="1" id="ninja_shoe_stock" name="ninja_shoe_stock" value="<?php echo esc_attr((string) $stock); ?>">
        </p>
        <?php
    }

    /**
     * Saves the shoe fields. A field that does not pass its check keeps its old value,
     * and the admin sees a notice listing what was not saved.
     */
    public static function saveShoe(int $postId, WP_Post $post): void
    {
        $nonce = self::posted(self::NONCE_FIELD);
        if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $errors = [];

        $priceCents = Money::parseToCents(self::posted('ninja_shoe_price'));
        if ($priceCents === null) {
            $errors[] = 'the price must be a number such as 89.00';
        } else {
            update_post_meta($postId, self::META_PRICE, $priceCents);
        }

        $size = ShoeFields::parseSize(self::posted('ninja_shoe_size'));
        if ($size === null) {
            $errors[] = 'the size is required and may use letters, numbers, spaces, dots and slashes, such as US 9 or 8.5';
        } else {
            update_post_meta($postId, self::META_SIZE, $size);
        }

        $stock = ShoeFields::parseStock(self::posted('ninja_shoe_stock'));
        if ($stock === null) {
            $errors[] = 'the pairs in stock must be a whole number, 0 or more';
        } else {
            update_post_meta($postId, self::META_STOCK, $stock);
        }

        update_post_meta($postId, self::META_COLOUR, substr(self::posted('ninja_shoe_colour'), 0, self::MAX_COLOUR_LENGTH));

        if ($errors !== []) {
            set_transient(self::ERRORS_PREFIX . get_current_user_id(), $errors, MINUTE_IN_SECONDS);
        }
    }

    public static function showAdminNotices(): void
    {
        $key = self::ERRORS_PREFIX . get_current_user_id();
        $errors = get_transient($key);
        if (!is_array($errors)) {
            return;
        }
        delete_transient($key);

        echo '<div class="notice notice-error"><p>' . esc_html('Some shoe details were not saved: ' . implode('; ', $errors) . '.') . '</p></div>';
    }

    /** A posted text field, sanitized, or '' when it is missing. */
    private static function posted(string $key): string
    {
        if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_POST[$key]));
    }
}
