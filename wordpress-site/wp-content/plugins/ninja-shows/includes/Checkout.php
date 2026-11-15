<?php
/**
 * Validation of the checkout form. It checks the customer, ticket count and card
 * fields, and returns the clean order data for the payment gateway.
 *
 * Kept free of WordPress functions so it can be unit tested without WordPress.
 *
 * @package NinjaShows
 */

namespace NinjaShows;

use DateTimeImmutable;

final class Checkout
{
    /** Most tickets one order can buy. */
    public const MAX_TICKETS_PER_ORDER = 10;

    /** Longest accepted name, in bytes. */
    private const MAX_NAME_LENGTH = 100;

    /** Longest accepted email address, in bytes (RFC 5321). */
    private const MAX_EMAIL_LENGTH = 254;

    /**
     * Validate the submitted fields.
     *
     * @param array<string, mixed> $input Form values: name, email, quantity, card_number, expiry_month, expiry_year, cvc.
     * @param int                  $ticketsAvailable Tickets left for the show.
     * @return array{errors: array<string, string>, order: array<string, mixed>|null}
     *         errors is keyed by field. order is set only when there are no errors.
     */
    public static function validate(array $input, int $ticketsAvailable, DateTimeImmutable $now): array
    {
        $errors = [];

        $name = self::text($input, 'name');
        if ($name === '' || strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'] = 'Enter your name (up to 100 characters).';
        }

        $email = self::text($input, 'email');
        if ($email === '' || strlen($email) > self::MAX_EMAIL_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        $maxTickets = min(self::MAX_TICKETS_PER_ORDER, max(0, $ticketsAvailable));
        $quantityText = self::text($input, 'quantity');
        $quantity = preg_match('/^\d{1,3}$/', $quantityText) ? (int) $quantityText : 0;
        if ($maxTickets < 1) {
            $errors['quantity'] = 'This show is sold out.';
        } elseif ($quantity < 1 || $quantity > $maxTickets) {
            $errors['quantity'] = sprintf('Choose between 1 and %d tickets.', $maxTickets);
        }

        $number = CardValidator::normalizeNumber(self::text($input, 'card_number'));
        if (!CardValidator::isValidNumber($number)) {
            $errors['card_number'] = 'Enter a valid card number.';
        }

        $expiry = self::parseExpiry(self::text($input, 'expiry_month'), self::text($input, 'expiry_year'), $now);
        if ($expiry === null) {
            $errors['expiry'] = 'Enter an expiry date (month and year) that is not in the past.';
        }

        $cvc = self::text($input, 'cvc');
        if (!CardValidator::isValidCvc($cvc)) {
            $errors['cvc'] = 'Enter the 3 or 4 digit security code.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'order' => null];
        }

        return [
            'errors' => [],
            'order' => [
                'name' => $name,
                'email' => $email,
                'quantity' => $quantity,
                'card_number' => $number,
                'expiry_month' => $expiry['month'],
                'expiry_year' => $expiry['year'],
                'cvc' => $cvc,
            ],
        ];
    }

    /**
     * Read an expiry month (1 or 2 digits) and year (2 or 4 digits).
     *
     * Returns the month and four-digit year, or null when the date is invalid or has passed.
     *
     * @return array{month: int, year: int}|null
     */
    public static function parseExpiry(string $month, string $year, DateTimeImmutable $now): ?array
    {
        $month = trim($month);
        $year = trim($year);
        if (!preg_match('/^\d{1,2}$/', $month) || !preg_match('/^(\d{2}|\d{4})$/', $year)) {
            return null;
        }

        $monthNumber = (int) $month;
        $yearNumber = strlen($year) === 2 ? 2000 + (int) $year : (int) $year;

        if (!CardValidator::isUnexpired($monthNumber, $yearNumber, $now)) {
            return null;
        }

        return ['month' => $monthNumber, 'year' => $yearNumber];
    }

    /** A trimmed string field, or '' when it is missing or not a string. */
    private static function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
