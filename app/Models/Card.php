<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Card extends Model
{
    use HasFactory, SoftDeletes;

    // 1. إعدادات الـ UUID
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'company_id',
        'nfc_tag_id',
        'full_name',
        'profile_image',
        'type',           // PERSONAL, EMPLOYEE, COMPANY
        'status',         // ACTIVE, FROZEN...
        'is_primary',
        'contacts_count',
        'bio',
        'company_name',
        'position',
        'theme_id',
        'color_scheme',
        'social_links',   // JSON
        'settings'        // JSON
    ];

    // Convert JSON tp Automatic Arrays & Revirse
    protected $casts = [
        'is_primary' => 'boolean',
        'social_links' => 'array', 
        'settings' => 'array',
        'contacts_count' => 'integer',
    ];

    // 3. Auto Generate UUID 
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
    // Image Handling
    protected $appends = ['profile_image_url'];

        public function getProfileImageUrlAttribute()
        {
            $image = $this->profile_image;

            if (!$image) {
                return null; 
            }

            if (Str::startsWith($image, ['http://', 'https://'])) {
                return $image;
            }

            return asset('storage/' . $image);
        }
    
    // --- Relations ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // الكارت مربوط بقطعة هاردوير واحدة (NFC Tag)
    public function nfcTag()
    {
        return $this->belongsTo(NfcInventory::class, 'nfc_tag_id', 'tag_id');
    }
    
}