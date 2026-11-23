<?php

namespace App\Services\Travel;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TravelSchedule
{
    public function normalize(array $input): array
    {
        $departure = $this->moment($input, 'depart');
        $return = $this->moment($input, 'return');
        if (substr($return, 0, 10) < substr($departure, 0, 10)
            || (strlen($return) > 10 && strlen($departure) > 10 && $return < $departure)) {
            throw ValidationException::withMessages(['return_date' => 'The date/hour return must not be until departure.']);
        }

        return ['depart_at' => $departure, 'return_at' => $return, 'depart_time_tba' => strlen($departure) === 10,
            'return_time_tba' => strlen($return) === 10, ...$this->duration(['depart_at' => $departure, 'return_at' => $return])];
    }

    public function duration(array $input): array
    {
        $dates = [];
        foreach (['depart', 'return'] as $prefix) {
            $value = $input[$prefix.'_at'] ?? '';
            $dates[$prefix.'_date'] = array_key_exists($prefix.'_date', $input)
                ? $input[$prefix.'_date'] : (is_string($value) ? substr($value, 0, 10) : $value);
        }
        Validator::make($dates, ['depart_date' => ['required', 'date_format:Y-m-d'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:depart_date']], [
                '*.required' => 'Select the date of departure and the return date to calculate duration.',
                '*.date_format' => 'Valid travel date content to calculate duration.',
                'return_date.after_or_equal' => 'The return date must not be before the departure date.',
            ])->validate();
        $nights = (int) CarbonImmutable::parse($dates['depart_date'])->diffInDays(CarbonImmutable::parse($dates['return_date']));

        return ['days' => $nights + 1, 'nights' => $nights];
    }

    public function formData(array $data): array
    {
        $fields = [];
        foreach (['depart', 'return'] as $prefix) {
            $value = $data[$prefix.'_at'] ?? '';
            $fields[$prefix.'_date'] = substr($value, 0, 10);
            $fields[$prefix.'_time'] = strlen($value) > 10 ? substr($value, 11, 5) : '';
            $fields[$prefix.'_time_tba'] = strlen($value) <= 10;
        }

        return $fields;
    }

    private function moment(array $input, string $prefix): string
    {
        if (! array_key_exists($prefix.'_date', $input)) {
            $value = $input[$prefix.'_at'] ?? '';
            Validator::make([$prefix.'_at' => $value], [$prefix.'_at' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d']])->validate();

            return $value;
        }
        $tba = $input[$prefix.'_time_tba'] ?? true;
        Validator::make($input + [$prefix.'_time_tba' => true], [
            $prefix.'_date' => ['required', 'date_format:Y-m-d'],
            $prefix.'_time_tba' => ['required', 'boolean'],
            $prefix.'_time' => $tba ? ['nullable'] : ['required', 'date_format:H:i'],
        ])->validate();

        return $input[$prefix.'_date'].($tba ? '' : 'T'.$input[$prefix.'_time']);
    }
}
