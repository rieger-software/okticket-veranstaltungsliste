<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Sync_Trait {
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

}
