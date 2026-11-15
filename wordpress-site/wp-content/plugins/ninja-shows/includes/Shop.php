<?php
/**
 * The shop front end: the [ninja_shows] shortcode, the checkout form and the
 * order confirmation.
 *
 * One page holds the whole shop. ?ninja_show=ID opens checkout for a show, and
 * ?ninja_order=KEY shows the confirmation. The checkout form posts back to that
 * page. handleCheckout() takes the payment in template_redirect, before the page
 * is printed, then redirects, so a reload does not pay twice.
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
    public const SHORTCODE = 'ninja_shows';

    /** Hidden field that marks a checkout post. */
    private const ACTION_FIELD = 'ninja_shows_action';
    private const NONCE_ACTION = 'ninja_shows_checkout';
    private const NOTICE_PREFIX = 'ninja_shows_notice_';
    private const NOTICE_SECONDS = 300;
    private const NOTICE_TOKEN_PATTERN = '/^[A-Za-z0-9]{16}$/';
    private const ORDER_KEY_LENGTH = 24;

    /** Fields the checkout form posts. Anything else is ignored. */
    private const FORM_FIELDS = [
        'name', 'email', 'quantity', 'card_number', 'expiry_month', 'expiry_year', 'cvc',
        'ninja_show_id', 'shop_page', '_wpnonce',
    ];

    public static function register(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render']);
        add_action('template_redirect', [self::class, 'handleCheckout']);
    }

    /** Shortcode output: the show list, the checkout form, or an order confirmation. */
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

        $showId = absint(self::queryText('ninja_show'));
        if ($showId > 0) {
            return self::renderCheckout($showId, $pageId, $pageUrl);
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

        $showId = absint($input['ninja_show_id'] ?? '');
        $checkoutUrl = add_query_arg('ninja_show', $showId, $pageUrl);
        $post = self::publishedShow($showId);
        if ($post === null) {
            self::redirect($pageUrl);
        }

        $show = self::showData($post);
        $result = Checkout::validate($input, $show['tickets'], new DateTimeImmutable('now', wp_timezone()));
        if ($result['order'] === null) {
            self::redirectWithNotice($checkoutUrl, '', $result['errors'], $input);
        }
        $order = $result['order'];

        if ($show['price_cents'] <= 0) {
            self::redirectWithNotice($checkoutUrl, 'Tickets for this show are not on sale yet.', [], $input);
        }

        $totalCents = $show['price_cents'] * $order['quantity'];
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
        $saved = self::saveOrder($show, $order, $totalCents, $reference, $orderKey, $payment->transactionId);
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

    /** @return array{id: int, title: string, price_cents: int, date: string, venue: string, tickets: int} */
    public static function showData(WP_Post $post): array
    {
        return [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'price_cents' => (int) get_post_meta($post->ID, PostTypes::META_PRICE, true),
            'date' => (string) get_post_meta($post->ID, PostTypes::META_DATE, true),
            'venue' => (string) get_post_meta($post->ID, PostTypes::META_VENUE, true),
            'tickets' => (int) get_post_meta($post->ID, PostTypes::META_TICKETS, true),
        ];
    }

    private static function renderList(string $pageUrl): string
    {
        $shows = self::upcomingShows();

        $html = '<div class="ninja-shows">';
        if ($shows === []) {
            $html .= '<p>No shows are on sale yet.</p>';
        }
        foreach ($shows as $show) {
            $html .= '<article class="ninja-show">';
            $html .= '<h2>' . esc_html($show['title']) . '</h2>';
            $html .= '<p class="ninja-show-meta">' . esc_html(self::meta($show)) . '</p>';
            $html .= '<p class="ninja-show-price">' . esc_html(self::priceText($show)) . '</p>';
            $html .= '<p class="ninja-show-stock">' . esc_html(self::stockText($show)) . '</p>';
            if (self::canBuy($show)) {
                $html .= '<a class="ninja-button" href="' . esc_url(add_query_arg('ninja_show', $show['id'], $pageUrl)) . '">Buy tickets</a>';
            }
            $html .= '</article>';
        }

        return $html . '</div>';
    }

    private static function renderCheckout(int $showId, int $pageId, string $pageUrl): string
    {
        $post = self::publishedShow($showId);
        if ($post === null) {
            return '<p>This show is not available.</p>' . self::backLink($pageUrl);
        }

        $show = self::showData($post);
        $html = '<div class="ninja-checkout">';
        $html .= '<h2>' . esc_html($show['title']) . '</h2>';
        $html .= '<p class="ninja-show-meta">' . esc_html(self::meta($show)) . '</p>';
        $html .= '<p class="ninja-show-price">' . esc_html(self::priceText($show)) . ' per ticket</p>';

        if (!self::canBuy($show)) {
            return $html . '<p>' . esc_html(self::stockText($show)) . '</p>' . self::backLink($pageUrl) . '</div>';
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

        $maxTickets = min(Checkout::MAX_TICKETS_PER_ORDER, $show['tickets']);
        $quantity = (string) ($values['quantity'] ?? '1');

        $html .= '<form method="post" action="' . esc_url($pageUrl) . '" class="ninja-checkout-form" novalidate>';
        $html .= wp_nonce_field(self::NONCE_ACTION, '_wpnonce', false, false);
        $html .= '<input type="hidden" name="' . esc_attr(self::ACTION_FIELD) . '" value="checkout">';
        $html .= '<input type="hidden" name="ninja_show_id" value="' . esc_attr((string) $show['id']) . '">';
        $html .= '<input type="hidden" name="shop_page" value="' . esc_attr((string) $pageId) . '">';

        $html .= self::field('name', 'Full name', 'text', (string) ($values['name'] ?? ''), $errors, 'autocomplete="name"');
        $html .= self::field('email', 'Email', 'email', (string) ($values['email'] ?? ''), $errors, 'autocomplete="email"');
        $html .= self::field('quantity', 'Tickets (up to ' . $maxTickets . ')', 'number', $quantity, $errors, 'min="1" max="' . $maxTickets . '" step="1"');
        $html .= self::field('card_number', 'Card number', 'text', '', $errors, 'inputmode="numeric" autocomplete="cc-number"');
        $html .= self::field('expiry_month', 'Expiry month (MM)', 'text', '', $errors, 'inputmode="numeric" autocomplete="cc-exp-month" maxlength="2"');
        $html .= self::field('expiry_year', 'Expiry year (YY or YYYY)', 'text', '', $errors, 'inputmode="numeric" autocomplete="cc-exp-year" maxlength="4"', 'expiry');
        $html .= self::field('cvc', 'Security code', 'text', '', $errors, 'inputmode="numeric" autocomplete="cc-csc" maxlength="4"');

        $html .= '<p><button type="submit" class="ninja-button">Pay ' . esc_html(Money::format($show['price_cents'])) . ' per ticket</button></p>';
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
        $showId = (int) get_post_meta($orderId, PostTypes::META_ORDER_SHOW, true);
        $showTitle = $showId > 0 ? get_the_title($showId) : '';

        $rows = [
            'Reference' => (string) get_post_meta($orderId, PostTypes::META_ORDER_REFERENCE, true),
            'Show' => $showTitle !== '' ? $showTitle : 'Show',
            'Tickets' => (string) (int) get_post_meta($orderId, PostTypes::META_ORDER_QUANTITY, true),
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
            $html .= '<dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd>';
        }
        $html .= '</dl>';

        return $html . self::backLink($pageUrl) . '</div>';
    }

    /**
     * Shows that are published and have a date, soonest first. Shows without a date come last.
     *
     * @return array<int, array{id: int, title: string, price_cents: int, date: string, venue: string, tickets: int}>
     */
    private static function upcomingShows(): array
    {
        $query = new WP_Query([
            'post_type' => PostTypes::SHOW,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        $shows = array_map([self::class, 'showData'], $query->posts);
        usort($shows, static fn (array $a, array $b): int => strcmp($a['date'] !== '' ? $a['date'] : '~', $b['date'] !== '' ? $b['date'] : '~'));

        return $shows;
    }

    private static function publishedShow(int $showId): ?WP_Post
    {
        $post = $showId > 0 ? get_post($showId) : null;
        if (!$post instanceof WP_Post || $post->post_type !== PostTypes::SHOW || $post->post_status !== 'publish') {
            return null;
        }

        return $post;
    }

    /** The shop page's address, or '' when the page is not published or has no [ninja_shows] shortcode. */
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

    private static function canBuy(array $show): bool
    {
        return $show['price_cents'] > 0 && $show['tickets'] > 0;
    }

    private static function stockText(array $show): string
    {
        if ($show['price_cents'] <= 0) {
            return 'Not on sale yet';
        }
        if ($show['tickets'] < 1) {
            return 'Sold out';
        }

        return $show['tickets'] === 1 ? '1 ticket left' : $show['tickets'] . ' tickets left';
    }

    private static function priceText(array $show): string
    {
        return $show['price_cents'] > 0 ? Money::format($show['price_cents']) : 'Price not set';
    }

    /** "Sun 1 Jun 2025, 7:30 pm at Riverside Theatre", without the parts that are missing. */
    private static function meta(array $show): string
    {
        $date = ShowFields::formatForDisplay($show['date']);
        if ($date === '') {
            return $show['venue'];
        }

        return $show['venue'] !== '' ? $date . ' at ' . $show['venue'] : $date;
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
        return '<p><a href="' . esc_url($pageUrl) . '">Back to all shows</a></p>';
    }

    /**
     * Saves the paid order. Returns false if WordPress could not save it.
     *
     * @param array{id: int, title: string} $show
     * @param array<string, mixed>          $order
     */
    private static function saveOrder(array $show, array $order, int $totalCents, string $reference, string $orderKey, string $transactionId): bool
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
            PostTypes::META_ORDER_SHOW => $show['id'],
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

        // Take the tickets out of stock. Two buyers at the same moment could both get the last ticket;
        // a real store would lock the show row or use a database transaction here.
        update_post_meta($show['id'], PostTypes::META_TICKETS, max(0, $show['tickets'] - $order['quantity']));

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
