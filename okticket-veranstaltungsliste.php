<?php
/**
 * Plugin Name: OKTicket Veranstaltungsliste
 * Description: Ruft Veranstaltungsdaten lesend aus der OKTicket-API ab.
 * Version: 0.2.5
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
    private const OPTION_VVKS_SNAPSHOT = 'altmuehlbuehne_okticket_vvks_snapshot';
    private const OPTION_VVKS_BY_PLZ_SNAPSHOT = 'altmuehlbuehne_okticket_vvks_by_plz_snapshot';
    private const OPTION_VVKS_LAST_SYNC = 'altmuehlbuehne_okticket_vvks_last_sync';
    private const API_URL = 'https://api.okticket.de/statistik/live/events';
    private const EVENT_DETAILS_URL = 'https://api.okticket.de/statistik/live/eventDetails/';
    private const VVKS_URL = 'https://api.okticket.de/statistik/live/vvks';
    private const UPDATE_URI = 'https://github.com/rieger-software/okticket-veranstaltungsliste/';
    private const GITHUB_RELEASE_API_URL = 'https://api.github.com/repos/rieger-software/okticket-veranstaltungsliste/releases/latest';
    private const PLUGIN_FILE = __FILE__;
    private const CRON_HOOK = 'altmuehlbuehne_okticket_sync_events';
    private const CRON_SCHEDULE = 'altmuehlbuehne_okticket_every_fifteen_minutes';
    private const VVKS_CRON_HOOK = 'altmuehlbuehne_okticket_sync_vvks';

    public static function init(): void {
        add_filter('cron_schedules', [self::class, 'add_cron_schedule']);
        add_action('init', [self::class, 'ensure_scheduled_sync']);
        add_action(self::CRON_HOOK, [self::class, 'run_scheduled_sync']);
        add_action(self::VVKS_CRON_HOOK, [self::class, 'run_scheduled_vvks_sync']);
        add_action('init', [self::class, 'register_overview_block']);
        add_action('init', [self::class, 'register_details_block']);
        add_action('init', [self::class, 'register_appointments_widget_block']);
        add_action('widgets_init', [self::class, 'register_appointments_legacy_widget']);
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

if (class_exists('WP_Widget')) {
    final class Altmuehlbuehne_OKTicket_Appointments_Widget extends WP_Widget {
        public function __construct() {
            parent::__construct(
                'okticket_termine',
                'OKTicket Termine',
                ['description' => 'Zeigt die nächsten Termine einer OKTicket-Veranstaltungsreihe an.']
            );
        }

        public function widget($args, $instance): void {
            $title = apply_filters('widget_title', (string) ($instance['title'] ?? 'Nächste Aufführungen'), $instance, $this->id_base);
            $event_id = absint($instance['event_id'] ?? 0);
            $limit = min(10, max(1, absint($instance['limit'] ?? 5)));
            $show_all = !empty($instance['show_all']);
            $show_availability = !empty($instance['show_availability']);

            if ($event_id <= 0) {
                return;
            }

            $content = Altmuehlbuehne_OKTicket_Veranstaltungsliste::render_appointments_widget_block([
                'eventId' => (string) $event_id,
                'title' => '',
                'limit' => $limit,
                'showAll' => $show_all,
                'showAvailability' => $show_availability,
            ]);
            if ($content === '') {
                return;
            }

            echo $args['before_widget'];
            if ($title !== '') {
                echo $args['before_title'] . esc_html($title) . $args['after_title'];
            }
            echo $content;
            echo $args['after_widget'];
        }

        public function form($instance): void {
            $title = (string) ($instance['title'] ?? 'Nächste Aufführungen');
            $event_id = (string) absint($instance['event_id'] ?? 0);
            $limit = min(10, max(1, absint($instance['limit'] ?? 5)));
            $show_all = !empty($instance['show_all']);
            $show_availability = !empty($instance['show_availability']);
            $series = Altmuehlbuehne_OKTicket_Veranstaltungsliste::get_widget_series();
            ?>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Überschrift</label>
                <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('event_id')); ?>">Veranstaltungsreihe</label>
                <select class="widefat" id="<?php echo esc_attr($this->get_field_id('event_id')); ?>" name="<?php echo esc_attr($this->get_field_name('event_id')); ?>">
                    <option value="">Veranstaltung wählen</option>
                    <?php foreach ($series as $series_id => $series_label) : ?>
                        <option value="<?php echo esc_attr((string) $series_id); ?>" <?php selected($event_id, (string) $series_id); ?>><?php echo esc_html($series_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>">Anzahl Termine</label>
                <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" name="<?php echo esc_attr($this->get_field_name('limit')); ?>" type="number" min="1" max="10" value="<?php echo esc_attr((string) $limit); ?>">
            </p>
            <p>
                <input id="<?php echo esc_attr($this->get_field_id('show_all')); ?>" name="<?php echo esc_attr($this->get_field_name('show_all')); ?>" type="checkbox" value="1" <?php checked($show_all); ?>>
                <label for="<?php echo esc_attr($this->get_field_id('show_all')); ?>">Alle Termine anzeigen</label>
            </p>
            <p>
                <input id="<?php echo esc_attr($this->get_field_id('show_availability')); ?>" name="<?php echo esc_attr($this->get_field_name('show_availability')); ?>" type="checkbox" value="1" <?php checked($show_availability); ?>>
                <label for="<?php echo esc_attr($this->get_field_id('show_availability')); ?>">Ticket-Ampel anzeigen</label>
            </p>
            <?php
        }

        public function update($new_instance, $old_instance): array {
            return [
                'title' => sanitize_text_field((string) ($new_instance['title'] ?? '')),
                'event_id' => absint($new_instance['event_id'] ?? 0),
                'limit' => min(10, max(1, absint($new_instance['limit'] ?? 5))),
                'show_all' => !empty($new_instance['show_all']),
                'show_availability' => !empty($new_instance['show_availability']),
            ];
        }
    }
}
