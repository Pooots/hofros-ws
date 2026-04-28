<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\BookingPortalException;
use App\Exceptions\BookingValidationException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoBookingFoundException;
use App\Exceptions\NoBookingPaymentFoundException;
use App\Exceptions\NoPromoCodeFoundException;
use App\Exceptions\NoPropertyFoundException;
use App\Exceptions\NoTeamMemberFoundException;
use App\Exceptions\NotFoundException;
use App\Exceptions\NoUnitDateBlockFoundException;
use App\Exceptions\NoUnitDiscountFoundException;
use App\Exceptions\NoUnitFoundException;
use App\Exceptions\NoUnitRateIntervalFoundException;
use App\Exceptions\PortalNotPublishedException;
use App\Exceptions\TeamMemberOperationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public static function exceptionProvider(): array
    {
        return [
            'invalid_credentials' => [InvalidCredentialsException::class, 422, 'invalid_credentials'],
            'booking_validation' => [BookingValidationException::class, 422, 'booking_validation_error'],
            'no_booking' => [NoBookingFoundException::class, 404, 'no_booking_found'],
            'no_booking_payment' => [NoBookingPaymentFoundException::class, 404, 'no_booking_payment_found'],
            'no_property' => [NoPropertyFoundException::class, 404, 'no_property_found'],
            'no_team_member' => [NoTeamMemberFoundException::class, 404, 'no_team_member_found'],
            'no_unit' => [NoUnitFoundException::class, 404, 'no_unit_found'],
            'no_unit_date_block' => [NoUnitDateBlockFoundException::class, 404, 'no_unit_date_block_found'],
            'no_unit_discount' => [NoUnitDiscountFoundException::class, 404, 'no_unit_discount_found'],
            'no_unit_rate_interval' => [NoUnitRateIntervalFoundException::class, 404, 'no_unit_rate_interval_found'],
            'no_promo_code' => [NoPromoCodeFoundException::class, 404, 'no_promo_code_found'],
            'invalid_date_range' => [InvalidDateRangeException::class, 422, 'invalid_date_range'],
            'portal_not_published' => [PortalNotPublishedException::class, 404, 'portal_not_published'],
            'booking_portal' => [BookingPortalException::class, 422, 'booking_portal_error'],
            'team_member_operation' => [TeamMemberOperationException::class, 422, 'team_member_operation_invalid'],
            'not_found' => [NotFoundException::class, 404, 'not_found'],
        ];
    }

    #[DataProvider('exceptionProvider')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function testApiExceptionHasExpectedStatusAndCode(string $class, int $expectedStatus, string $expectedCode): void
    {
        if (! class_exists($class)) {
            $this->markTestSkipped("$class does not exist");
        }
        $exception = new $class();

        $this->assertSame($expectedStatus, $exception->getHttpStatusCode());
        $response = $exception->render();

        $this->assertSame($expectedStatus, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame(false, $payload['success']);
        $this->assertSame($expectedCode, $payload['error_code']);
        $this->assertNotEmpty($payload['message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testApiExceptionUsesCustomMessageWhenProvided(): void
    {
        $custom = new InvalidCredentialsException('Wrong password');
        $this->assertSame('Wrong password', $custom->getMessage());
    }
}
