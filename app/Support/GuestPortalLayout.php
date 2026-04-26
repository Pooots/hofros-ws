<?php

namespace App\Support;

final class GuestPortalLayout
{
    public const SECTION_IDS = ['hero', 'units', 'amenities', 'reviews', 'contact'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'businessName' => '',
            'businessTagline' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'amenities' => ['Free WiFi', 'Parking', 'Air Conditioning', 'Pool'],
            'reviews' => [
                ['name' => 'Sarah L.', 'initial' => 'S', 'rating' => 5, 'text' => 'Amazing stay! Everything was perfect.'],
                ['name' => 'James M.', 'initial' => 'J', 'rating' => 5, 'text' => 'Great location and super clean.'],
                ['name' => 'Aira K.', 'initial' => 'A', 'rating' => 5, 'text' => 'We will definitely book again.'],
            ],
            'sectionOrder' => ['hero', 'units', 'amenities', 'reviews', 'contact'],
            'sectionVisibility' => [
                'hero' => true,
                'units' => true,
                'amenities' => true,
                'reviews' => true,
                'contact' => true,
            ],
            'showReviews' => true,
            'showMap' => true,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, mixed>|null  $patch
     * @return array<string, mixed>
     */
    public static function normalize(?array $stored, ?array $patch = null): array
    {
        $base = self::defaults();
        $a = is_array($stored) ? $stored : [];
        $b = is_array($patch) ? $patch : [];
        $merged = array_replace_recursive($base, array_replace_recursive($a, $b));

        return self::clamp($merged);
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    public static function clamp(array $layout): array
    {
        $out = self::defaults();

        $out['businessName'] = self::str($layout['businessName'] ?? '', 255);
        $out['businessTagline'] = self::str($layout['businessTagline'] ?? '', 500);
        $out['phone'] = self::str($layout['phone'] ?? '', 64);
        $out['email'] = self::str($layout['email'] ?? '', 255);
        $out['address'] = self::str($layout['address'] ?? '', 500);

        $amenities = [];
        if (isset($layout['amenities']) && is_array($layout['amenities'])) {
            foreach ($layout['amenities'] as $item) {
                if (count($amenities) >= 40) {
                    break;
                }
                if (is_string($item)) {
                    $t = trim($item);
                    if ($t !== '') {
                        $amenities[] = mb_substr($t, 0, 64);
                    }
                }
            }
        }
        $out['amenities'] = $amenities;

        $reviews = [];
        if (isset($layout['reviews']) && is_array($layout['reviews'])) {
            foreach ($layout['reviews'] as $r) {
                if (count($reviews) >= 20) {
                    break;
                }
                if (! is_array($r)) {
                    continue;
                }
                $name = self::str($r['name'] ?? '', 64);
                $initial = self::str($r['initial'] ?? '', 4);
                $text = self::str($r['text'] ?? '', 2000);
                $rating = (int) ($r['rating'] ?? 5);
                $rating = max(1, min(5, $rating));
                if ($name === '' || $text === '') {
                    continue;
                }
                if ($initial === '') {
                    $initial = mb_strtoupper(mb_substr($name, 0, 1));
                }
                $reviews[] = ['name' => $name, 'initial' => $initial, 'rating' => $rating, 'text' => $text];
            }
        }
        $out['reviews'] = $reviews;

        $order = [];
        if (isset($layout['sectionOrder']) && is_array($layout['sectionOrder'])) {
            foreach ($layout['sectionOrder'] as $id) {
                if (is_string($id) && in_array($id, self::SECTION_IDS, true) && ! in_array($id, $order, true)) {
                    $order[] = $id;
                }
            }
        }
        foreach (self::SECTION_IDS as $id) {
            if (! in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        $out['sectionOrder'] = $order;

        $vis = self::defaults()['sectionVisibility'];
        if (isset($layout['sectionVisibility']) && is_array($layout['sectionVisibility'])) {
            foreach (self::SECTION_IDS as $id) {
                if (array_key_exists($id, $layout['sectionVisibility'])) {
                    $vis[$id] = (bool) $layout['sectionVisibility'][$id];
                }
            }
        }
        $out['sectionVisibility'] = $vis;

        $out['showReviews'] = (bool) ($layout['showReviews'] ?? true);
        $out['showMap'] = (bool) ($layout['showMap'] ?? true);

        return $out;
    }

    private static function str(mixed $v, int $max): string
    {
        if (! is_string($v)) {
            return '';
        }

        return mb_substr(trim($v), 0, $max);
    }
}
