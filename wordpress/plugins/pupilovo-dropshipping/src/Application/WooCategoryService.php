<?php

namespace Pupilovo\SupplierHub\Application;

defined('ABSPATH') || exit;

final class WooCategoryService {
    public function tree_flat(): array {
        if (!taxonomy_exists('product_cat')) { return []; }
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        if (is_wp_error($terms)) { return []; }
        $by_id = [];
        foreach ($terms as $term) { $by_id[(int) $term->term_id] = $term; }
        $result = [];
        foreach ($by_id as $id => $term) {
            $parts = [$term->name]; $parent = (int) $term->parent; $guard = 0;
            while ($parent > 0 && isset($by_id[$parent]) && ++$guard < 50) { array_unshift($parts, $by_id[$parent]->name); $parent = (int) $by_id[$parent]->parent; }
            $result[] = ['id' => $id, 'name' => $term->name, 'parentId' => (int) $term->parent, 'path' => implode(' > ', $parts), 'count' => (int) $term->count];
        }
        usort($result, static fn(array $a, array $b): int => strcasecmp($a['path'], $b['path']));

        return $result;
    }
}
