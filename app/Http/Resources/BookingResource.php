<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unit = $this->unit;

        $payload = [
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'guest_phone' => $this->guest_phone,
            'unit_uuid' => $this->unit_uuid,
            'property_uuid' => $unit?->property_uuid !== null ? $unit->property_uuid : null,
            'unit_name' => $unit?->name,
            'accommodation_name' => $unit?->property?->property_name,
            'unit_type' => $unit?->type,
            'beds' => $unit?->beds,
            'bedrooms' => $unit?->bedrooms,
            'max_guests' => $unit?->max_guests,
            'check_in' => $this->check_in?->format('Y-m-d'),
            'check_out' => $this->check_out?->format('Y-m-d'),
            'adults' => $this->adults,
            'children' => $this->children,
            'total_price' => (float) $this->total_price,
            'currency' => $this->currency,
            'source' => $this->source,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (array_key_exists('payments_sum_amount', $this->resource->getAttributes())) {
            $total = round((float) $this->total_price, 2);
            $paid = round((float) ($this->payments_sum_amount ?? 0), 2);
            $payload['paid_total'] = $paid;
            $payload['balance_due'] = round(max(0, $total - $paid), 2);
        }

        $this->appendPortalBatchFields($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function appendPortalBatchFields(array &$payload): void
    {
        $batchId = $this->portal_batch_id;
        if ($batchId === null || $batchId === '') {
            $payload['portal_batch_id'] = null;
            $payload['batch_bookings'] = null;
            $payload['batch_total_price'] = (float) $this->total_price;
            $payload['batch_unit_names'] = null;

            return;
        }

        $rows = Booking::query()
            ->where('user_uuid', $this->user_uuid)
            ->where('portal_batch_id', $batchId)
            ->with(['unit:uuid,name'])
            ->orderBy('uuid')
            ->get();

        $lines = $rows->map(static function (Booking $b): array {
            return [
                'uuid' => $b->uuid,
                'reference' => $b->reference,
                'unit_name' => $b->unit?->name,
                'total_price' => (float) $b->total_price,
            ];
        })->values()->all();

        $sum = round((float) $rows->sum(static fn (Booking $b): float => (float) $b->total_price), 2);
        $names = $rows->map(static fn (Booking $b): string => (string) ($b->unit?->name ?? 'Unit'))->values()->all();

        $payload['portal_batch_id'] = (string) $batchId;
        $payload['batch_bookings'] = $lines;
        $payload['batch_total_price'] = $sum;
        $payload['batch_unit_names'] = $names;
    }
}
