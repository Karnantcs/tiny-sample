<?php
/**
 * Runs when the plugin is activated: creates the Shoes page and, on an empty
 * site, a few sample shoes so the shop has something to sell.
 *
 * @package NinjaShows
 */

namespace NinjaShows;

final class Setup
{
    public const SHOP_SLUG = 'shoes';

    public static function activate(): void
    {
        self::ensureShopPage();
        self::ensureSampleShoes();
    }

    private static function ensureShopPage(): void
    {
        if (get_page_by_path(self::SHOP_SLUG) !== null) {
            return;
        }

        wp_insert_post([
            'post_type' => 'page',
            'post_title' => 'Shoes',
            'post_name' => self::SHOP_SLUG,
            'post_status' => 'publish',
            'post_content' => '[' . Shop::SHORTCODE . ']',
        ]);
    }

    /** Adds sample shoes only when there are no shoes yet. Their details are fake. */
    private static function ensureSampleShoes(): void
    {
        $existing = get_posts([
            'post_type' => PostTypes::SHOE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        if ($existing !== []) {
            return;
        }

        $samples = [
            // [model, size, colour, price in cents, pairs in stock]
            ['Trail Runner', 'US 9', 'Black', 8900, 12],
            ['Trail Runner', 'US 10', 'Black', 8900, 6],
            ['Classic Leather Oxford', 'US 10', 'Brown', 12000, 5],
            ['Canvas Low Top', 'US 8', 'White', 4500, 0],
        ];

        foreach ($samples as [$model, $size, $colour, $priceCents, $stock]) {
            $shoeId = wp_insert_post([
                'post_type' => PostTypes::SHOE,
                'post_status' => 'publish',
                'post_title' => $model,
                'post_content' => 'Sample shoe added when the plugin was activated. Edit or delete it under Shoes.',
            ], true);
            if (is_wp_error($shoeId)) {
                continue;
            }

            update_post_meta($shoeId, PostTypes::META_PRICE, $priceCents);
            update_post_meta($shoeId, PostTypes::META_SIZE, $size);
            update_post_meta($shoeId, PostTypes::META_COLOUR, $colour);
            update_post_meta($shoeId, PostTypes::META_STOCK, $stock);
        }
    }
}
