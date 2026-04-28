<?php

namespace Tests\Unit\Helpers;

use App\Helpers\GeneralHelper;
use PHPUnit\Framework\TestCase;

class GeneralHelperTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnsetUnknownAndNullFieldsStripsNullsAndUnknowns(): void
    {
        $out = GeneralHelper::unsetUnknownAndNullFields(
            ['name' => 'A', 'price' => null, 'extra' => 'x'],
            ['name', 'price', 'currency']
        );

        $this->assertSame(['name' => 'A'], $out);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnsetUnknownAndNullFieldsReturnsEmptyForEmptyInputs(): void
    {
        $this->assertSame([], GeneralHelper::unsetUnknownAndNullFields([], ['name']));
        $this->assertSame([], GeneralHelper::unsetUnknownAndNullFields(['name' => 'A'], []));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnsetUnknownFieldsKeepsNullsButDropsUnknowns(): void
    {
        $out = GeneralHelper::unsetUnknownFields(
            ['name' => 'A', 'price' => null, 'extra' => 'x'],
            ['name', 'price', 'currency']
        );

        $this->assertSame(['name' => 'A', 'price' => null], $out);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnsetUnknownFieldsDropsUnknownKeys(): void
    {
        $out = GeneralHelper::unsetUnknownFields(['a' => 1, 'b' => 2], ['a']);

        $this->assertSame(['a' => 1], $out);
    }
}
