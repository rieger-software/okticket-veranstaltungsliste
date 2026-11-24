<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Rest_Trait {
    public static function register_rest_routes(): void {
        register_rest_route('okticket/v1', '/events', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_events_test_response'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('okticket/v1', '/event-details', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_event_details_test_response'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function get_events_test_response(WP_REST_Request $request) {
        $events = get_option(self::OPTION_EVENTS_SNAPSHOT, null);

        if (!is_array($events)) {
            return new WP_Error(
                'okticket_not_synchronized',
                'Es liegen noch keine OKTicket-Eventdaten vor.',
                ['status' => 503]
            );
        }

        return new WP_REST_Response(['events' => $events]);
    }

    public static function get_event_details_test_response(WP_REST_Request $request) {
        $details = get_option(self::OPTION_EVENT_DETAILS_SNAPSHOT, null);

        if (!is_array($details)) {
            return new WP_Error(
                'okticket_not_synchronized',
                'Es liegen noch keine OKTicket-Detaildaten vor.',
                ['status' => 503]
            );
        }

        return new WP_REST_Response(['eventDetails' => $details]);
    }

    /**
     * Makes the two public test endpoints easy to inspect in a browser without
     * changing the response format of WordPress' other REST endpoints.
     */
    public static function serve_pretty_test_json(bool $served, $result, WP_REST_Request $request, WP_REST_Server $server): bool {
        $route = $request->get_route();
        $test_routes = [
            '/okticket/v1/events',
            '/okticket/v1/event-details',
        ];

        if (!in_array($route, $test_routes, true)) {
            return $served;
        }

        $server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
        echo wp_json_encode($result->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return true;
    }
}
