<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteView extends Model
{
    public $timestamps = false;

    protected $fillable = ['note_id', 'user_id', 'viewed_at'];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
