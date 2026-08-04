<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_it_normalizes_supported_pakistani_mobile_formats(string $input): void
    {
        $this->assertSame('+923099486264', PhoneNumber::normalize($input));
        $this->assertSame('(0309)9486264', PhoneNumber::display($input));
    }

    public static function validNumbers(): array
    {
        return [['03099486264'], ['0309-9486264'], ['0309 9486264'], ['3099486264'], ['+923099486264']];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_invalid_numbers(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhoneNumber::normalize($input);
    }

    public static function invalidNumbers(): array
    {
        return [['02099486264'], ['0309948626'], ['030994862640'], ['03609486264'], ['0309abc9486264'], ['phone number']];
    }
}
