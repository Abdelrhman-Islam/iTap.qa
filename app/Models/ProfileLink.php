<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileLink extends Model
{
    use HasFactory;

    // Explicitly define the table name
    protected $table = 'profile_links';

    protected $fillable = [
        'user_id',
        'link_type_id',   // Foreign Key for LinkType (Non-standard name)
        'url',  // The actual URL column
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationship: Link belongs to a User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: Link belongs to a LinkType
    public function type()
    {
        // We specify 'cat_id' because it's the foreign key used in your migration
        return $this->belongsTo(LinkType::class, 'cat_id');
    }
}