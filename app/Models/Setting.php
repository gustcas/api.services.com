<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $row = static::find($key);
        return $row ? $row->value : $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public static function allAsObject(): object
    {
        $rows = static::all(['key', 'value']);
        $result = [];
        foreach ($rows as $row) {
            $val = $row->value;
            // Cast booleans stored as "0"/"1"
            if ($val === '0') $val = false;
            elseif ($val === '1') $val = true;
            // Cast numerics
            elseif (is_numeric($val) && !str_starts_with($val, '0')) {
                $val = str_contains($val, '.') ? (float)$val : (int)$val;
            }
            $result[$row->key] = $val;
        }
        return (object) $result;
    }
}
