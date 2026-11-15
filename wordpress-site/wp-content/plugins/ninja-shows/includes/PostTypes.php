<?php
/**
 * Custom post types and admin fields: shows (for sale) and orders (paid).
 *
 * @package NinjaShows
 */

namespace NinjaShows;

use WP_Post;

final class PostTypes
{
    public const SHOW = 'ninja_show';
    public const ORDER = 'ninja_order';

    // Show meta. The price is stored in cents.
    public const META_PRICE = '_ninja_show_price_cents';
    public const META_DATE = '_ninja_show_date';
    public const META_VENUE = '_ninja_show_venue';
    public const META_TICKETS = '_ninja_show_tickets';

    // Order meta. No card number or security code is ever stored.
    public const META_ORDER_KEY = '_ninja_order_key';
    public const META_ORDER_REFERENCE = '_ninja_order_reference';
    public const META_ORDER_SHOW = '_ninja_order_show_id';
    public const META_ORDER_QUANTITY = '_ninja_order_quantity';
    public const META_ORDER_TOTAL = '_ninja_order_total_cents';
    public const META_ORDER_NAME = '_ninja_order_customer_name';
    public const META_ORDER_EMAIL = '_ninja_order_customer_email';
    public const META_ORDER_TRANSACTION = '_ninja_order_transaction_id';
    public const META_ORDER_CARD_LAST4 = '_ninja_order_card_last4';
    public const META_ORDER_STATUS = '_ninja_order_status';

    private const NONCE_ACTION = 'ninja_show_details';
    private const NONCE_FIELD = 'ninja_show_nonce';
    private const ERRORS_PREFIX = 'ninja_show_errors_';
    private const MAX_VENUE_LENGTH = 120;

    public static function register(): void
    {
        add_action('init', [self::class, 'registerTypes']);
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post_' . self::SHOW, [self::class, 'saveShow'], 10, 2);
        add_action('admin_notices', [self::class, 'showAdminNotices']);
    }

    public static function registerTypes(): void
    {
        register_post_type(self::SHOW, [
            'labels' => [
                'name' => 'Shows',
                'singular_name' => 'Show',
                'add_new_item' => 'Add new show',
                'edit_item' => 'Edit show',
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
            'menu_icon' => 'dashicons-tickets-alt',
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
        add_meta_box('ninja_show_details', 'Show details', [self::class, 'renderShowDetails'], self::SHOW, 'normal', 'high');
    }

    public static function renderShowDetails(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $priceCents = (int) get_post_meta($post->ID, self::META_PRICE, true);
        $price = $priceCents > 0 ? number_format($priceCents / 100, 2, '.', '') : '';
        $date = (string) get_post_meta($post->ID, self::META_DATE, true);
        $venue = (string) get_post_meta($post->ID, self::META_VENUE, true);
        $tickets = (int) get_post_meta($post->ID, self::META_TICKETS, true);
        ?>
        <p>
            <label for="ninja_show_price">Price (USD, for example 45.00)</label><br>
            <input type="text" id="ninja_show_price" name="ninja_show_price" class="regular-text" value="<?php echo esc_attr($price); ?>">
        </p>
        <p>
            <label for="ninja_show_date">Date and time</label><br>
            <input type="datetime-local" id="ninja_show_date" name="ninja_show_date" value="<?php echo esc_attr($date); ?>">
        </p>
        <p>
            <label for="ninja_show_venue">Venue</label><br>
            <input type="text" id="ninja_show_venue" name="ninja_show_venue" class="regular-text" maxlength="<?php echo esc_attr((string) self::MAX_VENUE_LENGTH); ?>" value="<?php echo esc_attr($venue); ?>">
        </p>
        <p>
            <label for="ninja_show_tickets">Tickets available (0 means sold out)</label><br>
            <input type="number" min="0" step="1" id="ninja_show_tickets" name="ninja_show_tickets" value="<?php echo esc_attr((string) $tickets); ?>">
        </p>
        <?php
    }

    /**
     * Saves the show fields. A field that does not pass its check keeps its old value,
     * and the admin sees a notice listing what was not saved.
     */
    public static function saveShow(int $postId, WP_Post $post): void
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

        $priceCents = Money::parseToCents(self::posted('ninja_show_price'));
        if ($priceCents === null) {
            $errors[] = 'the price must be a number such as 45.00';
        } else {
            update_post_meta($postId, self::META_PRICE, $priceCents);
        }

        $date = ShowFields::parseDate(self::posted('ninja_show_date'));
        if ($date === null) {
            $errors[] = 'the date and time is not valid';
        } else {
            update_post_meta($postId, self::META_DATE, $date);
        }

        $tickets = ShowFields::parseTickets(self::posted('ninja_show_tickets'));
        if ($tickets === null) {
            $errors[] = 'the tickets available must be a whole number, 0 or more';
        } else {
            update_post_meta($postId, self::META_TICKETS, $tickets);
        }

        update_post_meta($postId, self::META_VENUE, substr(self::posted('ninja_show_venue'), 0, self::MAX_VENUE_LENGTH));

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

        echo '<div class="notice notice-error"><p>' . esc_html('Some show details were not saved: ' . implode('; ', $errors) . '.') . '</p></div>';
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
