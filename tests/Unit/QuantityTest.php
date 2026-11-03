<?php

namespace Tests\Unit;

use App\Support\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    public function test_parses_french_input_to_thousandths(): void
    {
        $this->assertSame(1500, Quantity::toMilli('1,5'));
        $this->assertSame(1500, Quantity::toMilli('1.5'));
        $this->assertSame(350, Quantity::toMilli('0,35'));
        $this->assertSame(1_200_000, Quantity::toMilli('1 200'));
        $this->assertSame(2000, Quantity::toMilli(2));
        $this->assertSame(25_000, Quantity::toMilli('25.000'));
    }

    public function test_rejects_more_than_three_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Quantity::toMilli('0,0005');
    }

    public function test_formats_for_decimal_column(): void
    {
        $this->assertSame('1.500', Quantity::fromMilli(1500));
        $this->assertSame('0.001', Quantity::fromMilli(1));
        $this->assertSame('-2.250', Quantity::fromMilli(-2250));
    }

    public function test_division_rounds_half_up(): void
    {
        $this->assertSame(3, Quantity::divRound(5, 2));   // 2,5 → 3
        $this->assertSame(2, Quantity::divRound(9, 4));   // 2,25 → 2
        $this->assertSame(350, Quantity::divRound(175_00 * 1000, 50_000)); // 175 $ / 50 boules
    }
}
