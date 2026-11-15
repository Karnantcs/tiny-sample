<?php
/**
 * Runs when the plugin is activated: creates the Shows page and, on an empty
 * site, a few sample shows so the shop has something to sell.
 *
 * @package NinjaShows
 */

namespace NinjaShows;

use DateTimeImmutable;

final class Setup
{
    public const SHOP_SLUG = 'shows';

    public static function activate(): void
    {
        self::ensureShopPage();
        self::ensureSampleShows();
    }

    private static function ensureShopPage(): void
    {
        if (get_page_by_path(self::SHOP_SLUG) !== null) {
            return;
        }

        wp_insert_post([
            'post_type' => 'page',
            'post_title' => 'Shows',
            'post_name' => self::SHOP_SLUG,
            'post_status' => 'publish',
            'post_content' => '[' . Shop::SHORTCODE . ']',
        ]);
    }

    /** Adds sample shows only when there are no shows yet. Their details are fake. */
    private static function ensureSampleShows(): void
    {
        $existing = get_posts([
            'post_type' => PostTypes::SHOW,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        if ($existing !== []) {
            return;
        }

        $now = new DateTimeImmutable('now', wp_timezone());
        $samples = [
            // [title, days from now, time, venue, price in cents, tickets]
            ['The Midnight Orchestra', 14, '19:30', 'Riverside Theatre', 4500, 40],
            ['Comedy Night Live', 21, '20:00', 'The Corner Club', 2500, 60],
            ['Spring Ballet Gala', 30, '18:00', 'Grand Opera House', 8000, 0],
        ];

        foreach ($samples as [$title, $days, $time, $venue, $priceCents, $tickets]) {
            $showId = wp_insert_post([
                'post_type' => PostTypes::SHOW,
                'post_status' => 'publish',
                'post_title' => $title,
                'post_content' => 'Sample show added when the plugin was activated. Edit or delete it under Shows.',
            ], true);
            if (is_wp_error($showId)) {
                continue;
            }

            $date = $now->modify('+' . $days . ' days')->format('Y-m-d') . 'T' . $time;
            update_post_meta($showId, PostTypes::META_PRICE, $priceCents);
            update_post_meta($showId, PostTypes::META_DATE, $date);
            update_post_meta($showId, PostTypes::META_VENUE, $venue);
            update_post_meta($showId, PostTypes::META_TICKETS, $tickets);
        }
    }
}
