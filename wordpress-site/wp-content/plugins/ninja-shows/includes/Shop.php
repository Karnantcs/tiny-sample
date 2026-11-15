<?php
/**
 * The shoe shop front end: the [ninja_shoes] shortcode, the checkout form and the
 * order confirmation.
 *
 * One page holds the whole shop. ?ninja_shoe=ID opens checkout for a shoe listing
 * (one size), and ?ninja_order=KEY shows the confirmation. The checkout form posts
 * back to that page. handleCheckout() takes the payment in template_redirect, before
 * the page is printed, then redirects, so a reload does not pay twice.
 *
 * @package NinjaShows
 */

namespace NinjaShows;

use DateTimeImmutable;
use NinjaShows\Payments\MockGateway;
use NinjaShows\Payments\PaymentGateway;
use NinjaShows\Payments\PaymentRequest;
use WP_Post;
use WP_Query;

final class Shop
{
    public const SHORTCODE = 'ninja_shoes';

    /** Query argument that opens checkout for one shoe listing. */
    private const SHOE_QUERY_ARG = 'ninja_shoe';

    /** Hidden field that marks a checkout post. */
    private const ACTION_FIELD = 'ninja_shoes_action';
    private const NONCE_ACTION = 'ninja_shoes_checkout';
    private const NOTICE_PREFIX = 'ninja_shoes_notice_';
    private const NOTICE_SECONDS = 300;
    private const NOTICE_TOKEN_PATTERN = '/^[A-Za-z0-9]{16}$/';
    private const ORDER_KEY_LENGTH = 24;

    /** Fields the checkout form posts. Anything else is ignored. */
    private const FORM_FIELDS = [
        'name', 'email', 'quantity', 'card_number', 'expiry_month', 'expiry_year', 'cvc',
        'ninja_shoe_id', 'shop_page', '_wpnonce',
    ];

    public static function register(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render']);
        add_action('template_redirect', [self::class, 'handleCheckout']);
    }

    /** Shortcode output: the shoe list, the checkout form, or an order confirmation. */
    public static function render(): string
    {
        $pageId = (int) get_the_ID();
        $pageUrl = $pageId > 0 ? (string) get_permalink($pageId) : '';
        if ($pageUrl === '') {
            return '';
        }

        $orderKey = self::queryText('ninja_order');
        if ($orderKey !== '') {
            return self::renderConfirmation($orderKey, $pageUrl);
        }

        $shoeId = absint(self::queryText(self::SHOE_QUERY_ARG));
        if ($shoeId > 0) {
            return self::renderCheckout($shoeId, $pageId, $pageUrl);
        }

        return self::renderList($pageUrl);
    }

