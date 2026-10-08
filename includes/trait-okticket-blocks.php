<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Blocks_Trait {
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

}
