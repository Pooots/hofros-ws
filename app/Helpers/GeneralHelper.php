<?php

namespace App\Helpers;

class GeneralHelper
{
    /**
     * Strip out keys not in the allowed list and drop nulls,
     * so model updates only persist the fields callers actually changed.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    public static function unsetUnknownAndNullFields(array $payload, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $value = $payload[$key];
            if ($value === null) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Same as {@see self::unsetUnknownAndNullFields()} but keeps explicit null values.
     * Useful when nulling out columns is part of the update.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    public static function unsetUnknownFields(array $payload, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $out[$key] = $payload[$key];
        }

        return $out;
    }
}
