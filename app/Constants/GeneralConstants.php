<?php

namespace App\Constants;

class GeneralConstants
{
    public const GENERAL_STATUSES = [
        'ACTIVE' => 'active',
        'INACTIVE' => 'inactive',
    ];

    public const BOOKING_STATUSES = [
        'PENDING' => 'pending',
        'ACCEPTED' => 'accepted',
        'ASSIGNED' => 'assigned',
        'CHECKED_IN' => 'checked_in',
        'CHECKED_OUT' => 'checked_out',
        'CANCELLED' => 'cancelled',
    ];

    public const BOOKING_SOURCES = [
        'MANUAL' => 'manual',
        'DIRECT_PORTAL' => 'direct_portal',
    ];

    public const PAYMENT_TYPES = [
        'GCASH' => 'gcash',
        'CASH' => 'cash',
        'BANK_TRANSFER' => 'bank_transfer',
        'CARD' => 'card',
        'OTHER' => 'other',
    ];

    public const PAYMENT_KINDS = [
        'PAYMENT' => 'payment',
        'REFUND' => 'refund',
    ];

    public const PROMO_CODE_TYPES = [
        'PERCENTAGE' => 'percentage',
        'FIXED' => 'fixed',
    ];

    public const UNIT_DISCOUNT_TYPES = [
        'EARLY_BIRD' => 'early_bird',
        'LONG_STAY' => 'long_stay',
        'LAST_MINUTE' => 'last_minute',
        'WEEKEND_DISCOUNT' => 'weekend_discount',
        'DATE_RANGE' => 'date_range',
    ];

    public const TEAM_MEMBER_ROLES = [
        'ADMIN' => 'admin',
        'MANAGER' => 'manager',
        'STAFF' => 'staff',
    ];

    public const RATE_INTERVAL_DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public const WEEK_SCHEDULE_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
}
