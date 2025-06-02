<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TextTemplate extends Model
{
    protected $fillable = [
        'user_id', 'type', 'subject', 'body',
    ];

    protected $casts = [
        'subject' => 'array',
        'body' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getSubjectForLocale($locale = null)
    {
        $locale = $locale ?: app()->getLocale();
        $subject = $this->subject[$locale] ?? null;
        if (!$subject && $locale !== 'de') $subject = $this->subject['de'] ?? null;
        if (!$subject && $locale !== 'en') $subject = $this->subject['en'] ?? null;
        if (!$subject && is_array($this->subject)) $subject = reset($this->subject);
        return $subject;
    }

    public function getBodyForLocale($locale = null)
    {
        $locale = $locale ?: app()->getLocale();
        $body = $this->body[$locale] ?? null;
        if (!$body && $locale !== 'de') $body = $this->body['de'] ?? null;
        if (!$body && $locale !== 'en') $body = $this->body['en'] ?? null;
        if (!$body && is_array($this->body)) $body = reset($this->body);
        return $body;
    }
}
