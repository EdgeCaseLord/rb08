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
        // Handle Tiptap JSON structure
        if (is_array($body) && isset($body['type']) && isset($body['content'])) {
            return $this->tiptapToHtml($body);
        }
        // Fallback: implode for legacy arrays
        if (is_array($body)) {
            $body = implode("\n", $body);
        }
        return $body;
    }

    // Convert Tiptap JSON to HTML (very basic, extend as needed)
    protected function tiptapToHtml($node)
    {
        if (!is_array($node)) return '';
        $html = '';
        switch ($node['type'] ?? null) {
            case 'doc':
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
                break;
            case 'paragraph':
                $html .= '<p>';
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
                $html .= '</p>';
                break;
            case 'text':
                $html .= htmlspecialchars($node['text'] ?? '');
                break;
            case 'table':
                $html .= '<table border="1">';
                foreach ($node['content'] ?? [] as $row) {
                    $html .= $this->tiptapToHtml($row);
                }
                $html .= '</table>';
                break;
            case 'tableRow':
                $html .= '<tr>';
                foreach ($node['content'] ?? [] as $cell) {
                    $html .= $this->tiptapToHtml($cell);
                }
                $html .= '</tr>';
                break;
            case 'tableCell':
            case 'tableHeader':
                $tag = $node['type'] === 'tableHeader' ? 'th' : 'td';
                $html .= '<' . $tag . '>';
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
                $html .= '</' . $tag . '>';
                break;
            default:
                // Unknown node type, try rendering children
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
        }
        return $html;
    }
}
