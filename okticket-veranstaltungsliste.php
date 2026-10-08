<?php
/**
 * Plugin Name: OKTicket Veranstaltungsliste
 * Description: Ruft Veranstaltungsdaten lesend aus der OKTicket-API ab.
 * Version: 0.2.2
 * Author: okticket.de
 * Update URI: https://github.com/rieger-software/okticket-veranstaltungsliste/
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: okticket-veranstaltungsliste
 */

defined('ABSPATH') || exit;

final class Altmuehlbuehne_OKTicket_Veranstaltungsliste {
    private const OPTION_TOKEN = 'altmuehlbuehne_okticket_api_token';
    private const OPTION_SHORTCODES = 'altmuehlbuehne_okticket_shortcode_configs';
    private const OPTION_OVERVIEW_SHORTCODES = 'altmuehlbuehne_okticket_overview_shortcode_configs';
    private const OPTION_EVENTS_SNAPSHOT = 'altmuehlbuehne_okticket_events_snapshot';
    private const OPTION_EVENT_DETAILS_SNAPSHOT = 'altmuehlbuehne_okticket_event_details_snapshot';
    private const OPTION_LAST_SYNC = 'altmuehlbuehne_okticket_last_sync';
    private const API_URL = 'https://api.okticket.de/statistik/live/events';
    private const EVENT_DETAILS_URL = 'https://api.okticket.de/statistik/live/eventDetails/';
    private const UPDATE_URI = 'https://github.com/rieger-software/okticket-veranstaltungsliste/';
    private const GITHUB_RELEASE_API_URL = 'https://api.github.com/repos/rieger-software/okticket-veranstaltungsliste/releases/latest';
    private const CRON_HOOK = 'altmuehlbuehne_okticket_sync_events';
    private const CRON_SCHEDULE = 'altmuehlbuehne_okticket_every_fifteen_minutes';

    public static function init(): void {
        add_filter('cron_schedules', [self::class, 'add_cron_schedule']);
        add_action('init', [self::class, 'ensure_scheduled_sync']);
        add_action(self::CRON_HOOK, [self::class, 'run_scheduled_sync']);
        add_action('init', [self::class, 'register_overview_block']);
        add_action('init', [self::class, 'register_details_block']);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_block_editor_data']);
        add_action('admin_menu', [self::class, 'add_settings_page']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('admin_post_altmuehlbuehne_okticket_save_token', [self::class, 'save_token']);
        add_action('admin_post_altmuehlbuehne_okticket_delete_token', [self::class, 'delete_token']);
        add_action('admin_post_altmuehlbuehne_okticket_refresh_events', [self::class, 'refresh_events']);
        add_action('admin_post_altmuehlbuehne_okticket_preview_overview', [self::class, 'preview_overview']);
        add_action('admin_post_altmuehlbuehne_okticket_preview_event_page', [self::class, 'preview_event_page']);
        add_action('admin_post_altmuehlbuehne_okticket_save_shortcode', [self::class, 'save_shortcode']);
        add_action('admin_post_altmuehlbuehne_okticket_delete_shortcode', [self::class, 'delete_shortcode']);
        add_action('admin_post_altmuehlbuehne_okticket_save_overview_shortcode', [self::class, 'save_overview_shortcode']);
        add_action('admin_post_altmuehlbuehne_okticket_delete_overview_shortcode', [self::class, 'delete_overview_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_grid']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [self::class, 'add_plugin_action_links']);
        add_filter('update_plugins_github.com', [self::class, 'check_for_updates'], 10, 4);
        add_filter('plugins_api', [self::class, 'get_plugin_information'], 10, 3);
        add_shortcode('okticket_veranstaltungsliste', [self::class, 'render_events_shortcode']);
        add_shortcode('okticket_veranstaltungsuebersicht', [self::class, 'render_events_overview_shortcode']);
    }

    /**
     * Registers the recurring background sync interval.
     *
     * @param array<string, array<string, mixed>> $schedules Existing schedules.
     * @return array<string, array<string, mixed>>
     */
    public static function add_cron_schedule(array $schedules): array {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display' => 'Alle 15 Minuten (OKTicket)',
            ];
        }

