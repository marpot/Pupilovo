<?php

namespace Pupilovo\SupplierHub\Domain\Category;

defined('ABSPATH') || exit;

final class CategoryMatcher {
    public function suggest(array $supplier, array $woocommerce, array $synonyms = []): array {
        $source_name = $this->normalize((string) $supplier['name'], $synonyms);
        $source_path = $this->normalize((string) $supplier['path'], $synonyms);
        $results = [];
        foreach ($woocommerce as $target) {
            $target_name = $this->normalize((string) $target['name'], $synonyms);
            $target_path = $this->normalize((string) $target['path'], $synonyms);
            $reasons = [];
            $score = 0.0;
            if ($source_path !== '' && $source_path === $target_path) { $score = 0.99; $reasons[] = 'identyczna ścieżka'; }
            if ($source_name !== '' && $source_name === $target_name) { $score = max($score, 0.88); $reasons[] = 'identyczna nazwa'; }
            $name_similarity = $this->similarity($source_name, $target_name);
            if ($name_similarity >= 0.55) { $score = max($score, 0.35 + 0.5 * $name_similarity); $reasons[] = 'podobieństwo nazwy ' . round($name_similarity * 100) . '%'; }
            $source_parts = array_values(array_filter(array_map('trim', explode('>', $source_path))));
            $target_parts = array_values(array_filter(array_map('trim', explode('>', $target_path))));
            if (count($source_parts) > 1 && count($target_parts) > 1 && $this->similarity($source_parts[0], $target_parts[0]) > 0.75) {
                $score = min(0.98, $score + 0.08); $reasons[] = 'zgodna gałąź nadrzędna';
            }
            if ($score >= 0.45) {
                $results[] = ['termId' => (int) $target['id'], 'name' => $target['name'], 'path' => $target['path'], 'score' => round($score, 4), 'reasons' => $reasons, 'autoAcceptable' => $score >= 0.95];
            }
        }
        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, 5);
    }

    public function normalize(string $value, array $synonyms = []): string {
        $value = $this->normalize_base($value);
        foreach ($synonyms as $from => $to) {
            $value = preg_replace('/\b' . preg_quote($this->normalize_base((string) $from), '/') . '\b/u', $this->normalize_base((string) $to), $value) ?? $value;
        }

        return $value;
    }

    private function normalize_base(string $value): string {
        $value = mb_strtolower(remove_accents(wp_strip_all_tags($value)));
        $value = preg_replace('/[^\p{L}\p{N}>]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $value;
    }

    private function similarity(string $a, string $b): float {
        if ($a === '' || $b === '') { return 0.0; }
        if ($a === $b) { return 1.0; }
        $max = max(mb_strlen($a), mb_strlen($b));
        if ($max > 250) { $a = mb_substr($a, 0, 250); $b = mb_substr($b, 0, 250); $max = max(strlen($a), strlen($b)); }

        return max(0.0, 1.0 - levenshtein($a, $b) / max(1, $max));
    }
}
