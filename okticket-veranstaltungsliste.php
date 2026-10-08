<?php
/**
 * Plugin Name: OKTicket Veranstaltungsliste
 * Description: Ruft Veranstaltungsdaten lesend aus der OKTicket-API ab.
 * Version: 0.2.3
 * Author: okticket.de
 * Update URI: https://github.com/rieger-software/okticket-veranstaltungsliste/
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: okticket-veranstaltungsliste
 */

defined('ABSPATH') || exit;


require_once __DIR__ . '/includes/trait-okticket-cron.php';
require_once __DIR__ . '/includes/trait-okticket-blocks.php';
require_once __DIR__ . '/includes/trait-okticket-update-client.php';
require_once __DIR__ . '/includes/trait-okticket-admin.php';
require_once __DIR__ . '/includes/trait-okticket-sync.php';
require_once __DIR__ . '/includes/trait-okticket-frontend.php';

final class Altmuehlbuehne_OKTicket_Veranstaltungsliste {
    use Altmuehlbuehne_OKTicket_Cron_Trait;
    use Altmuehlbuehne_OKTicket_Blocks_Trait;
    use Altmuehlbuehne_OKTicket_Update_Client_Trait;
    use Altmuehlbuehne_OKTicket_Admin_Trait;
    use Altmuehlbuehne_OKTicket_Sync_Trait;
    use Altmuehlbuehne_OKTicket_Frontend_Trait;

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
}

Altmuehlbuehne_OKTicket_Veranstaltungsliste::init();
register_deactivation_hook(__FILE__, [Altmuehlbuehne_OKTicket_Veranstaltungsliste::class, 'deactivate']);
