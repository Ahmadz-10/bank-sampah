<?php

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_money_is_formatted_in_indonesian_style(): void
    {
        $this->assertSame('Rp 1.500', Format::money(1500));
        $this->assertSame('Rp 1.500', Format::money('1500.00'));
        $this->assertSame('Rp 0', Format::money(null));
        $this->assertSame('Rp 1.000.000', Format::money(1000000));
    }

    public function test_money_shows_cents_only_when_they_exist(): void
    {
        $this->assertSame('Rp 3.332,50', Format::money(3332.5));
        $this->assertSame('Rp 1.234.567,50', Format::money(1234567.5));
    }

    public function test_weight_drops_trailing_zeros(): void
    {
        $this->assertSame('12,5', Format::weight(12.5));
        $this->assertSame('2,5', Format::weight('2.500'));
        $this->assertSame('100', Format::weight(100));
        $this->assertSame('10', Format::weight('10.000'));
        $this->assertSame('1.000', Format::weight(1000));
        $this->assertSame('1.234,567', Format::weight(1234.567));
        $this->assertSame('0,001', Format::weight(0.001));
        $this->assertSame('0', Format::weight(0));
        $this->assertSame('0', Format::weight(null));
    }
}
