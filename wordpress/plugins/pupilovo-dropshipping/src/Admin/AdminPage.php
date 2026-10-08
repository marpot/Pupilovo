<?php

namespace Pupilovo\SupplierHub\Admin;

defined('ABSPATH') || exit;

final class AdminPage {
    private const SLUG = 'pupilovo-supplier-hub';
    private static string $hook_suffix = '';

    public static function register(): void {
        self::$hook_suffix = (string) add_menu_page(
            __('Pupilovo Supplier Hub', 'pupilovo-supplier-hub'),
            __('Supplier Hub', 'pupilovo-supplier-hub'),
            'manage_pupilovo_supplier_hub',
            self::SLUG,
            [self::class, 'render'],
            'dashicons-store',
            56
        );
    }

    public static function enqueue_assets(string $hook_suffix): void {
        if ($hook_suffix !== self::$hook_suffix) {
            return;
        }

        $script = PUPILOVO_SUPPLIER_HUB_DIR . 'admin-app/dist/admin.js';
        $style = PUPILOVO_SUPPLIER_HUB_DIR . 'admin-app/dist/admin.css';
        if (!is_readable($script)) {
            return;
        }

        if (is_readable($style)) {
            wp_enqueue_style(
                'pupilovo-supplier-hub-admin',
                PUPILOVO_SUPPLIER_HUB_URL . 'admin-app/dist/admin.css',
                [],
                (string) filemtime($style)
            );
        }
        wp_enqueue_script(
            'pupilovo-supplier-hub-admin',
            PUPILOVO_SUPPLIER_HUB_URL . 'admin-app/dist/admin.js',
            [],
            (string) filemtime($script),
            true
        );
        wp_add_inline_script(
            'pupilovo-supplier-hub-admin',
            'window.pupilovoSupplierHub=' . wp_json_encode([
                'restUrl' => esc_url_raw(rest_url('pupilovo-supplier-hub/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'adminUrl' => esc_url_raw(admin_url()),
                'version' => PUPILOVO_SUPPLIER_HUB_VERSION,
            ]) . ';',
            'before'
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_pupilovo_supplier_hub')) {
            wp_die(esc_html__('Brak uprawnień do zarządzania Supplier Hub.', 'pupilovo-supplier-hub'));
        }

        echo '<div class="wrap pupilovo-supplier-hub-wrap">';
        echo '<div id="pupilovo-supplier-hub-root">';
        if (!is_readable(PUPILOVO_SUPPLIER_HUB_DIR . 'admin-app/dist/admin.js')) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Aplikacja administracyjna nie została zbudowana. Uruchom proces budowania opisany w README.', 'pupilovo-supplier-hub');
            echo '</p></div>';
        }
        echo '</div></div>';
    }
}
