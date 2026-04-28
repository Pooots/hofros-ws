<?php

namespace Tests\Unit\Support;

use App\Support\GuestPortalLayout;
use PHPUnit\Framework\TestCase;

class GuestPortalLayoutTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testDefaultsReturnsExpectedKeys(): void
    {
        $defaults = GuestPortalLayout::defaults();

        $this->assertSame('', $defaults['businessName']);
        $this->assertContains('hero', $defaults['sectionOrder']);
        $this->assertCount(5, $defaults['sectionOrder']);
        $this->assertTrue($defaults['sectionVisibility']['hero']);
        $this->assertTrue($defaults['showReviews']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testNormalizeWithNullUsesDefaults(): void
    {
        $out = GuestPortalLayout::normalize(null);

        $this->assertSame(GuestPortalLayout::defaults()['sectionOrder'], $out['sectionOrder']);
        $this->assertCount(4, $out['amenities']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testNormalizeOverridesWithPatch(): void
    {
        $out = GuestPortalLayout::normalize(['businessName' => 'Original'], ['businessName' => 'Patched']);

        $this->assertSame('Patched', $out['businessName']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testClampTruncatesStrings(): void
    {
        $longTagline = str_repeat('A', 600);

        $out = GuestPortalLayout::clamp([
            'businessName' => 'Acme',
            'businessTagline' => $longTagline,
            'phone' => '+639170001111',
            'email' => 'hi@example.com',
            'address' => '1 St',
        ]);

        $this->assertSame('Acme', $out['businessName']);
        $this->assertSame(500, mb_strlen($out['businessTagline']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testClampFiltersAmenitiesAndMaxCount(): void
    {
        $items = array_fill(0, 60, 'Wifi');
        $items[] = '   ';
        $items[] = 'Pool';
        $items[] = 12345;

        $out = GuestPortalLayout::clamp(['amenities' => $items]);

        $this->assertLessThanOrEqual(40, count($out['amenities']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testClampNormalizesReviews(): void
    {
        $reviews = [
            ['name' => '   ', 'text' => 'Empty name dropped', 'rating' => 9],
            ['name' => 'James', 'text' => '', 'rating' => 0],
            ['name' => 'Sarah', 'text' => 'Loved it', 'rating' => 7, 'initial' => ''],
            'not-an-array',
        ];

        $out = GuestPortalLayout::clamp(['reviews' => $reviews]);

        $this->assertCount(1, $out['reviews']);
        $this->assertSame('Sarah', $out['reviews'][0]['name']);
        $this->assertSame('S', $out['reviews'][0]['initial']);
        $this->assertSame(5, $out['reviews'][0]['rating']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testClampCapsReviewsAt20(): void
    {
        $reviews = array_fill(0, 25, ['name' => 'A', 'text' => 'B', 'rating' => 5]);
        $out = GuestPortalLayout::clamp(['reviews' => $reviews]);

        $this->assertCount(20, $out['reviews']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testClampAppendsMissingSectionIds(): void
    {
        $out = GuestPortalLayout::clamp(['sectionOrder' => ['units', 'units', 'invalid']]);

        $this->assertSame(['units', 'hero', 'amenities', 'reviews', 'contact'], $out['sectionOrder']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testClampReturnsEmptyStringForNonStringValues(): void
    {
        $out = GuestPortalLayout::clamp([
            'businessName' => ['array', 'value'],
            'businessTagline' => 12345,
            'phone' => null,
            'email' => false,
            'address' => (object) ['x' => 1],
        ]);

        $this->assertSame('', $out['businessName']);
        $this->assertSame('', $out['businessTagline']);
        $this->assertSame('', $out['phone']);
        $this->assertSame('', $out['email']);
        $this->assertSame('', $out['address']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testClampHandlesSectionVisibilityOverrides(): void
    {
        $out = GuestPortalLayout::clamp([
            'sectionVisibility' => ['hero' => false, 'amenities' => 0],
            'showReviews' => false,
            'showMap' => 0,
        ]);

        $this->assertFalse($out['sectionVisibility']['hero']);
        $this->assertFalse($out['sectionVisibility']['amenities']);
        $this->assertFalse($out['showReviews']);
        $this->assertFalse($out['showMap']);
    }
}
