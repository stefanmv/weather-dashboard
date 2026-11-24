<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public const MAX = 4;

    protected $fillable = [
        'slot', 'name', 'admin1', 'country', 'country_code', 'latitude', 'longitude', 'timezone',
    ];

    protected $casts = [
        'slot' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /** "Springfield, Illinois, United States" */
    public function label(): string
    {
        return collect([$this->name, $this->admin1, $this->country])
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /** First free dashboard slot (1..MAX), or null when the dashboard is full. */
    public static function nextFreeSlot(): ?int
    {
        $taken = static::pluck('slot')->all();

        foreach (range(1, self::MAX) as $slot) {
            if (! in_array($slot, $taken, true)) {
                return $slot;
            }
        }

        return null;
    }
}
