<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    public function next(string $prefix, int $padding = 4): string
    {
        DB::table('document_sequences')->insertOrIgnore([
            'prefix' => $prefix,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $number = DB::transaction(function () use ($prefix): int {
            $sequence = DB::table('document_sequences')
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            $number = (int) $sequence->next_number;

            DB::table('document_sequences')
                ->where('prefix', $prefix)
                ->update([
                    'next_number' => $number + 1,
                    'updated_at' => now(),
                ]);

            return $number;
        });

        return $prefix.str_pad(
            (string) $number,
            $padding,
            '0',
            STR_PAD_LEFT
        );
    }
}
