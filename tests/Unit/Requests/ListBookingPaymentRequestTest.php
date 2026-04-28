<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\BookingPayment\ListBookingPaymentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ListBookingPaymentRequestTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testAuthorizeReturnsTrue(): void
    {
        $req = new ListBookingPaymentRequest();
        $this->assertTrue($req->authorize());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRulesValidateProvidedPayload(): void
    {
        $req = new ListBookingPaymentRequest();

        $validator = Validator::make([
            'sort' => 'created_at',
            'per_page' => 25,
            'page' => 2,
        ], $req->rules());

        $this->assertFalse($validator->fails());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRulesRejectInvalidPagination(): void
    {
        $req = new ListBookingPaymentRequest();

        $validator = Validator::make([
            'per_page' => 9999,
            'page' => 0,
        ], $req->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
        $this->assertArrayHasKey('page', $validator->errors()->toArray());
    }
}
