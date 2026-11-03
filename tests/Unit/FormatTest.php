<?php

namespace Tests\Unit;

use App\Support\Format;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormatTest extends TestCase
{
    private const S = "\u{00A0}";

    public static function moneyCases(): array
    {
        return [
            'zéro' => [0, '0,00'.self::S.'$'],
            'cents seuls' => [5, '0,05'.self::S.'$'],
            'maquette tableau de bord' => [128450, '1'.self::S.'284,50'.self::S.'$'],
            'million' => [123456789, '1'.self::S.'234'.self::S.'567,89'.self::S.'$'],
            'négatif (note de crédit)' => [-2748, '-27,48'.self::S.'$'],
        ];
    }

    #[DataProvider('moneyCases')]
    public function test_money_is_formatted_fr_ca(int $cents, string $expected): void
    {
        $this->assertSame($expected, Format::money($cents));
    }

    public static function parseCases(): array
    {
        return [
            ['1 284,50', 128450],
            ['1'.self::S.'284,50 $', 128450],
            ['12.5', 1250],
            ['3', 300],
            ['0,07', 7],
            ['-4,99', -499],
        ];
    }

    #[DataProvider('parseCases')]
    public function test_money_input_is_parsed_to_cents_without_float(string $input, int $expected): void
    {
        $this->assertSame($expected, Format::parseMoney($input));
    }

    public function test_money_input_rejects_more_than_two_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Format::parseMoney('3,999');
    }

    public function test_money_input_rejects_text(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Format::parseMoney('trois dollars');
    }

    public function test_quantity_drops_useless_zeros(): void
    {
        $this->assertSame('1,5', Format::quantity('1.500'));
        $this->assertSame('25', Format::quantity('25.000'));
        $this->assertSame('0,25', Format::quantity('0.250'));
        $this->assertSame('1'.self::S.'200', Format::quantity('1200.000'));
        $this->assertSame('4', Format::quantity(4));
    }

    public function test_dates_are_written_in_french(): void
    {
        $this->assertSame('2 novembre 2026', Format::date(Carbon::create(2026, 11, 2)));
        $this->assertSame('1er novembre 2026', Format::date('2026-11-01'));
        $this->assertSame('lundi 2 novembre 2026', Format::longDate('2026-11-02'));
        $this->assertSame('6 nov.', Format::shortDate('2026-11-06'));
        $this->assertSame('15 août', Format::shortDate('2026-08-15'));
    }

    public function test_time_uses_quebec_style(): void
    {
        $this->assertSame('10 h', Format::time('2026-11-02 10:00'));
        $this->assertSame('14 h 05', Format::time('2026-11-02 14:05'));
    }
}