    /**
     * Takes a posted checkout: validates it, charges the gateway, records the order,
     * and redirects. Runs before the page is printed, so it can redirect.
     */
    public static function handleCheckout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST[self::ACTION_FIELD])) {
            return;
        }

        $input = self::postedFields();

        $pageUrl = self::shopPageUrl(absint($input['shop_page'] ?? ''));
        if ($pageUrl === '') {
            wp_die(esc_html__('This shop page is not available.', 'ninja-shows'), '', ['response' => 404]);
        }

        if (!wp_verify_nonce($input['_wpnonce'] ?? '', self::NONCE_ACTION)) {
            wp_die(esc_html__('This form has expired. Go back, reload the page and try again.', 'ninja-shows'), '', ['response' => 403]);
        }

        $shoeId = absint($input['ninja_shoe_id'] ?? '');
        $checkoutUrl = add_query_arg(self::SHOE_QUERY_ARG, $shoeId, $pageUrl);
        $post = self::publishedShoe($shoeId);
        if ($post === null) {
            self::redirect($pageUrl);
        }

        $shoe = self::shoeData($post);
        $result = Checkout::validate($input, $shoe['stock'], new DateTimeImmutable('now', wp_timezone()));
        if ($result['order'] === null) {
            self::redirectWithNotice($checkoutUrl, '', $result['errors'], $input);
        }
        $order = $result['order'];

        if ($shoe['price_cents'] <= 0) {
            self::redirectWithNotice($checkoutUrl, 'This shoe is not on sale yet.', [], $input);
        }

        $totalCents = $shoe['price_cents'] * $order['quantity'];
        $reference = 'NS-' . strtoupper(wp_generate_password(8, false));
        $payment = self::gateway()->charge(new PaymentRequest(
            $totalCents,
            Money::CURRENCY,
            $order['card_number'],
            $order['expiry_month'],
            $order['expiry_year'],
            $order['cvc'],
            $reference
        ));

        if (!$payment->approved) {
            self::redirectWithNotice($checkoutUrl, 'Payment was declined. ' . $payment->message, [], $input);
        }

        $orderKey = wp_generate_password(self::ORDER_KEY_LENGTH, false);
        $saved = self::saveOrder($shoe, $order, $totalCents, $reference, $orderKey, $payment->transactionId);
        if (!$saved) {
            // The charge went through but the order could not be saved. A real gateway
            // would need a refund or void here. The mock has nothing to refund.
            self::redirectWithNotice($checkoutUrl, 'Your payment went through, but we could not save the order. Please contact the shop with reference ' . $reference . '.', [], $input);
        }

        self::redirect(add_query_arg('ninja_order', $orderKey, $pageUrl));
    }

    /** The gateway to charge. A plugin can replace it with the filter ninja_shows_payment_gateway. */
    private static function gateway(): PaymentGateway
    {
        $gateway = apply_filters('ninja_shows_payment_gateway', new MockGateway());

        return $gateway instanceof PaymentGateway ? $gateway : new MockGateway();
    }

    /** @return array{id: int, title: string, price_cents: int, size: string, colour: string, stock: int} */
    public static function shoeData(WP_Post $post): array
    {
        return [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'price_cents' => (int) get_post_meta($post->ID, PostTypes::META_PRICE, true),
            'size' => (string) get_post_meta($post->ID, PostTypes::META_SIZE, true),
            'colour' => (string) get_post_meta($post->ID, PostTypes::META_COLOUR, true),
            'stock' => (int) get_post_meta($post->ID, PostTypes::META_STOCK, true),
        ];
    }

    private static function renderList(string $pageUrl): string
    {
        $shoes = self::shoeList();

        $html = '<div class="ninja-shoes">';
        if ($shoes === []) {
            $html .= '<p>No shoes are on sale yet.</p>';
        }
        foreach ($shoes as $shoe) {
            $html .= '<article class="ninja-shoe">';
            $html .= '<h2>' . esc_html($shoe['title']) . '</h2>';
            $html .= '<p class="ninja-shoe-meta">' . esc_html(self::meta($shoe)) . '</p>';
            $html .= '<p class="ninja-shoe-price">' . esc_html(self::priceText($shoe)) . '</p>';
            $html .= '<p class="ninja-shoe-stock">' . esc_html(self::stockText($shoe)) . '</p>';
            if (self::canBuy($shoe)) {
                $html .= '<a class="ninja-button" href="' . esc_url(add_query_arg(self::SHOE_QUERY_ARG, $shoe['id'], $pageUrl)) . '">Buy now</a>';
            }
            $html .= '</article>';
        }

        return $html . '</div>';
    }

    private static function renderCheckout(int $shoeId, int $pageId, string $pageUrl): string
    {
        $post = self::publishedShoe($shoeId);
        if ($post === null) {
            return '<p>This shoe is not available.</p>' . self::backLink($pageUrl);
        }

        $shoe = self::shoeData($post);
        $html = '<div class="ninja-checkout">';
        $html .= '<h2>' . esc_html($shoe['title']) . '</h2>';
        $html .= '<p class="ninja-shoe-meta">' . esc_html(self::meta($shoe)) . '</p>';
        $html .= '<p class="ninja-shoe-price">' . esc_html(self::priceText($shoe)) . ' per pair</p>';

        if (!self::canBuy($shoe)) {
            return $html . '<p>' . esc_html(self::stockText($shoe)) . '</p>' . self::backLink($pageUrl) . '</div>';
        }

        $notice = self::takeNotice();
        $errors = is_array($notice['errors'] ?? null) ? $notice['errors'] : [];
        $values = is_array($notice['values'] ?? null) ? $notice['values'] : [];
        if (!empty($notice['message'])) {
            $html .= '<div class="ninja-error"><p>' . esc_html((string) $notice['message']) . '</p></div>';
        }
        if ($errors !== []) {
            $html .= '<div class="ninja-error"><p>Please fix the highlighted fields.</p></div>';
        }

        $html .= '<p class="ninja-mock-note">Mock payment: no real money is charged. Test card 4242 4242 4242 4242 is approved. '
            . 'Card 4000 0000 0000 0002 is declined. Use any future expiry date and any 3-digit security code.</p>';

        $maxPairs = min(Checkout::MAX_TICKETS_PER_ORDER, $shoe['stock']);
        $quantity = (string) ($values['quantity'] ?? '1');

        $html .= '<form method="post" action="' . esc_url($pageUrl) . '" class="ninja-checkout-form" novalidate>';
        $html .= wp_nonce_field(self::NONCE_ACTION, '_wpnonce', false, false);
        $html .= '<input type="hidden" name="' . esc_attr(self::ACTION_FIELD) . '" value="checkout">';
        $html .= '<input type="hidden" name="ninja_shoe_id" value="' . esc_attr((string) $shoe['id']) . '">';
        $html .= '<input type="hidden" name="shop_page" value="' . esc_attr((string) $pageId) . '">';

        $html .= self::field('name', 'Full name', 'text', (string) ($values['name'] ?? ''), $errors, 'autocomplete="name"');
        $html .= self::field('email', 'Email', 'email', (string) ($values['email'] ?? ''), $errors, 'autocomplete="email"');
        $html .= self::field('quantity', 'Pairs (up to ' . $maxPairs . ')', 'number', $quantity, $errors, 'min="1" max="' . $maxPairs . '" step="1"');
        $html .= self::field('card_number', 'Card number', 'text', '', $errors, 'inputmode="numeric" autocomplete="cc-number"');
        $html .= self::field('expiry_month', 'Expiry month (MM)', 'text', '', $errors, 'inputmode="numeric" autocomplete="cc-exp-month" maxlength="2"');
        $html .= self::field('expiry_year', 'Expiry year (YY or YYYY)', 'text', '', $errors, 'inputmode="numeric" autocomplete="cc-exp-year" maxlength="4"', 'expiry');
        $html .= self::field('cvc', 'Security code', 'text', '', $errors, 'inputmode="numeric" autocomplete="cc-csc" maxlength="4"');

        $html .= '<p><button type="submit" class="ninja-button">Pay ' . esc_html(Money::format($shoe['price_cents'])) . ' per pair</button></p>';
        $html .= '</form>';

        return $html . self::backLink($pageUrl) . '</div>';
    }

    private static function renderConfirmation(string $orderKey, string $pageUrl): string
    {
        $ids = get_posts([
            'post_type' => PostTypes::ORDER,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => PostTypes::META_ORDER_KEY,
            'meta_value' => $orderKey,
        ]);
        if ($ids === []) {
            return '<p>We could not find that order.</p>' . self::backLink($pageUrl);
        }

        $orderId = (int) $ids[0];
        $shoeId = (int) get_post_meta($orderId, PostTypes::META_ORDER_SHOE, true);
        $shoeTitle = $shoeId > 0 ? get_the_title($shoeId) : '';

        $rows = [
            'Reference' => (string) get_post_meta($orderId, PostTypes::META_ORDER_REFERENCE, true),
            'Shoe' => $shoeTitle !== '' ? $shoeTitle : 'Shoe',
            'Size' => (string) get_post_meta($orderId, PostTypes::META_ORDER_SIZE, true),
            'Pairs' => (string) (int) get_post_meta($orderId, PostTypes::META_ORDER_QUANTITY, true),
            'Total' => Money::format((int) get_post_meta($orderId, PostTypes::META_ORDER_TOTAL, true)),
            'Card' => 'ending ' . (string) get_post_meta($orderId, PostTypes::META_ORDER_CARD_LAST4, true),
            'Mock transaction' => (string) get_post_meta($orderId, PostTypes::META_ORDER_TRANSACTION, true),
        ];

        $name = (string) get_post_meta($orderId, PostTypes::META_ORDER_NAME, true);
        $html = '<div class="ninja-confirmation">';
        $html .= '<h2>Thank you, ' . esc_html($name) . '!</h2>';
        $html .= '<p>Your order is confirmed. This is a mock payment, so no money moved.</p>';
        $html .= '<dl>';
        foreach ($rows as $label => $value) {
            if ($value === '') {
                continue;
            }
            $html .= '<dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd>';
        }
        $html .= '</dl>';

        return $html . self::backLink($pageUrl) . '</div>';
    }

    /**
     * Published shoe listings, in the order they were added. Each size is its own listing.
     *
     * @return array<int, array{id: int, title: string, price_cents: int, size: string, colour: string, stock: int}>
     */
    private static function shoeList(): array
    {
        $query = new WP_Query([
            'post_type' => PostTypes::SHOE,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        return array_map([self::class, 'shoeData'], $query->posts);
    }

    private static function publishedShoe(int $shoeId): ?WP_Post
    {
        $post = $shoeId > 0 ? get_post($shoeId) : null;
        if (!$post instanceof WP_Post || $post->post_type !== PostTypes::SHOE || $post->post_status !== 'publish') {
            return null;
        }

        return $post;
    }

    /** The shop page's address, or '' when the page is not published or has no [ninja_shoes] shortcode. */
    private static function shopPageUrl(int $pageId): string
    {
        $page = $pageId > 0 ? get_post($pageId) : null;
        if (!$page instanceof WP_Post || $page->post_type !== 'page' || $page->post_status !== 'publish') {
            return '';
        }
        if (!has_shortcode($page->post_content, self::SHORTCODE)) {
            return '';
        }

        return (string) get_permalink($page);
    }

    private static function canBuy(array $shoe): bool
    {
        return $shoe['price_cents'] > 0 && $shoe['stock'] > 0;
    }

    private static function stockText(array $shoe): string
    {
        if ($shoe['price_cents'] <= 0) {
            return 'Not on sale yet';
        }
        if ($shoe['stock'] < 1) {
            return 'Sold out';
        }

        return $shoe['stock'] === 1 ? '1 pair left' : $shoe['stock'] . ' pairs left';
    }

    private static function priceText(array $shoe): string
    {
        return $shoe['price_cents'] > 0 ? Money::format($shoe['price_cents']) : 'Price not set';
    }

    /** "Size US 9, Black". The parts that are missing are left out. */
    private static function meta(array $shoe): string
    {
        $parts = [];
        if ($shoe['size'] !== '') {
            $parts[] = 'Size ' . $shoe['size'];
        }
        if ($shoe['colour'] !== '') {
            $parts[] = $shoe['colour'];
        }

        return implode(', ', $parts);
    }

    /** One labelled form field, with its error below it when there is one. */
    private static function field(string $name, string $label, string $type, string $value, array $errors, string $attributes, ?string $errorKey = null): string
    {
        $errorKey = $errorKey ?? $name;
        $html = '<p><label>' . esc_html($label) . '<br>';
        $html .= '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . $attributes . '></label>';
        if (isset($errors[$errorKey])) {
            $html .= '<br><span class="ninja-field-error">' . esc_html($errors[$errorKey]) . '</span>';
        }

        return $html . '</p>';
    }

    private static function backLink(string $pageUrl): string
    {
        return '<p><a href="' . esc_url($pageUrl) . '">Back to all shoes</a></p>';
    }

    /**
     * Saves the paid order and takes the pairs out of stock. Returns false if WordPress could not save the order.
     *
     * @param array{id: int, title: string, size: string, stock: int} $shoe
     * @param array<string, mixed>                                   $order
     */
    private static function saveOrder(array $shoe, array $order, int $totalCents, string $reference, string $orderKey, string $transactionId): bool
    {
        $orderId = wp_insert_post([
            'post_type' => PostTypes::ORDER,
            'post_status' => 'publish',
            'post_title' => $reference,
        ], true);
        if (is_wp_error($orderId)) {
            return false;
        }

        $meta = [
            PostTypes::META_ORDER_KEY => $orderKey,
            PostTypes::META_ORDER_REFERENCE => $reference,
            PostTypes::META_ORDER_SHOE => $shoe['id'],
            PostTypes::META_ORDER_SIZE => $shoe['size'],
            PostTypes::META_ORDER_QUANTITY => $order['quantity'],
            PostTypes::META_ORDER_TOTAL => $totalCents,
            PostTypes::META_ORDER_NAME => $order['name'],
            PostTypes::META_ORDER_EMAIL => $order['email'],
            PostTypes::META_ORDER_TRANSACTION => $transactionId,
            PostTypes::META_ORDER_CARD_LAST4 => CardValidator::lastFour($order['card_number']),
            PostTypes::META_ORDER_STATUS => 'paid',
        ];
        foreach ($meta as $key => $value) {
            update_post_meta($orderId, $key, $value);
        }

        // Take the pairs out of stock. Two buyers at the same moment could both get the last pair;
        // a real store would lock the stock row or use a database transaction here.
        update_post_meta($shoe['id'], PostTypes::META_STOCK, max(0, $shoe['stock'] - $order['quantity']));

        return true;
    }

    /**
     * Stores the message and the field errors for the next page view, and redirects there.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed>  $input
     */
    private static function redirectWithNotice(string $url, string $message, array $errors, array $input): void
    {
        $token = wp_generate_password(16, false);
        set_transient(self::NOTICE_PREFIX . $token, [
            'message' => $message,
            'errors' => $errors,
            // Card details are never kept, only the fields that are safe to show again.
            'values' => [
                'name' => (string) ($input['name'] ?? ''),
                'email' => (string) ($input['email'] ?? ''),
                'quantity' => (string) ($input['quantity'] ?? ''),
            ],
        ], self::NOTICE_SECONDS);

        self::redirect(add_query_arg('ninja_notice', $token, $url));
    }

    /**
     * Reads and removes the notice from the last checkout attempt, if there is one.
     *
     * @return array<string, mixed>|null
     */
    private static function takeNotice(): ?array
    {
        $token = self::queryText('ninja_notice');
        if (!preg_match(self::NOTICE_TOKEN_PATTERN, $token)) {
            return null;
        }

        $notice = get_transient(self::NOTICE_PREFIX . $token);
        delete_transient(self::NOTICE_PREFIX . $token);

        return is_array($notice) ? $notice : null;
    }

    /** The posted checkout fields, sanitized. Only the known fields are read. */
    private static function postedFields(): array
    {
        $fields = [];
        foreach (self::FORM_FIELDS as $key) {
            if (isset($_POST[$key]) && is_string($_POST[$key])) {
                $fields[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
            }
        }

        return $fields;
    }

    /** A query-string value, sanitized, or '' when it is missing. */
    private static function queryText(string $key): string
    {
        if (!isset($_GET[$key]) || !is_string($_GET[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_GET[$key]));
    }

    /** Sends the browser to $url and stops the request. */
    private static function redirect(string $url): void
    {
        wp_safe_redirect($url, 303);
        exit;
    }
}
