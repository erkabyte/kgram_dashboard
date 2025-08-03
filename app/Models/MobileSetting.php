<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

// use App\Models\Traits\HasSlug;

class MobileSetting extends Model
{
    use HasFactory;
    // use HasSlug;
    use SoftDeletes;

    protected $fillable = ['name','slug', 'position', 'value'];
    public static function getValueBySlug($slug)
    {
        // Retrieve the setting by slug
        $setting = self::where('slug', $slug)->first();

        // If the setting exists, return its value, otherwise return null
        return $setting ? $setting->value : null;
    }

    public static function getCacheValueBySlug($slug)
    {
        if (!Cache::has('setting')) {
            $settingData = self::get(['id','name','slug', 'position', 'value'])->keyBy('slug')->toArray();
            Cache::put('setting', $settingData);
        }

        // Retrieve the setting by slug
        $setting = Cache::get('setting')[$slug];

        // If the setting exists, return its value, otherwise return null
        return $setting ? $setting['value'] : null;
    }
}
