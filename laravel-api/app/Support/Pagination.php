<?php

namespace App\Support;

use Illuminate\Http\Request;

class Pagination
{
    public static function perPage(Request $request, int $default = 20): int
    {
        $value = filter_var($request->query('per_page', $default), FILTER_VALIDATE_INT);

        return $value === false ? $default : max(1, min(100, $value));
    }
}
