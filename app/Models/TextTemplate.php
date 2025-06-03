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

    public function getBodyForLocale($locale = null, $vars = [])
    {
        $locale = $locale ?: app()->getLocale();
        $body = $this->body[$locale] ?? null;
        if (!$body && $locale !== 'de') $body = $this->body['de'] ?? null;
        if (!$body && $locale !== 'en') $body = $this->body['en'] ?? null;
        if (!$body && is_array($this->body)) $body = reset($this->body);
        // Handle Tiptap JSON structure
        if (is_array($body) && isset($body['type']) && isset($body['content'])) {
            $body = $this->tiptapToHtml($body);
        }
        // Fallback: implode for legacy arrays
        if (is_array($body)) {
            $body = implode("\n", $body);
        }
        // Variable replacement
        if (!empty($vars) && is_string($body)) {
            $body = preg_replace_callback('/\{\$?([a-zA-Z0-9_]+)(->\w+)*\}/', function ($matches) use ($vars) {
                $expr = ltrim(trim($matches[0], '{}'), '$');
                $parts = explode('->', $expr);
                $val = $vars[$parts[0]] ?? null;
                for ($i = 1; $i < count($parts); $i++) {
                    if (is_object($val) && isset($val->{$parts[$i]})) {
                        $val = $val->{$parts[$i]};
                    } elseif (is_array($val) && isset($val[$parts[$i]])) {
                        $val = $val[$parts[$i]];
                    } else {
                        return $matches[0];
                    }
                }
                return $val;
            }, $body);
        }
        return $body;
    }

    // Convert Tiptap JSON to HTML (extended for more formatting)
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
                $text = htmlspecialchars($node['text'] ?? '');
                if (!empty($node['marks'])) {
                    foreach ($node['marks'] as $mark) {
                        switch ($mark['type']) {
                            case 'bold':
                                $text = '<strong>' . $text . '</strong>';
                                break;
                            case 'italic':
                                $text = '<em>' . $text . '</em>';
                                break;
                            case 'underline':
                                $text = '<u>' . $text . '</u>';
                                break;
                            case 'link':
                                $href = htmlspecialchars($mark['attrs']['href'] ?? '#');
                                $text = '<a href="' . $href . '" target="_blank">' . $text . '</a>';
                                break;
                        }
                    }
                }
                $html .= $text;
                break;
            case 'hardBreak':
            case 'hard_break':
                $html .= '<br />';
                break;
            case 'heading':
                $level = isset($node['attrs']['level']) ? intval($node['attrs']['level']) : 1;
                $level = max(1, min(6, $level));
                $html .= '<h' . $level . '>';
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
                $html .= '</h' . $level . '>';
                break;
            case 'bulletList':
            case 'bullet_list':
                $html .= '<ul>';
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
                $html .= '</ul>';
                break;
            case 'orderedList':
            case 'ordered_list':
                $html .= '<ol>';
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
                $html .= '</ol>';
                break;
            case 'listItem':
            case 'list_item':
                $html .= '<li>';
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
                $html .= '</li>';
                break;
            case 'blockquote':
                $html .= '<blockquote>';
                foreach ($node['content'] ?? [] as $child) {
                    $html .= $this->tiptapToHtml($child);
                }
                $html .= '</blockquote>';
                break;
            case 'table':
                $html .= '<table class="masstabelle">';
                foreach ($node['content'] ?? [] as $row) {
                    $html .= $this->tiptapToHtml($row);
                }
                $html .= '</table>';
                break;
            case 'tableRow':
            case 'table_row':
                $html .= '<tr>';
                foreach ($node['content'] ?? [] as $cell) {
                    $html .= $this->tiptapToHtml($cell);
                }
                $html .= '</tr>';
                break;
            case 'tableCell':
            case 'tableHeader':
            case 'table_cell':
            case 'table_header':
                $tag = in_array($node['type'], ['tableHeader', 'table_header']) ? 'th' : 'td';
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
