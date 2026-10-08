<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Cron_Trait {

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

}
