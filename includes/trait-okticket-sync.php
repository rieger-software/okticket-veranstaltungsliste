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

    /**
     * Returns the locally cached master list of sales points.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function get_vvks(): array {
        $vvks = get_option(self::OPTION_VVKS_SNAPSHOT, []);
        return is_array($vvks) ? $vvks : [];
    }

    /**
     * Returns the daily cached list ordered by distance from a venue postcode.
     * Falls back to the standard list if that postcode has not been synced yet.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function get_vvks_for_venue_postcode(string $postcode, bool $sort_by_distance): array {
        if (!$sort_by_distance || !preg_match('/^\d{5}$/', $postcode)) {
            return self::get_vvks();
        }

        $by_postcode = get_option(self::OPTION_VVKS_BY_PLZ_SNAPSHOT, []);
        if (is_array($by_postcode) && isset($by_postcode[$postcode]) && is_array($by_postcode[$postcode])) {
            return $by_postcode[$postcode];
        }

        return self::get_vvks();
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

    /**
     * Updates the locally stored sales-point master list once per day.
     */
    private static function sync_vvks(): bool {
        $token = self::get_token();

        if ($token === '') {
            return false;
        }

        $response = wp_remote_post(self::VVKS_URL, [
            'timeout' => 30,
            'body' => ['token' => $token],
        ]);
        $data = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200 || !is_array($data) || !is_array($data['vvks'] ?? null)) {
            return false;
        }

        update_option(self::OPTION_VVKS_SNAPSHOT, $data['vvks'], false);
        self::sync_vvks_by_venue_postcode($token);
        update_option(self::OPTION_VVKS_LAST_SYNC, time(), false);

        return true;
    }

    /**
     * Prepares distance-sorted lists for all postcodes used by current event
     * series, so front-end rendering never has to call the OKTicket API.
     */
    private static function sync_vvks_by_venue_postcode(string $token): void {
        $details_snapshot = get_option(self::OPTION_EVENT_DETAILS_SNAPSHOT, []);
        if (!is_array($details_snapshot)) {
            return;
        }

        $postcodes = [];
        foreach ($details_snapshot as $details) {
            if (!is_array($details)) {
                continue;
            }

            $postcode = trim((string) ($details['OrtPLZ'] ?? ''));
            if (preg_match('/^\d{5}$/', $postcode)) {
                $postcodes[$postcode] = true;
            }
        }

        if (!$postcodes) {
            return;
        }

        $by_postcode = get_option(self::OPTION_VVKS_BY_PLZ_SNAPSHOT, []);
        $by_postcode = is_array($by_postcode) ? $by_postcode : [];

        foreach (array_keys($postcodes) as $postcode) {
            $response = wp_remote_post(self::VVKS_URL, [
                'timeout' => 30,
                'body' => [
                    'token' => $token,
                    'plz' => $postcode,
                ],
            ]);
            $data = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);

            if (wp_remote_retrieve_response_code($response) === 200 && is_array($data) && is_array($data['vvks'] ?? null)) {
                $by_postcode[$postcode] = $data['vvks'];
            }
        }

        update_option(self::OPTION_VVKS_BY_PLZ_SNAPSHOT, $by_postcode, false);
    }

}
