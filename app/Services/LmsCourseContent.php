<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LmsCourseContent
{
    /** @return array{materials: list<array<string, mixed>>} */
    public function normalize(mixed $content): array
    {
        if (! is_array($content) || array_diff(array_keys($content), ['materials']) !== []) {
            $this->invalid('content', 'Struktur konten tidak valid.');
        }
        if (is_array($content['materials'] ?? null)) {
            foreach ($content['materials'] as &$material) {
                if (is_array($material) && ! array_key_exists('media', $material)) {
                    $material['media'] = [];
                }
                if (is_array($material['sections'] ?? null)) {
                    foreach ($material['sections'] as &$section) {
                        if (is_array($section) && ! array_key_exists('media', $section)) {
                            $section['media'] = [];
                        }
                    }
                    unset($section);
                }
            }
            unset($material);
        }
        $rules = [
            'materials' => ['present', 'array', 'list', 'max:100'],
            'materials.*' => ['required', 'array:id,title,body,media,questions,sections'],
            'materials.*.sections' => ['present', 'array', 'list', 'max:50'],
            'materials.*.sections.*' => ['required', 'array:id,title,body,media,questions'],
        ];
        foreach (['materials.*', 'materials.*.sections.*'] as $path) {
            $rules += [
                "{$path}.id" => ['required', 'uuid'],
                "{$path}.title" => ['required', 'string', 'max:180'],
                "{$path}.body" => ['nullable', 'string', 'max:30000'],
                "{$path}.media" => ['present', 'array', 'list', 'max:20'],
                "{$path}.media.*" => ['required', 'array:id,type,asset_id,url,caption'],
                "{$path}.media.*.id" => ['required', 'uuid'],
                "{$path}.media.*.type" => ['required', Rule::in(['image', 'pdf', 'youtube'])],
                "{$path}.media.*.asset_id" => ['nullable', 'uuid'],
                "{$path}.media.*.url" => ['nullable', 'string', 'max:500'],
                "{$path}.media.*.caption" => ['nullable', 'string', 'max:255'],
                "{$path}.questions" => ['present', 'array', 'list', 'max:100'],
                "{$path}.questions.*" => ['required', 'array:id,type,prompt,options'],
                "{$path}.questions.*.id" => ['required', 'uuid'],
                "{$path}.questions.*.type" => ['required', Rule::in(['multiple_choice', 'essay'])],
                "{$path}.questions.*.prompt" => ['required', 'string', 'max:10000'],
                "{$path}.questions.*.options" => ['sometimes', 'array', 'list', 'max:6'],
                "{$path}.questions.*.options.*" => ['required', 'array:id,text,is_correct'],
                "{$path}.questions.*.options.*.id" => ['required', 'uuid'],
                "{$path}.questions.*.options.*.text" => ['required', 'string', 'max:500'],
                "{$path}.questions.*.options.*.is_correct" => ['required', 'boolean'],
            ];
        }
        $validated = Validator::make($content, $rules)->validate();
        $ids = [];
        $totalQuestions = 0;
        $totalMedia = 0;
        $totalSections = 0;
        $register = function (string $id) use (&$ids): void {
            if (isset($ids[$id])) {
                $this->invalid('content', 'Identitas konten tidak boleh berulang.');
            }
            $ids[$id] = true;
        };
        $normalizePage = function (array $page) use (&$totalQuestions, &$totalMedia, $register): array {
            $register($page['id']);
            $page['body'] = $this->sanitize($page['body'] ?? '');
            foreach ($page['questions'] as &$question) {
                $register($question['id']);
                if (trim($question['prompt']) === '') {
                    $this->invalid('content', 'Pertanyaan wajib diisi.');
                }
                $options = $question['options'] ?? [];
                $correctOptions = 0;
                foreach ($options as $option) {
                    $correctOptions += (int) ($option['is_correct'] ?? false);
                }
                if ($question['type'] === 'multiple_choice' && (count($options) < 2 || count($options) > 6)) {
                    $this->invalid('content', 'Soal pilihan ganda harus memiliki 2 sampai 6 opsi.');
                }
                if ($question['type'] === 'multiple_choice' && $correctOptions !== 1) {
                    $this->invalid('content', 'Pilih tepat satu jawaban benar untuk soal pilihan ganda.');
                }
                if ($question['type'] === 'essay' && $options !== []) {
                    $this->invalid('content', 'Soal uraian tidak menerima opsi jawaban.');
                }
                foreach ($options as $option) {
                    $register($option['id']);
                    if (trim($option['text']) === '') {
                        $this->invalid('content', 'Teks opsi jawaban wajib diisi.');
                    }
                }
            }
            unset($question);
            foreach ($page['media'] as &$media) {
                $register($media['id']);
                if ($media['type'] === 'youtube') {
                    $url = parse_url($media['url'] ?? '');
                    $host = strtolower($url['host'] ?? '');
                    $videoId = '';
                    if (($url['scheme'] ?? '') === 'https' && in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true) && ! isset($url['user'], $url['pass'])) {
                        parse_str($url['query'] ?? '', $query);
                        $path = $url['path'] ?? '';
                        $videoId = $host === 'youtu.be' ? substr($path, 1) : ($path === '/watch' ? ($query['v'] ?? '') : preg_replace('#^/(embed|shorts)/#', '', $path));
                    }
                    if (! is_string($videoId) || ! preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId)) {
                        $this->invalid('content', 'Gunakan tautan HTTPS video YouTube yang valid.');
                    }
                    $media['url'] = 'https://www.youtube.com/watch?v='.$videoId;
                    unset($media['asset_id']);
                } elseif (empty($media['asset_id'])) {
                    $this->invalid('content', 'Pilih file gambar atau PDF terlebih dahulu.');
                } else {
                    unset($media['url']);
                }
            }
            unset($media);
            $totalQuestions += count($page['questions']);
            $totalMedia += count($page['media']);

            return $page;
        };
        $materials = [];
        foreach ($validated['materials'] as $material) {
            $sections = $material['sections'];
            $totalSections += count($sections);
            $material = $normalizePage($material);
            $material['sections'] = array_map($normalizePage, $sections);
            $materials[] = $material;
        }
        if ($totalQuestions > 100 || $totalMedia > 100 || $totalSections > 200) {
            $this->invalid('content', 'Batas course: 100 soal, 100 media, dan 200 Bagian.');
        }

        return ['materials' => $materials];
    }

    public function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);
            if ($body === null) {
                return '';
            }
            $render = function (DOMNode $node) use (&$render): string {
                if ($node->nodeType === XML_TEXT_NODE) {
                    return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                if (! $node instanceof DOMElement) {
                    return '';
                }
                $tag = strtolower($node->tagName);
                if ($tag === 'div' && $node->parentNode?->nodeName !== 'body') {
                    $tag = 'p';
                }
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'], true)) {
                    return '';
                }
                $children = '';
                foreach ($node->childNodes as $child) {
                    $children .= $render($child);
                }
                if ($tag === 'a') {
                    $href = trim($node->getAttribute('href'));
                    if ($href === '' || ! filter_var($href, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($href, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                        return $children;
                    }

                    return '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.$children.'</a>';
                }
                if (! in_array($tag, ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'pre', 'code', 'hr'], true)) {
                    return $children;
                }
                if ($tag === 'br' || $tag === 'hr') {
                    return '<'.$tag.'>';
                }
                if ($tag === 'ol' && preg_match('/^[0-9]{1,6}$/D', $node->getAttribute('start'))) {
                    return '<ol start="'.(int) $node->getAttribute('start').'">'.$children.'</ol>';
                }

                return '<'.$tag.'>'.$children.'</'.$tag.'>';
            };

            return $render($body);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @param array<string, mixed> $content
     * @return list<array<string, mixed>>
     */
    public function media(array $content): array
    {
        $media = [];
        foreach ($content['materials'] ?? [] as $material) {
            $media = [...$media, ...($material['media'] ?? [])];
            foreach ($material['sections'] ?? [] as $section) {
                $media = [...$media, ...($section['media'] ?? [])];
            }
        }

        return array_values($media);
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
