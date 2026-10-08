<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Infrastructure\Http\SourceHttpClient;

defined('ABSPATH') || exit;

final class ProductImageImporter {
    private const MAX_IMAGES = 12;
    private const MAX_BYTES = 10485760;

    public function __construct(private readonly SourceHttpClient $http = new SourceHttpClient()) {}

    /** @return array{ids:array<int,int>,errors:array<int,string>} */
    public function import(array $urls, int $product_id): array {
        $ids = []; $errors = [];
        foreach (array_slice(array_values(array_unique(array_filter($urls, 'is_string'))), 0, self::MAX_IMAGES) as $url) {
            $result = $this->one(trim($url), $product_id);
            if (is_wp_error($result)) { $errors[] = $result->get_error_code(); continue; }
            $ids[] = $result;
        }
        return ['ids' => array_values(array_unique($ids)), 'errors' => array_values(array_unique($errors))];
    }

    private function one(string $url, int $product_id) {
        if ($url === '') { return new \WP_Error('empty_image_url', 'Pusty adres obrazu.'); }
        $download = $this->http->download($url, [], false, self::MAX_BYTES, 15);
        if (is_wp_error($download)) { return $download; }
        $temporary = $download['path'];
        try {
            $mime = function_exists('wp_get_image_mime') ? wp_get_image_mime($temporary) : false;
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif'];
            if (!$mime || !isset($extensions[$mime])) { return new \WP_Error('invalid_image_type', 'Źródło nie jest obsługiwanym obrazem.'); }
            $existing = get_posts([
                'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => 1,
                'meta_key' => '_pupilovo_source_checksum', 'meta_value' => $download['checksum'],
            ]);
            if ($existing !== []) { return (int) $existing[0]; }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $file = ['name' => 'supplier-' . substr($download['checksum'], 0, 16) . '.' . $extensions[$mime], 'tmp_name' => $temporary];
            $attachment_id = media_handle_sideload($file, $product_id, '', ['post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME))]);
            if (is_wp_error($attachment_id)) { return new \WP_Error('image_sideload_failed', 'Nie udało się zapisać obrazu.', ['cause' => $attachment_id->get_error_code()]); }
            update_post_meta($attachment_id, '_pupilovo_source_checksum', $download['checksum']);
            update_post_meta($attachment_id, '_pupilovo_source_url', esc_url_raw($url));
            return (int) $attachment_id;
        } finally {
            if (is_string($temporary) && file_exists($temporary)) { wp_delete_file($temporary); }
        }
    }
}