        return $schedules;
    }

    /**
     * Schedules the sync for fresh installations and after plugin updates.
     */
    public static function ensure_scheduled_sync(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    /**
     * Runs from WP-Cron and keeps the last successful snapshot on failures.
     */
    public static function run_scheduled_sync(): void {
        self::sync_events();
    }

    /**
     * Removes the recurring task when the plugin is deactivated.
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function register_overview_block(): void {
        $file = plugin_dir_path(__FILE__) . 'assets/okticket-overview-block.js';

        wp_register_script(
            'okticket-overview-block',
            plugins_url('assets/okticket-overview-block.js', __FILE__),
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
            file_exists($file) ? (string) filemtime($file) : '0.2.0',
            true
        );

        register_block_type('okticket/veranstaltungsuebersicht', [
            'api_version' => 3,
            'editor_script' => 'okticket-overview-block',
            'render_callback' => [self::class, 'render_overview_block'],
            'attributes' => [
                'eventIds' => [
                    'type' => 'array',
                    'default' => [],
                ],
                'fields' => [
                    'type' => 'array',
                    'default' => self::overview_field_keys(),
                ],
            ],
        ]);
    }

    public static function enqueue_block_editor_data(): void {
        self::enqueue_frontend_grid();
        wp_add_inline_style('okticket-veranstaltungsliste-grid', '
            .editor-styles-wrapper .okticket-overview-block-preview {
                box-sizing: border-box;
                padding: 18px;
                border: 1px solid #e6e8ec;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 1px 2px rgba(31, 41, 55, .04), 0 2px 8px rgba(31, 41, 55, .035);
            }
            .editor-styles-wrapper .okticket-overview-block-preview .okticket-veranstaltung__kopf > .row {
                display: flex;
                flex-wrap: wrap;
            }
            .editor-styles-wrapper .okticket-overview-block-preview .okticket-veranstaltung__kopf > .row > .col {
                box-sizing: border-box;
                width: 100%;
                max-width: 100%;
            }
            .editor-styles-wrapper .okticket-overview-block-preview .okticket-veranstaltung__bild img {
                display: block;
                width: 100%;
                height: auto;
            }
            @media (min-width: 768px) {
                .editor-styles-wrapper .okticket-overview-block-preview .okticket-veranstaltung__kopf > .row > .col-md-6 {
                    flex: 0 0 50%;
                    width: 50%;
                    max-width: 50%;
                }
                .editor-styles-wrapper .okticket-overview-block-preview .okticket-veranstaltung__kopf > .row > .col-md-12 {
                    flex: 0 0 100%;
                    width: 100%;
                    max-width: 100%;
                }
            }
        ');

        $series = [];
        $result = self::get_events();

        foreach ($result['events'] ?? [] as $event) {
            $event_id = (int) ($event['event_id'] ?? 0);
            if ($event_id <= 0) {
                continue;
            }

            $details = self::get_event_details($event_id);
            $parent_id = (int) ($details['ParentID'] ?? 0);
            $parent_id = $parent_id > 0 ? $parent_id : $event_id;

            if (isset($series[$parent_id])) {
                continue;
            }

            $parent_details = $parent_id === $event_id ? $details : self::get_event_details($parent_id);
            $series[$parent_id] = [
                'value' => (string) $parent_id,
                'label' => (string) ($parent_details['Titel'] ?? $parent_details['Name'] ?? $event['name'] ?? 'Veranstaltung'),
            ];
        }

        uasort($series, static function (array $a, array $b): int {
            return strnatcasecmp($a['label'], $b['label']);
        });

        $fields = [];
        foreach (self::overview_field_labels() as $value => $label) {
            $fields[] = ['value' => $value, 'label' => $label];
        }

        $detail_fields = [];
        foreach (self::field_labels() as $value => $label) {
            $detail_fields[] = ['value' => $value, 'label' => $label];
        }

        wp_enqueue_script('okticket-overview-block');
        wp_enqueue_script('okticket-details-block');
        wp_add_inline_script(
            'okticket-overview-block',
            'window.OKTicketOverviewBlockData = ' . wp_json_encode([
                'series' => array_values($series),
                'fields' => $fields,
            ]) . ';',
            'before'
        );
        wp_add_inline_script(
            'okticket-details-block',
            'window.OKTicketDetailsBlockData = ' . wp_json_encode([
                'series' => array_values($series),
                'fields' => $detail_fields,
            ]) . ';',
            'before'
        );
    }

    public static function render_overview_block(array $attributes): string {
        $event_ids = isset($attributes['eventIds']) && is_array($attributes['eventIds'])
            ? array_values(array_unique(array_filter(array_map('absint', $attributes['eventIds']))))
            : [];
        $fields = isset($attributes['fields']) && is_array($attributes['fields'])
            ? array_values(array_intersect(self::overview_field_keys(), array_map('sanitize_key', $attributes['fields'])))
            : self::overview_field_keys();

        if (!$event_ids) {
            return '';
        }

        return self::render_events_overview_shortcode([
            'preview_config' => [
                'event_ids' => $event_ids,
                'fields' => $fields,
                'version' => 2,
            ],
        ]);
    }

    public static function register_details_block(): void {
        $file = plugin_dir_path(__FILE__) . 'assets/okticket-details-block.js';

        wp_register_script(
            'okticket-details-block',
            plugins_url('assets/okticket-details-block.js', __FILE__),
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
            file_exists($file) ? (string) filemtime($file) : '0.2.0',
            true
        );

        register_block_type('okticket/veranstaltungsdetails', [
            'api_version' => 3,
            'editor_script' => 'okticket-details-block',
            'render_callback' => [self::class, 'render_details_block'],
            'attributes' => [
                'eventId' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'fields' => [
                    'type' => 'array',
                    'default' => self::field_keys(),
                ],
            ],
        ]);
    }

    public static function render_details_block(array $attributes): string {
        $event_id = isset($attributes['eventId']) ? absint($attributes['eventId']) : 0;
        $fields = isset($attributes['fields']) && is_array($attributes['fields'])
            ? array_values(array_intersect(self::field_keys(), array_map('sanitize_key', $attributes['fields'])))
            : self::field_keys();

        if ($event_id <= 0) {
            return '';
        }

        return self::render_events_shortcode([
            'preview_config' => [
                'event_ids' => [$event_id],
                'fields' => $fields,
            ],
        ]);
    }

    public static function enqueue_admin_assets(): void {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($page !== 'okticket-settings') {
            return;
        }

        wp_enqueue_style(
            'okticket-tabler-ui',
            'https://cdn.jsdelivr.net/npm/@tabler/core@1.5.1/dist/css/tabler.min.css',
            [],
            '1.5.1'
        );
        wp_enqueue_script(
            'okticket-tabler-ui',
            'https://cdn.jsdelivr.net/npm/@tabler/core@1.5.1/dist/js/tabler.min.js',
            [],
            '1.5.1',
            true
        );

        $file = plugin_dir_path(__FILE__) . 'assets/admin.css';
        wp_enqueue_style(
            'okticket-veranstaltungsliste-admin',
            plugins_url('assets/admin.css', __FILE__),
            [],
            file_exists($file) ? (string) filemtime($file) : '0.2.0'
        );
    }

    public static function enqueue_frontend_grid(): void {
        wp_enqueue_style(
            'okticket-tabler-ui',
            'https://cdn.jsdelivr.net/npm/@tabler/core@1.5.1/dist/css/tabler.min.css',
            [],
            '1.5.1'
        );
        wp_register_style('okticket-veranstaltungsliste-grid', false, ['okticket-tabler-ui'], '0.2.0');
        wp_enqueue_style('okticket-veranstaltungsliste-grid');
        wp_add_inline_style('okticket-veranstaltungsliste-grid', '
            .okticket-bootstrap .okticket-veranstaltung__termine ul{margin:0;padding:0;list-style:none}
            .okticket-bootstrap .okticket-veranstaltung__termin{margin:0 0 .5rem}
            .okticket-bootstrap .okticket-veranstaltung__date{display:block;padding:.5rem .75rem;color:inherit;text-decoration:none;background:inherit;border:1px solid #c9c9c9;border-radius:.4rem;box-shadow:none;transition:border-color .15s ease,background-color .15s ease}
            .okticket-bootstrap .okticket-veranstaltung__date:hover,.okticket-bootstrap .okticket-veranstaltung__date:focus{color:inherit;text-decoration:none;background:rgba(0,0,0,.02);border-color:#999}
            .okticket-bootstrap .okticket-veranstaltung__date-row{align-items:center}
            .okticket-bootstrap .okticket-veranstaltung__date-full{font-weight:700}
            .okticket-bootstrap .okticket-veranstaltung__date-time-column{display:flex;justify-content:center}
            .okticket-bootstrap .okticket-veranstaltung__date-time{display:inline-block;font-weight:bold;padding:2px .55em;border-radius:.4rem;background:rgba(0,0,0,.05);opacity:.75;white-space:nowrap}
            .okticket-bootstrap .okticket-veranstaltung__beschreibung{margin:0 0 .5rem;line-height:1.4}
            .okticket-bootstrap .okticket-veranstaltung__langbeschreibung{padding-bottom:10px}
            .okticket-bootstrap .okticket-veranstaltung__titel{margin:0 0 .35rem!important;font-size:1.2em;font-weight:700}
            .okticket-bootstrap .okticket-veranstaltung__ort{margin:0 0 1rem;font-size:.95em}
            .okticket-bootstrap .okticket-veranstaltung__date-status{white-space:nowrap}
            .okticket-bootstrap .okticket-veranstaltung__status-dot{display:inline;font-size:.85em;line-height:1;background:none}
            .okticket-bootstrap .okticket-veranstaltung__status--available .okticket-veranstaltung__status-dot{color:#198754}
            .okticket-bootstrap .okticket-veranstaltung__status--few .okticket-veranstaltung__status-dot{color:#d97706}
            .okticket-bootstrap .okticket-veranstaltung__status--critical .okticket-veranstaltung__status-dot{color:#dc3545}
            .okticket-bootstrap .okticket-veranstaltung__status--sold-out .okticket-veranstaltung__status-dot{color:#6c757d}
            .okticket-bootstrap .okticket-veranstaltung__date-chevron-column{display:flex;justify-content:flex-end}
            .okticket-bootstrap .okticket-veranstaltung__date-chevron{font-size:1.5em;line-height:1}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht{width:100%}
            .okticket-bootstrap .searchItem{margin:0 0 1.5rem;padding:1rem;background:#fff;border:1px solid #c9c9c9;border-radius:.4rem}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__link{display:block;color:inherit;text-decoration:none}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__link:hover,.okticket-bootstrap .okticket-veranstaltungsuebersicht__link:focus{color:inherit;text-decoration:none}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__kopf{display:grid;grid-template-columns:160px minmax(0,1fr);gap:1rem;align-items:start}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__kopf--ohne-bild{grid-template-columns:minmax(0,1fr)}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__bild{display:block;width:160px;height:112px;object-fit:cover}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__inhalt{min-width:0}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__titel{margin:0 0 .35rem!important;font-size:1.2em;font-weight:700}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__ort{margin:0 0 1rem;font-size:.95em}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__beschreibung{margin:0;line-height:1.4}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__termine{margin-top:1.25rem}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__termine-titel{margin:0 0 .5rem;font-size:1em;font-weight:700}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__termine-liste{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem 1rem;margin:0;padding:0;list-style:none}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__termin{margin:0;padding:.35rem .5rem;border-radius:.25rem;background:rgba(0,0,0,.05);font-weight:600}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__weitere{background:transparent;color:inherit;font-weight:400}
            @media (max-width:650px){.okticket-bootstrap .searchItem{padding:.75rem}.okticket-bootstrap .okticket-veranstaltungsuebersicht__kopf{grid-template-columns:125px minmax(0,1fr);gap:.75rem}.okticket-bootstrap .okticket-veranstaltungsuebersicht__bild{width:125px;height:88px}.okticket-bootstrap .okticket-veranstaltungsuebersicht__beschreibung{display:none}.okticket-bootstrap .okticket-veranstaltungsuebersicht__ort{margin-bottom:0}.okticket-bootstrap .okticket-veranstaltungsuebersicht__termine-liste{grid-template-columns:1fr}.okticket-bootstrap .okticket-veranstaltungsuebersicht__kopf--ohne-bild{grid-template-columns:minmax(0,1fr)}}
        ');
    }

    public static function add_settings_page(): void {
        add_options_page('OKTicket Einstellungen', 'OKTicket', 'manage_options', 'okticket-settings', [self::class, 'render_settings_page']);
    }

    public static function add_plugin_action_links(array $links): array {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=okticket-settings')) . '">Einstellungen</a>';
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Provides WordPress with updates from the latest public GitHub release.
     *
     * WordPress decides when to run update checks and whether an automatic
     * update is enabled. This plugin only supplies the release metadata.
     *
     * @param array|false $update Existing update response.
     * @param array<string, mixed> $plugin_data Plugin header data.
     * @param string $plugin_file Plugin basename.
     * @param string[] $locales Installed locales.
     * @return array|false
     */
    public static function check_for_updates($update, array $plugin_data, string $plugin_file, array $locales) {
        unset($locales);

        if ($plugin_file !== plugin_basename(__FILE__)) {
            return $update;
        }

        $release = self::get_latest_github_release();
        $version = is_array($release) ? self::get_release_version($release) : '';
        $package = is_array($release) && $version !== '' ? self::get_release_package($release, $version) : '';

        if (
            $version === ''
            || $package === ''
            || version_compare($version, (string) $plugin_data['Version'], '<=')
        ) {
            return $update;
        }

        return [
            'id' => self::UPDATE_URI,
            'slug' => 'okticket-veranstaltungsliste',
            'version' => $version,
            'url' => self::UPDATE_URI,
            'package' => $package,
            'tested' => '',
            'requires' => '6.4',
            'requires_php' => '7.4',
        ];
    }

    /**
     * Supplies the native WordPress plugin-details modal.
     *
     * @param false|object|array $result Existing API result.
     * @param string $action Requested API action.
     * @param object $args Request arguments.
     * @return false|object|array
     */
    public static function get_plugin_information($result, string $action, $args) {
        if (
            $action !== 'plugin_information'
            || !is_object($args)
            || !isset($args->slug)
            || $args->slug !== 'okticket-veranstaltungsliste'
        ) {
            return $result;
        }

        $release = self::get_latest_github_release();
        $version = is_array($release) ? self::get_release_version($release) : '';
        if ($version === '') {
            return $result;
        }

        $body = isset($release['body']) ? (string) $release['body'] : '';
        $published_at = isset($release['published_at']) ? sanitize_text_field((string) $release['published_at']) : '';

        return (object) [
            'name' => 'OKTicket Veranstaltungsliste',
            'slug' => 'okticket-veranstaltungsliste',
            'version' => $version,
            'author' => '<a href="https://okticket.de/">okticket.de</a>',
            'homepage' => 'https://okticket.de/',
            'requires' => '6.4',
            'requires_php' => '7.4',
            'last_updated' => $published_at,
            'download_link' => self::get_release_package($release, $version),
            'sections' => [
                'description' => '<p>Verbindet WordPress mit der OKTicket-API und stellt Veranstaltungsdaten als Blöcke bereit.</p>',
                'changelog' => wpautop(esc_html($body !== '' ? $body : 'Keine Änderungsnotizen verfügbar.')),
            ],
        ];
    }

    /**
     * Retrieves the latest stable release metadata from GitHub.
     *
     * @return array<string, mixed>|null
     */
    private static function get_latest_github_release(): ?array {
        $response = wp_remote_get(self::GITHUB_RELEASE_API_URL, [
            'timeout' => 5,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'OKTicket-Veranstaltungsliste',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (
            !is_array($release)
            || !empty($release['draft'])
            || !empty($release['prerelease'])
        ) {
            return null;
        }

        return $release;
    }

    /**
     * Converts a GitHub tag such as v0.2.1 to a WordPress plugin version.
     */
    private static function get_release_version(array $release): string {
        $tag = isset($release['tag_name']) ? trim((string) $release['tag_name']) : '';
        $version = preg_replace('/^v/i', '', $tag);

        if (!is_string($version) || !preg_match('/^[0-9][0-9A-Za-z.+_-]*$/', $version)) {
            return '';
        }

        return $version;
    }

    /**
     * Returns only the explicitly uploaded, versioned WordPress plugin ZIP.
     */
    private static function get_release_package(array $release, string $version): string {
        $expected_name = 'okticket-veranstaltungsliste-' . $version . '.zip';
        $assets = isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : [];

        foreach ($assets as $asset) {
            if (!is_array($asset) || ($asset['name'] ?? '') !== $expected_name) {
                continue;
            }

            $url = isset($asset['browser_download_url']) ? esc_url_raw((string) $asset['browser_download_url']) : '';
            if (
                $url !== ''
                && wp_parse_url($url, PHP_URL_SCHEME) === 'https'
                && wp_parse_url($url, PHP_URL_HOST) === 'github.com'
            ) {
                return $url;
            }
        }

        return '';
    }

    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $has_token = self::get_token() !== '';
        $notice = isset($_GET['okticket_notice'])
            ? sanitize_key(wp_unslash($_GET['okticket_notice']))
            : '';
        $events_result = $has_token ? self::get_events() : null;
        $api_is_available = $has_token && self::validate_token(self::get_token()) === 'valid';
        $api_status_class = $api_is_available ? 'success' : ($has_token ? 'danger' : 'secondary');
        $api_status_label = $api_is_available ? 'API erreichbar' : ($has_token ? 'API momentan nicht erreichbar' : 'Kein Token gespeichert');
        ?>
        <div class="wrap okticket-admin">
            <div class="page-header d-print-none mb-4">
                <div class="container-fluid">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md bg-transparent">
                                <img src="<?php echo esc_url(plugins_url('assets/okticket-logo.png', __FILE__)); ?>" alt="OKTicket Logo" class="avatar-img">
                            </span>
                        </div>
                        <div class="col">
                            <h2 class="page-title">OKTicket Einstellungen</h2>
                            <div class="text-secondary mt-1">Verwalten Sie Ihre API-Anbindung und Synchronisation</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-body">
                <div class="container-fluid">

            <?php if ($notice === 'valid') : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Der API-Token ist gültig und wurde gespeichert.</p>
                </div>
            <?php elseif ($notice === 'invalid') : ?>
                <div class="notice notice-error">
                    <p>Der API-Token wurde von OKTicket nicht akzeptiert und deshalb nicht gespeichert.</p>
                </div>
            <?php elseif ($notice === 'unreachable') : ?>
                <div class="notice notice-error">
                    <p>Die OKTicket-API ist momentan nicht erreichbar. Der Token wurde nicht verändert.</p>
                </div>
            <?php elseif ($notice === 'missing') : ?>
                <div class="notice notice-warning">
                    <p>Bitte einen API-Token eingeben.</p>
                </div>
            <?php elseif ($notice === 'token_deleted') : ?>
                <div class="notice notice-success is-dismissible"><p>Der API-Token wurde gelöscht. Die Verbindung zu OKTicket ist getrennt.</p></div>
            <?php elseif ($notice === 'shortcode_saved') : ?>
                <div class="notice notice-success is-dismissible"><p>Der Shortcode wurde gespeichert.</p></div>
            <?php elseif ($notice === 'shortcode_deleted') : ?>
                <div class="notice notice-success is-dismissible"><p>Der Shortcode wurde gelöscht.</p></div>
            <?php elseif ($notice === 'shortcode_missing') : ?>
                <div class="notice notice-error"><p>Bitte Name, mindestens eine Veranstaltung und mindestens ein Feld auswählen.</p></div>
            <?php elseif ($notice === 'synced') : ?>
                <div class="notice notice-success is-dismissible"><p>Die OKTicket-Daten wurden vollständig aktualisiert und lokal gespeichert.</p></div>
            <?php elseif ($notice === 'sync_failed') : ?>
                <div class="notice notice-error"><p>Die OKTicket-Daten konnten nicht aktualisiert werden. Der zuletzt gespeicherte Datenstand bleibt erhalten.</p></div>
            <?php endif; ?>

            <script>
                document.addEventListener('click', function (event) {
                    var button = event.target.closest('.okticket-copy-shortcode');
                    if (!button || !navigator.clipboard) { return; }
                    navigator.clipboard.writeText(button.dataset.shortcode).then(function () {
                        var label = button.textContent;
                        button.textContent = 'Kopiert';
                        window.setTimeout(function () { button.textContent = label; }, 1500);
                    });
                });
            </script>

                    <div class="row row-cards">
                        <div class="col-12 col-xxl-7">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">API-Konfiguration</h3>
                                    <div class="card-actions">
                                        <span class="badge bg-<?php echo esc_attr($api_status_class); ?>-lt d-flex align-items-center gap-1">
                                            <span class="status-dot status-dot-animated bg-<?php echo esc_attr($api_status_class); ?>"></span>
                                            <?php echo esc_html($api_status_label); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form id="okticket-token-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="altmuehlbuehne_okticket_save_token">
                                        <?php wp_nonce_field('altmuehlbuehne_okticket_save_token'); ?>
                                        <div class="mb-3 row">
                                            <label for="okticket_api_token" class="col-3 col-form-label required">
                                                API-Token
                                                <button type="button" class="form-help border-0" data-bs-toggle="modal" data-bs-target="#okticket-api-help" aria-label="Hilfe zum API-Token öffnen">?</button>
                                            </label>
                                            <div class="col-9">
                            <input
                                type="password"
                                id="okticket_api_token"
                                name="okticket_api_token"
                                value=""
                                class="form-control"
                                placeholder="••••••••••••••••"
                                autocomplete="off"
                                spellcheck="false"
                            >
                            <small class="form-hint">
                                <?php if ($has_token) : ?>
                                    Leer lassen, um den gespeicherten Token erneut zu prüfen.
                                <?php else : ?>
                                    Der Token wird vor dem Speichern direkt bei OKTicket geprüft.
                                <?php endif; ?>
                            </small>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between bg-transparent">
                                    <div class="btn-list">
                                        <button type="submit" form="okticket-token-form" class="btn btn-primary btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plug-connected" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M7 12l5 5l-1.5 1.5a3.536 3.536 0 1 1 -5 -5l1.5 -1.5z"/>
                                                <path d="M17 12l-5 -5l1.5 -1.5a3.536 3.536 0 1 1 5 5l-1.5 1.5z"/>
                                                <path d="M3 21l2.5 -2.5"/><path d="M18.5 5.5l2.5 -2.5"/><path d="M10 11l-2 -2"/><path d="M13 14l-2 -2"/>
                                            </svg>
                                            <?php echo esc_html($has_token ? 'Verbindung zu OKTicket prüfen' : 'Token prüfen und speichern'); ?>
                                        </button>
                                        <form class="d-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="altmuehlbuehne_okticket_refresh_events">
                                            <?php wp_nonce_field('altmuehlbuehne_okticket_refresh_events'); ?>
                                            <button type="submit" class="btn btn-outline-secondary btn-sm" <?php disabled(!$api_is_available); ?>>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-refresh" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                                </svg>
                                                Daten jetzt aktualisieren
                                            </button>
                                        </form>
                                    </div>
                                    <?php if ($has_token) : ?>
                                        <form class="d-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="altmuehlbuehne_okticket_delete_token">
                                            <?php wp_nonce_field('altmuehlbuehne_okticket_delete_token'); ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-unlink" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                    <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5"/><path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5"/>
                                                </svg>
                                                Token löschen &amp; Verbindung trennen
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

            <div class="modal modal-blur fade" id="okticket-api-help" tabindex="-1" aria-labelledby="okticket-api-help-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title" id="okticket-api-help-title">Hilfe zum API-Token</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <p>Der API-Token verbindet dieses Plugin mit OKTicket, damit Veranstaltungsdaten abgerufen werden können.</p>
                            <ol class="okticket-api-help-steps">
                                <li>
                                    <strong>API-Einstellungen öffnen:</strong> Im OKTicket-Veranstalterkonto oben rechts auf Ihren Veranstalter-Namen klicken und im geöffneten Menü <strong>„API“</strong> auswählen.
                                    <img class="okticket-api-help-image" src="<?php echo esc_url(plugins_url('assets/okticket-api-menu.png', __FILE__)); ?>" alt="Das Veranstalter-Menü bei OKTicket mit dem Menüpunkt API">
                                </li>
                                <li>
                                    <strong>Token anlegen:</strong> Auf der API-Seite auf <strong>„Neues Token erstellen“</strong> klicken. Den danach angezeigten Token kopieren, hier einfügen und anschließend <strong>„Token prüfen und speichern“</strong> wählen.
                                    <img class="okticket-api-help-image" src="<?php echo esc_url(plugins_url('assets/okticket-api-token.png', __FILE__)); ?>" alt="Die API-Seite bei OKTicket mit der Schaltfläche Neues Token erstellen; der Token ist maskiert">
                                </li>
                            </ol>
                            <p>Bereits gespeicherte Token werden aus Sicherheitsgründen nicht angezeigt. Bei Fragen zum Token wenden Sie sich bitte an den OKTicket-Support.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Verstanden</button>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function save_token(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_save_token');

        $submitted_token = isset($_POST['okticket_api_token'])
            ? trim(sanitize_text_field(wp_unslash($_POST['okticket_api_token'])))
            : '';
        $token = $submitted_token !== '' ? $submitted_token : self::get_token();

        if ($token === '') {
            self::redirect_with_notice('missing', 'api');
        }

        $validation = self::validate_token($token);

        if ($validation === 'valid') {
            if ($submitted_token !== '') {
                update_option(self::OPTION_TOKEN, $submitted_token, false);
            }
            self::redirect_with_notice('valid', 'api');
        }

        self::redirect_with_notice($validation, 'api');
    }

    public static function delete_token(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_delete_token');
        delete_option(self::OPTION_TOKEN);
        self::redirect_with_notice('token_deleted', 'api');
    }

    public static function refresh_events(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_refresh_events');
        self::redirect_with_notice(self::sync_events() ? 'synced' : 'sync_failed', 'dashboard');
    }

    public static function preview_overview(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        $id = isset($_GET['overview_id']) ? sanitize_key(wp_unslash($_GET['overview_id'])) : '';
        check_admin_referer('altmuehlbuehne_okticket_preview_overview_' . $id);
        $config = $id !== '' ? (self::get_overview_shortcode_configs()[$id] ?? null) : null;
        $preview_config = self::get_preview_overview_config();

        if (is_array($preview_config)) {
            $config = $preview_config;
        }

        if (!is_array($config)) {
            wp_die('Diese Veranstaltungsübersicht wurde nicht gefunden.', 'Vorschau nicht verfügbar', ['response' => 404]);
        }

        nocache_headers();
        do_action('wp_enqueue_scripts');
        if (get_template_directory() !== get_stylesheet_directory()) {
            wp_enqueue_style('okticket-preview-parent-theme', get_template_directory_uri() . '/style.css', [], null);
        }
        $stylesheet_uri = get_stylesheet_uri();
        if ($stylesheet_uri !== '') {
            wp_enqueue_style('okticket-preview-active-theme', $stylesheet_uri, [], null);
        }
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html((string) ($config['name'] ?? 'Veranstaltungsübersicht')); ?> – Vorschau</title>
            <?php wp_head(); ?>
            <style>.okticket-preview-notice{max-width:1200px;margin:18px auto;padding:10px 16px;background:#f0f6fc;border-left:4px solid #2271b1;box-sizing:border-box;font:14px/1.4 sans-serif}.okticket-preview-content{max-width:1200px;margin:0 auto;padding:0 16px 32px;box-sizing:border-box}.okticket-preview .okticket-veranstaltungsuebersicht__beschreibung{display:block!important}</style>
        </head>
        <body <?php body_class('okticket-preview'); ?>>
            <?php wp_body_open(); ?>
            <div class="okticket-preview-notice">Vorschau: <?php echo esc_html((string) ($config['name'] ?? $id)); ?>. Diese Ansicht ist nur für Administratoren sichtbar.</div>
            <main class="okticket-preview-content">
                <?php echo self::render_events_overview_shortcode(['id' => $id, 'preview_config' => $config]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    public static function preview_event_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        $id = isset($_GET['event_page_id']) ? sanitize_key(wp_unslash($_GET['event_page_id'])) : '';
        check_admin_referer('altmuehlbuehne_okticket_preview_event_page_' . $id);
        $config = $id !== '' ? (self::get_shortcode_configs()[$id] ?? null) : null;
        $preview_config = self::get_preview_shortcode_config();

        if (is_array($preview_config)) {
            $config = $preview_config;
        }

        if (!is_array($config)) {
            wp_die('Diese Veranstaltungsseite wurde nicht gefunden.', 'Vorschau nicht verfügbar', ['response' => 404]);
        }

        nocache_headers();
        do_action('wp_enqueue_scripts');
        if (get_template_directory() !== get_stylesheet_directory()) {
            wp_enqueue_style('okticket-preview-parent-theme', get_template_directory_uri() . '/style.css', [], null);
        }
        $stylesheet_uri = get_stylesheet_uri();
        if ($stylesheet_uri !== '') {
            wp_enqueue_style('okticket-preview-active-theme', $stylesheet_uri, [], null);
        }
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html((string) ($config['name'] ?? 'Veranstaltungsseite')); ?> – Vorschau</title>
            <?php wp_head(); ?>
            <style>.okticket-preview-notice{max-width:1200px;margin:18px auto;padding:10px 16px;background:#f0f6fc;border-left:4px solid #2271b1;box-sizing:border-box;font:14px/1.4 sans-serif}.okticket-preview-content{max-width:1200px;margin:0 auto;padding:0 16px 32px;box-sizing:border-box}</style>
        </head>
        <body <?php body_class('okticket-preview'); ?>>
            <?php wp_body_open(); ?>
            <div class="okticket-preview-notice">Vorschau: <?php echo esc_html((string) ($config['name'] ?? $id)); ?>. Diese Ansicht ist nur für Administratoren sichtbar.</div>
            <main class="okticket-preview-content">
                <?php echo self::render_events_shortcode(['id' => $id, 'preview_config' => $config]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    private static function render_dashboard(?array $result, bool $has_token): void {
        $connection_label = !$has_token
            ? 'Nicht eingerichtet'
            : (($result['status'] ?? 0) === 200 ? 'Verbunden' : 'Nicht erreichbar');
        $connection_class = !$has_token || ($result['status'] ?? 0) !== 200 ? 'notice-error' : 'notice-success';
        ?>
        <div class="okticket-dashboard-grid">
            <div class="okticket-dashboard-card">
                <div class="okticket-dashboard-card-header">
                    <h2>Verbindung</h2>
                    <p class="<?php echo esc_attr($connection_class); ?>"><strong><?php echo esc_html($connection_label); ?></strong></p>
                </div>
                <p><?php echo $has_token ? 'Ein API-Token ist gespeichert.' : 'Es ist noch kein API-Token gespeichert.'; ?></p>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dashboard&tab=api')); ?>">Verbindung verwalten</a>
            </div>
        </div>
        <div class="okticket-dashboard-actions">
            <h2>Daten aktualisieren</h2>
            <p>Lädt alle Veranstaltungs- und Detaildaten einmal von OKTicket und speichert sie lokal. Backend und Frontend verwenden anschließend ausschließlich diesen gespeicherten Datenstand.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="altmuehlbuehne_okticket_refresh_events">
                <?php wp_nonce_field('altmuehlbuehne_okticket_refresh_events'); ?>
                <?php submit_button('Daten jetzt aktualisieren', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    private static function render_views_manager(?array $result): void {
        ?>
        <p>Dieser ältere Direktlink fasst die Veranstaltungsübersicht und die einzelnen Veranstaltungsseiten zusammen.</p>
        <?php self::render_shortcode_manager($result); ?>
        <hr>
        <?php self::render_overview_shortcode_manager($result); ?>
        <?php
    }

    public static function save_shortcode(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_save_shortcode');

        $name = isset($_POST['shortcode_name']) ? sanitize_text_field(wp_unslash($_POST['shortcode_name'])) : '';
        $event_ids = isset($_POST['event_ids']) && is_array($_POST['event_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['event_ids'])))))
            : [];
        $event_ids = array_slice($event_ids, 0, 1);
        $fields = isset($_POST['fields']) && is_array($_POST['fields'])
            ? array_values(array_intersect(self::field_keys(), array_map('sanitize_key', wp_unslash($_POST['fields']))))
            : [];

        if ($name === '' || !$event_ids || !$fields) {
            self::redirect_with_notice('shortcode_missing', 'pages');
        }

        $configs = self::get_shortcode_configs();
        $editing_id = isset($_POST['shortcode_id']) ? sanitize_key(wp_unslash($_POST['shortcode_id'])) : '';

        if ($editing_id !== '' && isset($configs[$editing_id])) {
            $id = $editing_id;
        } else {
            $base_id = sanitize_title($name);
            $id = $base_id !== '' ? $base_id : 'veranstaltungen';
            $suffix = 2;
            while (isset($configs[$id])) {
                $id = $base_id . '-' . $suffix++;
            }
        }

        $configs[$id] = [
            'name' => $name,
            'event_ids' => $event_ids,
            'fields' => $fields,
        ];
        update_option(self::OPTION_SHORTCODES, $configs, false);
        self::redirect_with_notice('shortcode_saved', 'pages');
    }

    public static function delete_shortcode(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_delete_shortcode');
        $id = isset($_POST['shortcode_id']) ? sanitize_key(wp_unslash($_POST['shortcode_id'])) : '';
        $configs = self::get_shortcode_configs();

        if ($id !== '' && isset($configs[$id])) {
            unset($configs[$id]);
            update_option(self::OPTION_SHORTCODES, $configs, false);
        }

        self::redirect_with_notice('shortcode_deleted', 'pages');
    }

    public static function save_overview_shortcode(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_save_overview_shortcode');

        $name = isset($_POST['shortcode_name']) ? sanitize_text_field(wp_unslash($_POST['shortcode_name'])) : '';
        $event_ids = isset($_POST['event_ids']) && is_array($_POST['event_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['event_ids'])))))
            : [];
        $fields = isset($_POST['fields']) && is_array($_POST['fields'])
            ? array_values(array_intersect(self::overview_field_keys(), array_map('sanitize_key', wp_unslash($_POST['fields']))))
            : [];

        if ($name === '' || !$event_ids || !$fields) {
            self::redirect_with_notice('shortcode_missing', 'overview');
        }

        $configs = self::get_overview_shortcode_configs();
        $editing_id = isset($_POST['shortcode_id']) ? sanitize_key(wp_unslash($_POST['shortcode_id'])) : '';

        if ($editing_id !== '' && isset($configs[$editing_id])) {
            $id = $editing_id;
        } else {
            $base_id = sanitize_title($name);
            $id = $base_id !== '' ? $base_id : 'veranstaltungen';
            $suffix = 2;
            while (isset($configs[$id])) {
                $id = $base_id . '-' . $suffix++;
            }
        }

        $configs[$id] = [
            'name' => $name,
            'event_ids' => $event_ids,
            'fields' => $fields,
        ];
        $configs[$id]['version'] = 2;
        update_option(self::OPTION_OVERVIEW_SHORTCODES, $configs, false);
        self::redirect_with_notice('shortcode_saved', 'overview');
    }

    public static function delete_overview_shortcode(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_delete_overview_shortcode');
        $id = isset($_POST['shortcode_id']) ? sanitize_key(wp_unslash($_POST['shortcode_id'])) : '';
        $configs = self::get_overview_shortcode_configs();

        if ($id !== '' && isset($configs[$id])) {
            unset($configs[$id]);
            update_option(self::OPTION_OVERVIEW_SHORTCODES, $configs, false);
        }

        self::redirect_with_notice('shortcode_deleted', 'overview');
    }

    private static function validate_token(string $token): string {
        $response = wp_remote_post(self::API_URL, [
            'timeout' => 20,
            'body' => ['token' => $token],
        ]);

        if (is_wp_error($response)) {
            return 'unreachable';
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            return in_array($status, [401, 403], true) ? 'invalid' : 'unreachable';
        }

        return is_array($data) && isset($data['events']) && is_array($data['events'])
            ? 'valid'
            : 'invalid';
    }

    private static function get_token(): string {
        $token = get_option(self::OPTION_TOKEN, '');
        return is_string($token) ? trim($token) : '';
    }

    private static function get_events(): array {
        $events = get_option(self::OPTION_EVENTS_SNAPSHOT, null);

        if (is_array($events)) {
            return ['status' => 200, 'events' => $events];
        }

        return ['status' => 0, 'events' => [], 'error' => 'Noch nicht synchronisiert'];
    }

    private static function sync_events(): bool {
        $token = self::get_token();

        if ($token === '') {
            return false;
        }

        $response = wp_remote_post(self::API_URL, [
            'timeout' => 30,
            'body' => ['token' => $token],
        ]);
        $data = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200 || !is_array($data) || !is_array($data['events'] ?? null)) {
            return false;
        }

        $details_snapshot = [];

        foreach ($data['events'] as $event) {
            $event_id = (int) ($event['event_id'] ?? 0);
            if ($event_id <= 0) {
                continue;
            }

            $detail_response = wp_remote_post(self::EVENT_DETAILS_URL . rawurlencode((string) $event_id), [
                'timeout' => 30,
                'body' => ['token' => $token],
            ]);
            $details = is_wp_error($detail_response) ? null : json_decode(wp_remote_retrieve_body($detail_response), true);

            if (wp_remote_retrieve_response_code($detail_response) === 200 && is_array($details) && (int) ($details['ID'] ?? 0) === $event_id) {
                $details_snapshot[$event_id] = $details;

                $parent_id = (int) ($details['ParentID'] ?? 0);
                if ($parent_id > 0 && $parent_id !== $event_id && !isset($details_snapshot[$parent_id])) {
                    $parent_response = wp_remote_post(self::EVENT_DETAILS_URL . rawurlencode((string) $parent_id), [
                        'timeout' => 30,
                        'body' => ['token' => $token],
                    ]);
                    $parent_details = is_wp_error($parent_response) ? null : json_decode(wp_remote_retrieve_body($parent_response), true);

                    if (wp_remote_retrieve_response_code($parent_response) === 200 && is_array($parent_details) && (int) ($parent_details['ID'] ?? 0) === $parent_id) {
                        $details_snapshot[$parent_id] = $parent_details;
                    }
                }
            }
        }

        update_option(self::OPTION_EVENTS_SNAPSHOT, $data['events'], false);
        update_option(self::OPTION_EVENT_DETAILS_SNAPSHOT, $details_snapshot, false);
        update_option(self::OPTION_LAST_SYNC, time(), false);

        return true;
    }

    public static function render_events_shortcode(array $atts = []): string {
        if (self::get_token() === '') {
            return '';
        }

        $preview_config = isset($atts['preview_config']) && is_array($atts['preview_config']) ? $atts['preview_config'] : null;
        $atts = shortcode_atts(['id' => ''], $atts, 'okticket_veranstaltungsliste');
        $config_id = sanitize_key((string) $atts['id']);
        $config = $preview_config ?? ($config_id !== '' ? (self::get_shortcode_configs()[$config_id] ?? null) : null);

        if ($config_id !== '' && !is_array($config)) {
            return '';
        }

        $fields = is_array($config) ? $config['fields'] : self::field_keys();
        $selected_ids = is_array($config) ? array_slice(array_map('absint', $config['event_ids']), 0, 1) : [];

        $result = self::get_events();

        if (($result['status'] ?? 0) !== 200 || empty($result['events'])) {
            return '';
        }

        // Older configurations may contain an appointment ID. Resolve it to its
        // parent event so existing shortcodes continue to display the full series.
        $selected_ids = array_values(array_unique(array_map(static function (int $event_id): int {
            $details = self::get_event_details($event_id);
            $parent_id = (int) ($details['ParentID'] ?? 0);
            return $parent_id > 0 ? $parent_id : $event_id;
        }, $selected_ids)));

        $parent_events = [];

        foreach ($result['events'] as $event) {
            $event_id = (int) ($event['event_id'] ?? 0);
            $child_details = self::get_event_details($event_id);
            $parent_id = (int) ($child_details['ParentID'] ?? 0);
            $parent_id = $parent_id > 0 ? $parent_id : $event_id;

            if (is_array($config) && !in_array($parent_id, $selected_ids, true)) {
                continue;
            }

            if (!isset($parent_events[$parent_id])) {
                $parent_events[$parent_id] = [
                    'event' => $event,
                    'details' => $parent_id === $event_id ? $child_details : self::get_event_details($parent_id),
                    'appointments' => [],
                ];
            }

            $parent_events[$parent_id]['appointments'][] = [
                'event' => $event,
                'details' => $child_details,
            ];
        }

        if (!$parent_events) {
            return '';
        }

        ob_start();
        ?>
        <div class="okticket-bootstrap">
        <div class="container-fluid okticket-veranstaltungsliste">
            <?php foreach ($parent_events as $parent_event) : ?>
                <?php
                $event = $parent_event['event'];
                $details = $parent_event['details'];
                $appointments = $parent_event['appointments'];
                $title = (string) ($details['Name'] ?? $details['Titel'] ?? $event['name'] ?? 'Veranstaltung');
                $image_url = (string) ($details['BildUrl'] ?? '');
                $purchase_url = (string) ($details['DetailundKaufUrl'] ?? '');
                $date = trim((string) ($details['Datum'] ?? ''));
                $time = trim((string) ($details['Zeit'] ?? ''));
                $date_parts = array_filter(explode('/', $date));
                $date_label = implode(' – ', array_map(static function (string $date_part): string {
                    $timestamp = strtotime($date_part);
                    return $timestamp ? wp_date('j. F Y', $timestamp) : $date_part;
                }, $date_parts));
                $venue = trim((string) ($details['OrtNameLang'] ?? $details['OrtName'] ?? $event['ort'] ?? ''));
                $address = trim(implode(', ', array_filter([
                    (string) ($details['OrtStrasse'] ?? ''),
                    trim((string) ($details['OrtPLZ'] ?? '') . ' ' . (string) ($details['OrtName'] ?? '')),
                ])));
                $description = (string) ($details['KurzBeschreibung'] ?? '');
                $long_description = (string) ($details['LangBeschreibung'] ?? '');
                $has_image = in_array('image', $fields, true) && $image_url !== '';
                $meta_rows = [];
                if (in_array('venue', $fields, true) && $address !== '') {
                    $meta_rows[] = ['Adresse:', $address];
                }
                $additional_fields = [];
                foreach (self::event_api_field_map() as $field_key => $api_key) {
                    if (
                        !in_array($field_key, $fields, true)
                        || in_array($field_key, ['api_name', 'api_kurzbeschreibung', 'api_langbeschreibung', 'api_ortnamelang'], true)
                    ) {
                        continue;
                    }
                    $value = self::format_event_api_value($details[$api_key] ?? ($event[strtolower($api_key)] ?? null));
                    if ($value !== '') {
                        $additional_fields[] = [$api_key . ':', $value];
                    }
                }
                ?>
                <article class="okticket-veranstaltung">
                    <header class="okticket-veranstaltung__kopf">
                        <div class="row">
                        <?php if ($has_image) : ?>
                            <div class="col col-md-6 okticket-veranstaltung__bild">
                                <?php if ($purchase_url !== '') : ?><a href="<?php echo esc_url($purchase_url); ?>" target="_blank" rel="noopener noreferrer"><?php endif; ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                                <?php if ($purchase_url !== '') : ?></a><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="col <?php echo $has_image ? 'col-md-6' : 'col-md-12'; ?> okticket-veranstaltung__kopfinhalt">
                        <?php if (in_array('api_name', $fields, true)) : ?><h2 class="okticket-veranstaltung__titel">
                            <?php if ($purchase_url !== '') : ?>
                                <a href="<?php echo esc_url($purchase_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($title); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($title); ?>
                            <?php endif; ?>
                        </h2><?php endif; ?>
                        <?php if (in_array('api_kurzbeschreibung', $fields, true) && $description !== '') : ?>
                            <p class="okticket-veranstaltung__beschreibung"><?php echo wp_kses_post($description); ?></p>
                        <?php endif; ?>
                        <?php if (in_array('api_ortnamelang', $fields, true) && $venue !== '') : ?>
                            <p class="okticket-veranstaltung__ort"><?php echo esc_html($venue); ?></p>
                        <?php endif; ?>
                        <?php if ($meta_rows) : ?>
                            <div class="okticket-veranstaltung__meta-grid">
                                <?php foreach ($meta_rows as $meta_row) : ?>
                                    <div class="row">
                                        <div class="col col-md-4"><small><?php echo esc_html($meta_row[0]); ?></small></div>
                                        <div class="col col-md-8"><small><?php echo esc_html($meta_row[1]); ?></small></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (in_array('date_time', $fields, true) && ($date !== '' || $time !== '')) : ?>
                            <p class="okticket-veranstaltung__meta">
                                <span aria-hidden="true">📅</span>
                                <?php echo esc_html($date_label . ($time !== '' ? ', ' . $time . ' Uhr' : '')); ?>
                            </p>
                        <?php endif; ?>
                        </div>
                        </div>
                    </header>
                    <div class="okticket-veranstaltung__inhalt">
                        <?php if (in_array('api_langbeschreibung', $fields, true) && $long_description !== '') : ?>
                            <div class="row okticket-veranstaltung__langbeschreibung-row">
                                <div class="col">
                                    <div class="okticket-veranstaltung__langbeschreibung"><?php echo wp_kses_post($long_description); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($additional_fields) : ?>
                            <div class="okticket-veranstaltung__meta-grid">
                                <?php foreach ($additional_fields as $additional_field) : ?>
                                    <div class="row">
                                        <div class="col col-md-4"><small><?php echo esc_html($additional_field[0]); ?></small></div>
                                        <div class="col col-md-8"><small><?php echo esc_html($additional_field[1]); ?></small></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (in_array('appointments', $fields, true) && $appointments) : ?>
                            <div class="okticket-veranstaltung__termine">
                                <h4>Termine</h4>
                                <ul>
                                    <?php foreach ($appointments as $appointment) : ?>
                                        <?php
                                        $appointment_details = $appointment['details'];
                                        $appointment_event = $appointment['event'];
                                        $appointment_date = trim((string) ($appointment_details['Datum'] ?? ''));
                                        $appointment_time = trim((string) ($appointment_details['Zeit'] ?? ''));
                                        $appointment_time = preg_replace('/^(\d{1,2}:\d{2})(?::\d{2})?$/', '$1', $appointment_time) ?? $appointment_time;
                                        $appointment_url = (string) ($appointment_details['DetailundKaufUrl'] ?? '');
                                        $appointment_date = $appointment_date !== '' ? $appointment_date : (string) ($appointment_event['datum'] ?? '');
                                        $appointment_timestamp = strtotime($appointment_date);
                                        $appointment_label = $appointment_timestamp
                                            ? wp_date('l, j. F Y', $appointment_timestamp)
                                            : $appointment_date;
                                        $traffic_light = self::get_traffic_light(
                                            (int) ($appointment_event['free'] ?? 0),
                                            (int) ($appointment_event['total'] ?? 0)
                                        );
                                        ?>
                                        <?php
                                        $appointment_href = $appointment_url !== '' ? $appointment_url : $purchase_url;
                                        $appointment_date_long = $appointment_timestamp ? wp_date('l, j. F Y', $appointment_timestamp) : $appointment_date;
                                        ?>
                                        <li class="okticket-veranstaltung__termin">
                                            <a class="okticket-veranstaltung__date" href="<?php echo esc_url($appointment_href); ?>" target="_blank" rel="noopener noreferrer">
                                                <div class="row okticket-veranstaltung__date-row">
                                                    <div class="col col-5 okticket-veranstaltung__date-full"><?php echo esc_html($appointment_date_long); ?></div>
                                                    <div class="col col-1 okticket-veranstaltung__date-time-column">
                                                        <?php if ($appointment_time !== "") : ?><small class="okticket-veranstaltung__date-time"><?php echo esc_html($appointment_time); ?> Uhr</small><?php endif; ?>
                                                    </div>
                                                    <div class="col col-5 okticket-veranstaltung__date-status <?php echo esc_attr($traffic_light["class"]); ?>" align="right">
                                                        <span class="okticket-veranstaltung__status-dot" aria-hidden="true">●</span>
                                                        <span><?php echo esc_html($traffic_light["label"]); ?></span>
                                                    </div>
                                                    <div class="col col-1 okticket-veranstaltung__date-chevron-column"><span class="okticket-veranstaltung__date-chevron" aria-hidden="true">›</span></div>
                                                </div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (in_array('ticket_link', $fields, true) && $purchase_url !== '') : ?>
                            <p><a class="okticket-veranstaltung__button" href="<?php echo esc_url($purchase_url); ?>" target="_blank" rel="noopener noreferrer">Tickets kaufen</a></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_events_overview_shortcode(array $atts = []): string {
        if (self::get_token() === '') {
            return '';
        }

        $preview_config = isset($atts['preview_config']) && is_array($atts['preview_config']) ? $atts['preview_config'] : null;
        $atts = shortcode_atts(['id' => ''], $atts, 'okticket_veranstaltungsuebersicht');
        $config_id = sanitize_key((string) $atts['id']);
        $config = $preview_config ?? ($config_id !== '' ? (self::get_overview_shortcode_configs()[$config_id] ?? null) : null);

        if ($config_id !== '' && !is_array($config)) {
            return '';
        }

        $fields = is_array($config) ? $config['fields'] : self::overview_field_keys();
        if (is_array($config) && !isset($config['version']) && !in_array('appointments', $fields, true)) {
            $fields[] = 'appointments';
        }
        $selected_ids = is_array($config) ? array_map('absint', $config['event_ids']) : [];
        $result = self::get_events();

        if (($result['status'] ?? 0) !== 200 || empty($result['events'])) {
            return '';
        }

        $overview_events = [];

        foreach ($result['events'] as $event) {
            $event_id = (int) ($event['event_id'] ?? 0);
            $details = self::get_event_details($event_id);
            $parent_id = (int) ($details['ParentID'] ?? 0);
            $parent_id = $parent_id > 0 ? $parent_id : $event_id;

            if (is_array($config) && !in_array($parent_id, $selected_ids, true)) {
                continue;
            }

            if ($parent_id <= 0) {
                continue;
            }

            if (!isset($overview_events[$parent_id])) {
                $overview_events[$parent_id] = [
                    'details' => $parent_id === $event_id ? $details : self::get_event_details($parent_id),
                    'event' => $event,
                    'appointments' => [],
                ];
            }

            $overview_events[$parent_id]['appointments'][] = [
                'details' => $details,
                'event' => $event,
            ];
        }

        if (!$overview_events) {
            return '';
        }

        uasort($overview_events, static function (array $a, array $b): int {
            $a_date = strtotime((string) ($a['details']['Datum'] ?? $a['event']['datum'] ?? '')) ?: PHP_INT_MAX;
            $b_date = strtotime((string) ($b['details']['Datum'] ?? $b['event']['datum'] ?? '')) ?: PHP_INT_MAX;
            return $a_date <=> $b_date;
        });

        ob_start();
        ?>
        <div class="okticket-bootstrap">
            <div class="okticket-veranstaltungsuebersicht">
                <?php foreach ($overview_events as $overview_event) : ?>
                    <?php
                    $details = $overview_event['details'];
                    $event = $overview_event['event'];
                    $title = (string) ($details['Titel'] ?? $event['name'] ?? 'Veranstaltung');
                    $url = (string) ($details['DetailundKaufUrl'] ?? '');
                    $image_url = (string) ($details['BildUrl'] ?? '');
                    $date = trim((string) ($details['Datum'] ?? $event['datum'] ?? ''));
                    $timestamp = strtotime($date);
                    $date_label = $timestamp ? wp_date('D, j. F Y', $timestamp) : $date;
                    $time = trim((string) ($details['Zeit'] ?? ''));
                    $time = preg_replace('/^(\d{1,2}:\d{2})(?::\d{2})?$/', '$1', $time) ?? $time;
                    $venue = trim((string) ($details['OrtNameLang'] ?? $details['OrtName'] ?? $event['ort'] ?? ''));
                    $description = wp_strip_all_tags((string) ($details['KurzBeschreibung'] ?? ''));
                    $has_image = in_array('image', $fields, true) && $image_url !== '';
                    $appointments = $overview_event['appointments'];
                    usort($appointments, static function (array $a, array $b): int {
                        $a_date = strtotime((string) ($a['details']['Datum'] ?? $a['event']['datum'] ?? '')) ?: PHP_INT_MAX;
                        $b_date = strtotime((string) ($b['details']['Datum'] ?? $b['event']['datum'] ?? '')) ?: PHP_INT_MAX;
                        return $a_date <=> $b_date;
                    });
                    ?>
                    <article class="okticket-veranstaltungsuebersicht__karte searchItem">
                        <?php if ($url !== '') : ?><a class="okticket-veranstaltungsuebersicht__link <?php echo $has_image ? '' : 'okticket-veranstaltungsuebersicht__link--ohne-bild'; ?>" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php endif; ?>
                            <div class="okticket-veranstaltungsuebersicht__kopf <?php echo $has_image ? '' : 'okticket-veranstaltungsuebersicht__kopf--ohne-bild'; ?>">
                                <?php if ($has_image) : ?><img class="okticket-veranstaltungsuebersicht__bild" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy"><?php endif; ?>
                                <div class="okticket-veranstaltungsuebersicht__inhalt">
                                    <?php if (in_array('title', $fields, true)) : ?><h3 class="okticket-veranstaltungsuebersicht__titel"><?php echo esc_html($title); ?></h3><?php endif; ?>
                                    <?php if (in_array('venue', $fields, true) && $venue !== '') : ?><p class="okticket-veranstaltungsuebersicht__ort"><?php echo esc_html($venue); ?></p><?php endif; ?>
                                    <?php if (in_array('description', $fields, true) && $description !== '') : ?><p class="okticket-veranstaltungsuebersicht__beschreibung"><?php echo esc_html($description); ?></p><?php endif; ?>
                                </div>
                            </div>
                            <?php if (in_array('appointments', $fields, true) && $appointments) : ?><div class="okticket-veranstaltungsuebersicht__termine"><p class="okticket-veranstaltungsuebersicht__termine-titel">Nächste Termine</p><ul class="okticket-veranstaltungsuebersicht__termine-liste"><?php foreach (array_slice($appointments, 0, 3) as $appointment) : ?><?php $appointment_date = (string) ($appointment['details']['Datum'] ?? $appointment['event']['datum'] ?? ''); $appointment_time = trim((string) ($appointment['details']['Zeit'] ?? '')); $appointment_time = preg_replace('/^(\d{1,2}:\d{2})(?::\d{2})?$/', '$1', $appointment_time) ?? $appointment_time; $appointment_timestamp = strtotime($appointment_date); ?><li class="okticket-veranstaltungsuebersicht__termin"><?php echo esc_html(($appointment_timestamp ? wp_date('D, j. M.', $appointment_timestamp) : $appointment_date) . ($appointment_time !== '' ? ' · ' . $appointment_time . ' Uhr' : '')); ?></li><?php endforeach; ?><?php if (count($appointments) > 3) : ?><li class="okticket-veranstaltungsuebersicht__termin okticket-veranstaltungsuebersicht__weitere">+ <?php echo esc_html((string) (count($appointments) - 3)); ?> weitere Termine</li><?php endif; ?></ul></div><?php endif; ?>
                        <?php if ($url !== '') : ?></a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function field_keys(): array {
        return ['image', 'api_name', 'api_kurzbeschreibung', 'api_langbeschreibung', 'api_ortnamelang', 'api_titel', 'appointments'];
    }

    private static function overview_field_keys(): array {
        return ['image', 'title', 'venue', 'description', 'appointments'];
    }

    private static function overview_field_labels(): array {
        return [
            'image' => 'Bild',
            'title' => 'Titel',
            'venue' => 'Veranstaltungsort',
            'description' => 'Kurzbeschreibung',
            'appointments' => 'Nächste Termine',
        ];
    }

    private static function field_labels(): array {
        return [
            'image' => 'Bild anzeigen',
            'api_name' => 'Name',
            'api_kurzbeschreibung' => 'KurzBeschreibung',
            'api_langbeschreibung' => 'LangBeschreibung',
            'api_ortnamelang' => 'OrtNameLang',
            'api_titel' => 'Titel',
            'appointments' => 'Termine',
        ];
    }

    private static function event_api_field_map(): array {
        return [
            'api_name' => 'Name',
            'api_kurzbeschreibung' => 'KurzBeschreibung',
            'api_langbeschreibung' => 'LangBeschreibung',
            'api_ortnamelang' => 'OrtNameLang',
            'api_titel' => 'Titel',
        ];
    }

    private static function format_event_api_value($value): string {
        if (is_bool($value)) {
            return $value ? 'Ja' : 'Nein';
        }

        if (is_array($value)) {
            $encoded = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function get_shortcode_configs(): array {
        $configs = get_option(self::OPTION_SHORTCODES, []);
        return is_array($configs) ? $configs : [];
    }

    private static function get_overview_shortcode_configs(): array {
        $configs = get_option(self::OPTION_OVERVIEW_SHORTCODES, []);
        return is_array($configs) ? $configs : [];
    }

    private static function get_preview_overview_config(): ?array {
        if (!isset($_POST['preview_config']) || !is_array($_POST['preview_config'])) {
            return null;
        }

        $submitted = wp_unslash($_POST['preview_config']);
        $name = isset($submitted['shortcode_name']) ? sanitize_text_field($submitted['shortcode_name']) : '';
        $event_ids = isset($submitted['event_ids']) && is_array($submitted['event_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', $submitted['event_ids']))))
            : [];
        $fields = isset($submitted['fields']) && is_array($submitted['fields'])
            ? array_values(array_intersect(self::overview_field_keys(), array_map('sanitize_key', $submitted['fields'])))
            : [];

        return [
            'name' => $name,
            'event_ids' => $event_ids,
            'fields' => $fields,
            'version' => 2,
        ];
    }

    private static function get_preview_shortcode_config(): ?array {
        if (!isset($_POST['preview_config']) || !is_array($_POST['preview_config'])) {
            return null;
        }

        $submitted = wp_unslash($_POST['preview_config']);
        $name = isset($submitted['shortcode_name']) ? sanitize_text_field($submitted['shortcode_name']) : '';
        $event_ids = isset($submitted['event_ids']) && is_array($submitted['event_ids'])
            ? array_slice(array_values(array_unique(array_filter(array_map('absint', $submitted['event_ids'])))), 0, 1)
            : [];
        $fields = isset($submitted['fields']) && is_array($submitted['fields'])
            ? array_values(array_intersect(self::field_keys(), array_map('sanitize_key', $submitted['fields'])))
            : [];

        return [
            'name' => $name,
            'event_ids' => $event_ids,
            'fields' => $fields,
        ];
    }

    private static function render_shortcode_manager(?array $result): void {
        $events = is_array($result) && ($result['status'] ?? 0) === 200 ? ($result['events'] ?? []) : [];
        $labels = self::field_labels();
        $configs = self::get_shortcode_configs();
        $editing_id = isset($_GET['edit_shortcode']) ? sanitize_key(wp_unslash($_GET['edit_shortcode'])) : '';
        $editing_config = $editing_id !== '' ? ($configs[$editing_id] ?? null) : null;
        $selected_event_ids = is_array($editing_config) ? array_slice(array_map('absint', $editing_config['event_ids'] ?? []), 0, 1) : [];
        $selected_event_id = (int) ($selected_event_ids[0] ?? 0);
        $selected_fields = is_array($editing_config) ? ($editing_config['fields'] ?? []) : array_keys($labels);
        $parent_events = [];

        foreach ($events as $event) {
            $event_id = (int) ($event['event_id'] ?? 0);
            if ($event_id > 0) {
                $details = self::get_event_details($event_id);
                $parent_id = (int) ($details['ParentID'] ?? 0);
                $parent_id = $parent_id > 0 ? $parent_id : $event_id;

                if ($event_id === $selected_event_id) {
                    $selected_event_id = $parent_id;
                }

                if (!isset($parent_events[$parent_id])) {
                    $parent_events[$parent_id] = $parent_id === $event_id ? $details : self::get_event_details($parent_id);
                }
            }
        }
        $preview_id = is_array($editing_config) ? $editing_id : 'new';
        $preview_url = wp_nonce_url(
            admin_url('admin-post.php?action=altmuehlbuehne_okticket_preview_event_page&event_page_id=' . rawurlencode($preview_id)),
            'altmuehlbuehne_okticket_preview_event_page_' . $preview_id
        );
        ?>
        <hr>
        <h2>Veranstaltungsdetails</h2>
        <p><?php echo is_array($editing_config) ? 'Bearbeite die ausgewählte Veranstaltung.' : 'Lege für genau eine Veranstaltung einen Shortcode an. Den erzeugten Shortcode kannst du anschließend in einer Seite oder einem Beitrag verwenden.'; ?></p>

        <?php if (!$events) : ?>
            <p>Zum Anlegen eines Shortcodes müssen zuerst Veranstaltungen über die API geladen werden.</p>
            <?php return; ?>
        <?php endif; ?>

        <div class="okticket-overview-editor">
            <div class="okticket-overview-editor-form">
        <form id="okticket-details-editor-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="altmuehlbuehne_okticket_save_shortcode">
            <?php if (is_array($editing_config)) : ?><input type="hidden" name="shortcode_id" value="<?php echo esc_attr($editing_id); ?>"><?php endif; ?>
            <?php wp_nonce_field('altmuehlbuehne_okticket_save_shortcode'); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="okticket_shortcode_name">Name der Veranstaltungsdetails</label></th><td><input class="regular-text" id="okticket_shortcode_name" name="shortcode_name" value="<?php echo esc_attr((string) ($editing_config['name'] ?? '')); ?>" required></td></tr>
                <tr><th scope="row"><label for="okticket_shortcode_event">Veranstaltung</label></th><td><select class="regular-text" id="okticket_shortcode_event" name="event_ids[]" required><option value="">Veranstaltung wählen</option><?php foreach ($parent_events as $parent_id => $parent_event) : ?><option value="<?php echo esc_attr((string) $parent_id); ?>" <?php selected((int) $parent_id, $selected_event_id); ?>><?php echo esc_html((string) ($parent_event['Titel'] ?? 'Veranstaltung')); ?></option><?php endforeach; ?></select></td></tr>
                <tr><th scope="row">Anzuzeigende Felder</th><td><?php foreach ($labels as $key => $label) : ?><label style="display:block;margin-bottom:4px"><input type="checkbox" name="fields[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_fields, true)); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></td></tr>
            </table>
            <?php submit_button(is_array($editing_config) ? 'Shortcode aktualisieren' : 'Shortcode anlegen'); ?>
            <?php if (is_array($editing_config)) : ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dashboard&tab=details')); ?>">Abbrechen</a><?php endif; ?>
        </form>
            </div>
            <aside class="okticket-overview-preview">
                <h2>Live-Vorschau</h2>
                <iframe id="okticket-details-preview" name="okticket-details-preview" data-preview-url="<?php echo esc_url($preview_url); ?>" src="<?php echo esc_url($preview_url); ?>" title="Vorschau der Veranstaltungsdetails"></iframe>
                <a class="button" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer">In neuem Tab öffnen</a>
            </aside>
        </div>
        <script>
        (function () {
            var editor = document.getElementById('okticket-details-editor-form');
            var preview = document.getElementById('okticket-details-preview');
            if (!editor || !preview || !preview.dataset.previewUrl) { return; }

            var updatePreview = function () {
                var request = document.createElement('form');
                request.method = 'post';
                request.action = preview.dataset.previewUrl;
                request.target = preview.name;
                request.style.display = 'none';
                var append = function (name, value) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    request.appendChild(input);
                };
                var name = editor.querySelector('[name="shortcode_name"]');
                append('preview_config[shortcode_name]', name ? name.value : '');
                var selectedEvent = editor.querySelector('[name="event_ids[]"]');
                if (selectedEvent && selectedEvent.value) {
                    append('preview_config[event_ids][]', selectedEvent.value);
                }
                editor.querySelectorAll('[name="fields[]"]:checked').forEach(function (input) {
                    append('preview_config[fields][]', input.value);
                });
                document.body.appendChild(request);
                request.submit();
                request.remove();
            };

            editor.addEventListener('input', updatePreview);
            editor.addEventListener('change', updatePreview);
            updatePreview();
        }());
        </script>

        <?php if ($configs) : ?>
            <h3>Bestehende Shortcodes</h3>
            <table class="widefat striped"><thead><tr><th>Name</th><th>Shortcode</th><th>Veranstaltungsreihe</th><th>Felder</th><th></th></tr></thead><tbody>
            <?php foreach ($configs as $id => $config) : ?><?php $shortcode = '[okticket_veranstaltungsliste id="' . $id . '"]'; $event_id = (int) (($config['event_ids'][0] ?? 0)); $preview_url = wp_nonce_url(admin_url('admin-post.php?action=altmuehlbuehne_okticket_preview_event_page&event_page_id=' . rawurlencode($id)), 'altmuehlbuehne_okticket_preview_event_page_' . $id); ?><tr><td><?php echo esc_html((string) ($config['name'] ?? $id)); ?></td><td><code><?php echo esc_html($shortcode); ?></code> <button type="button" class="button button-small okticket-copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>">Kopieren</button></td><td><?php echo $event_id > 0 ? esc_html((string) $event_id) : '–'; ?></td><td><?php echo esc_html(implode(', ', array_intersect_key($labels, array_flip($config['fields'] ?? [])))); ?></td><td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=dashboard&tab=details&edit_shortcode=' . rawurlencode($id))); ?>">Bearbeiten</a> <a class="button button-small" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr('Vorschau für ' . (string) ($config['name'] ?? $id)); ?>"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></a> <form style="display:inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="altmuehlbuehne_okticket_delete_shortcode"><input type="hidden" name="shortcode_id" value="<?php echo esc_attr($id); ?>"><?php wp_nonce_field('altmuehlbuehne_okticket_delete_shortcode'); ?><button class="button-link-delete" type="submit">Löschen</button></form></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
        <?php
    }

    private static function render_overview_shortcode_manager(?array $result): void {
        $events = is_array($result) && ($result['status'] ?? 0) === 200 ? ($result['events'] ?? []) : [];
        $labels = self::overview_field_labels();
        $configs = self::get_overview_shortcode_configs();
        $editing_id = isset($_GET['edit_shortcode']) ? sanitize_key(wp_unslash($_GET['edit_shortcode'])) : '';
        $editing_config = $editing_id !== '' ? ($configs[$editing_id] ?? null) : null;
        $selected_event_ids = is_array($editing_config) ? array_map('absint', $editing_config['event_ids'] ?? []) : [];
        $selected_fields = is_array($editing_config) ? ($editing_config['fields'] ?? []) : array_keys($labels);
        $parent_events = [];

        foreach ($events as $event) {
            $event_id = (int) ($event['event_id'] ?? 0);
            $details = self::get_event_details($event_id);
            $parent_id = (int) ($details['ParentID'] ?? 0);
            $parent_id = $parent_id > 0 ? $parent_id : $event_id;

            if (!isset($parent_events[$parent_id])) {
                $parent_events[$parent_id] = $parent_id === $event_id
                    ? $details
                    : self::get_event_details($parent_id);
            }
        }

        $preview_id = is_array($editing_config) ? $editing_id : 'new';
        $preview_url = wp_nonce_url(
            admin_url('admin-post.php?action=altmuehlbuehne_okticket_preview_overview&overview_id=' . rawurlencode($preview_id)),
            'altmuehlbuehne_okticket_preview_overview_' . $preview_id
        );
        ?>
        <hr>
        <p><?php echo is_array($editing_config) ? 'Bearbeite die ausgewählte Veranstaltungsübersicht.' : 'Lege die Veranstaltungsübersicht an und wähle die enthaltenen Veranstaltungsreihen sowie die anzuzeigenden Felder. Jede Karte verlinkt auf die bestehende OKTicket-Detailseite der Veranstaltung.'; ?></p>

        <?php if (!$events) : ?>
            <p>Zum Anlegen einer Übersicht müssen zuerst Veranstaltungen über die API geladen werden.</p>
            <?php return; ?>
        <?php endif; ?>

        <div class="okticket-overview-editor">
            <div class="okticket-overview-editor-form">
        <form id="okticket-overview-editor-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="altmuehlbuehne_okticket_save_overview_shortcode">
            <?php if (is_array($editing_config)) : ?><input type="hidden" name="shortcode_id" value="<?php echo esc_attr($editing_id); ?>"><?php endif; ?>
            <?php wp_nonce_field('altmuehlbuehne_okticket_save_overview_shortcode'); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="okticket_overview_shortcode_name">Name der Veranstaltungsübersicht</label></th><td><input class="regular-text" id="okticket_overview_shortcode_name" name="shortcode_name" value="<?php echo esc_attr((string) ($editing_config['name'] ?? '')); ?>" required></td></tr>
                <tr><th scope="row">Veranstaltungsreihen<p class="description">Welche Veranstaltungen sollen in der Liste angezeigt werden?</p></th><td><?php foreach ($parent_events as $parent_id => $event) : ?><label style="display:block;margin-bottom:4px"><input type="checkbox" name="event_ids[]" value="<?php echo esc_attr((string) $parent_id); ?>" <?php checked(in_array((int) $parent_id, $selected_event_ids, true)); ?>> <?php echo esc_html((string) ($event['Titel'] ?? 'Veranstaltung')); ?><?php $series_date = self::format_event_date((string) ($event['Datum'] ?? '')); ?><?php if ($series_date !== '') : ?> <span class="description">(<?php echo esc_html($series_date); ?>)</span><?php endif; ?></label><?php endforeach; ?></td></tr>
                <tr><th scope="row">Anzuzeigende Felder</th><td><?php foreach ($labels as $key => $label) : ?><label style="display:block;margin-bottom:4px"><input type="checkbox" name="fields[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_fields, true)); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></td></tr>
            </table>
            <?php submit_button(is_array($editing_config) ? 'Übersicht aktualisieren' : 'Übersicht anlegen'); ?>
            <?php if (is_array($editing_config)) : ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dashboard&tab=overview')); ?>">Abbrechen</a><?php endif; ?>
        </form>
            </div>

            <aside class="okticket-overview-preview">
                <h2>Live-Vorschau</h2>
                <iframe id="okticket-overview-preview" name="okticket-overview-preview" data-preview-url="<?php echo esc_url($preview_url); ?>" src="<?php echo esc_url($preview_url); ?>" title="Vorschau der Veranstaltungsübersicht"></iframe>
                <a class="button" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer">In neuem Tab öffnen</a>
            </aside>
        </div>
        <script>
        (function () {
            var editor = document.getElementById('okticket-overview-editor-form');
            var preview = document.getElementById('okticket-overview-preview');
            if (!editor || !preview || !preview.dataset.previewUrl) {
                return;
            }

            var updatePreview = function () {
                var request = document.createElement('form');
                request.method = 'post';
                request.action = preview.dataset.previewUrl;
                request.target = preview.name;
                request.style.display = 'none';

                var append = function (name, value) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    request.appendChild(input);
                };

                var name = editor.querySelector('[name="shortcode_name"]');
                append('preview_config[shortcode_name]', name ? name.value : '');
                editor.querySelectorAll('[name="event_ids[]"]:checked').forEach(function (input) {
                    append('preview_config[event_ids][]', input.value);
                });
                editor.querySelectorAll('[name="fields[]"]:checked').forEach(function (input) {
                    append('preview_config[fields][]', input.value);
                });

                document.body.appendChild(request);
                request.submit();
                request.remove();
            };

            editor.addEventListener('input', updatePreview);
            editor.addEventListener('change', updatePreview);
            updatePreview();
        }());
        </script>

        <?php if ($configs) : ?>
            <h3>Gespeicherte Veranstaltungsübersichten</h3>
            <table class="widefat striped"><thead><tr><th>Name</th><th>Shortcode</th><th>Veranstaltungen</th><th>Felder</th><th></th></tr></thead><tbody>
            <?php foreach ($configs as $id => $config) : ?><?php $shortcode = '[okticket_veranstaltungsuebersicht id="' . $id . '"]'; $preview_url = wp_nonce_url(admin_url('admin-post.php?action=altmuehlbuehne_okticket_preview_overview&overview_id=' . rawurlencode($id)), 'altmuehlbuehne_okticket_preview_overview_' . $id); ?><tr><td><?php echo esc_html((string) ($config['name'] ?? $id)); ?></td><td><code><?php echo esc_html($shortcode); ?></code> <button type="button" class="button button-small okticket-copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>">Kopieren</button></td><td><?php echo esc_html((string) count($config['event_ids'] ?? [])); ?></td><td><?php echo esc_html(implode(', ', array_intersect_key($labels, array_flip($config['fields'] ?? [])))); ?></td><td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=dashboard&tab=overview&edit_shortcode=' . rawurlencode($id))); ?>">Bearbeiten</a> <a class="button button-small" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr('Vorschau für ' . (string) ($config['name'] ?? $id)); ?>"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></a> <form style="display:inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="altmuehlbuehne_okticket_delete_overview_shortcode"><input type="hidden" name="shortcode_id" value="<?php echo esc_attr($id); ?>"><?php wp_nonce_field('altmuehlbuehne_okticket_delete_overview_shortcode'); ?><button class="button-link-delete" type="submit">Löschen</button></form></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
        <?php
    }

    private static function render_events_table(?array $result): void {
        if ($result === null) {
            return;
        }

        if (($result['status'] ?? 0) !== 200) {
            echo '<hr><h2>Veranstaltungen</h2>';
            echo '<div class="notice notice-error inline"><p>Die Veranstaltungen konnten nicht geladen werden. API-Status: ';
            echo esc_html((string) ($result['status'] ?? 0));
            echo '</p></div>';
            return;
        }

        $events = $result['events'] ?? [];
        $grouped_events = [];

        foreach ($events as $event) {
            $event_id = (int) ($event['event_id'] ?? 0);
            $child_details = self::get_event_details($event_id);
            $parent_id = (int) ($child_details['ParentID'] ?? 0);
            $parent_id = $parent_id > 0 ? $parent_id : $event_id;

            if (!isset($grouped_events[$parent_id])) {
                $parent_details = $parent_id === $event_id
                    ? $child_details
                    : self::get_event_details($parent_id);
                $grouped_events[$parent_id] = [
                    'event_id' => $parent_id,
                    'name' => (string) ($parent_details['Titel'] ?? $event['name'] ?? ''),
                    'ort' => (string) ($parent_details['OrtNameLang'] ?? $event['ort'] ?? ''),
                    'datum' => (string) ($parent_details['Datum'] ?? $event['datum'] ?? ''),
                    'sold' => 0,
                    'free' => 0,
                    'blocked' => 0,
                    'total' => 0,
                    'appointments' => [],
                    '_parent_details' => $parent_details,
                ];
            }

            foreach (['sold', 'free', 'blocked', 'total'] as $stat) {
                $grouped_events[$parent_id][$stat] += (int) ($event[$stat] ?? 0);
            }

            $grouped_events[$parent_id]['appointments'][] = [
                'event' => $event,
                'details' => $child_details,
            ];
        }

        $events = array_values($grouped_events);
        ?>
        <hr>
        <h2>Veranstaltungen</h2>
        <p><?php echo esc_html((string) count($events)); ?> Veranstaltungsreihen. Termine derselben Reihe werden zusammengefasst; die Werte für verkauft, frei und gesamt sind summiert.</p>

        <?php if (!$events) : ?>
            <p>OKTicket liefert aktuell keine Veranstaltungen.</p>
            <?php return; ?>
        <?php endif; ?>

        <input class="regular-text okticket-event-search" id="okticket-event-search" type="search" placeholder="Nach Titel, Ort oder ID suchen" aria-label="Veranstaltungen durchsuchen">
        <table class="widefat striped" id="okticket-events-table">
            <thead>
                <tr>
                    <th>Bild</th>
                    <th>Veranstaltung</th>
                    <th>Termin</th>
                    <th>Ort</th>
                    <th>Verkauft</th>
                    <th>Frei</th>
                    <th>Blockiert</th>
                    <th>Gesamt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event) : ?>
                    <?php
                    $raw_date = (string) ($event['datum'] ?? '');

                    if (preg_match('~^(\\d{4})-(\\d{2})-(\\d{2})/(\\d{4})-(\\d{2})-(\\d{2})$~', $raw_date, $date_parts)) {
                        $date = $date_parts[3] . '.' . $date_parts[2] . '.' . $date_parts[1] . ' – ' . $date_parts[6] . '.' . $date_parts[5] . '.' . $date_parts[4];
                    } else {
                        $timestamp = strtotime($raw_date);
                        $date = $timestamp ? wp_date('d.m.Y \u\m H:i \U\h\r', $timestamp) : ($raw_date !== '' ? $raw_date : '–');
                    }
                    $event_id = (int) ($event['event_id'] ?? 0);
                    $details = is_array($event['_parent_details'] ?? null)
                        ? $event['_parent_details']
                        : self::get_event_details($event_id);
                    $image_url = (string) ($details['BildUrl'] ?? '');
                    $purchase_url = (string) ($details['DetailundKaufUrl'] ?? '');
                    $title = (string) ($details['Titel'] ?? $event['name'] ?? '–');
                    ?>
                    <tr data-okticket-search="<?php echo esc_attr((string) ($event['event_id'] ?? '') . ' ' . $title . ' ' . (string) ($event['ort'] ?? '')); ?>">
                        <td style="width:100px">
                            <?php if ($image_url !== '') : ?>
                                <img
                                    src="<?php echo esc_url($image_url); ?>"
                                    alt=""
                                    loading="lazy"
                                    style="display:block;width:90px;height:60px;object-fit:cover;border-radius:3px"
                                >
                            <?php else : ?>
                                <span aria-label="Kein Bild verfügbar">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($purchase_url !== '') : ?>
                                <a href="<?php echo esc_url($purchase_url); ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo esc_html($title); ?></strong></a>
                            <?php else : ?>
                                <strong><?php echo esc_html($title); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td class="okticket-events-date">
                            <?php $appointments = is_array($event['appointments'] ?? null) ? $event['appointments'] : []; ?>
                            <?php if (count($appointments) > 1) : ?>
                                <details class="okticket-appointments">
                                    <summary><?php echo esc_html((string) count($appointments)); ?> Termine anzeigen</summary>
                                    <table>
                                        <thead>
                                            <tr><th>Termin</th><th>Frei</th><th>Verkauft</th><th>Blockiert</th><th>Gesamt</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $appointment) : ?>
                                                <?php
                                                $appointment_event = is_array($appointment['event'] ?? null) ? $appointment['event'] : [];
                                                $appointment_details = is_array($appointment['details'] ?? null) ? $appointment['details'] : [];
                                                $appointment_raw_date = (string) ($appointment_details['Datum'] ?? $appointment_event['datum'] ?? '');
                                                $appointment_timestamp = strtotime($appointment_raw_date);
                                                $appointment_date = $appointment_timestamp ? wp_date('d.m.Y', $appointment_timestamp) : $appointment_raw_date;
                                                $appointment_time = preg_replace('/^(\d{1,2}:\d{2})(?::\d{2})?$/', '$1', (string) ($appointment_details['Zeit'] ?? '')) ?? '';
                                                ?>
                                                <tr>
                                                    <td><?php echo esc_html($appointment_date . ($appointment_time !== '' ? ', ' . $appointment_time . ' Uhr' : '')); ?></td>
                                                    <td><?php echo esc_html((string) ($appointment_event['free'] ?? '–')); ?></td>
                                                    <td><?php echo esc_html((string) ($appointment_event['sold'] ?? '–')); ?></td>
                                                    <td><?php echo esc_html((string) ($appointment_event['blocked'] ?? '–')); ?></td>
                                                    <td><?php echo esc_html((string) ($appointment_event['total'] ?? '–')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </details>
                            <?php else : ?>
                                <?php echo esc_html($date); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) ($event['ort'] ?? '–')); ?></td>
                        <td><?php echo esc_html((string) ($event['sold'] ?? '–')); ?></td>
                        <td><?php echo esc_html((string) ($event['free'] ?? '–')); ?></td>
                        <td><?php echo esc_html((string) ($event['blocked'] ?? '–')); ?></td>
                        <td><?php echo esc_html((string) ($event['total'] ?? '–')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <script>
            (function () {
                var input = document.getElementById('okticket-event-search');
                var table = document.getElementById('okticket-events-table');
                if (!input || !table) { return; }
                input.addEventListener('input', function () {
                    var needle = input.value.toLocaleLowerCase();
                    table.querySelectorAll('tbody tr').forEach(function (row) {
                        row.hidden = needle !== '' && row.dataset.okticketSearch.toLocaleLowerCase().indexOf(needle) === -1;
                    });
                });
            }());
        </script>
        <?php
    }

    private static function get_event_details(int $event_id): array {
        if ($event_id <= 0) {
            return [];
        }

        $details_snapshot = get_option(self::OPTION_EVENT_DETAILS_SNAPSHOT, []);
        $details = is_array($details_snapshot) ? ($details_snapshot[$event_id] ?? []) : [];

        return is_array($details) ? $details : [];
    }

    private static function format_event_date(string $raw_date): string {
        if (preg_match('~^(\\d{4})-(\\d{2})-(\\d{2})/(\\d{4})-(\\d{2})-(\\d{2})$~', $raw_date, $parts)) {
            return $parts[3] . '.' . $parts[2] . '.' . $parts[1] . ' – ' . $parts[6] . '.' . $parts[5] . '.' . $parts[4];
        }

        $timestamp = strtotime($raw_date);
        return $timestamp ? wp_date('d.m.Y', $timestamp) : $raw_date;
    }

    private static function get_traffic_light(int $free, int $total): array {
        $percent = $total > 0 ? ($free / $total) * 100 : 0.0;

        if ($percent > 10) {
            return ["class" => "okticket-veranstaltung__status--available", "label" => "Tickets verfügbar"];
        }

        if ($percent > 3) {
            return ["class" => "okticket-veranstaltung__status--few", "label" => "Wenige Plätze"];
        }

        if ($free > 0) {
            return ["class" => "okticket-veranstaltung__status--critical", "label" => "Fast ausverkauft"];
        }

        return ["class" => "okticket-veranstaltung__status--sold-out", "label" => "Ausverkauft"];
    }

    private static function redirect_with_notice(string $notice, string $tab = 'pages'): void {
        $url = add_query_arg(
            [
                'page' => 'okticket-settings',
                'okticket_notice' => $notice,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}

Altmuehlbuehne_OKTicket_Veranstaltungsliste::init();
register_deactivation_hook(__FILE__, [Altmuehlbuehne_OKTicket_Veranstaltungsliste::class, 'deactivate']);
