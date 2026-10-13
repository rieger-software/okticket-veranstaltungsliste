<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Blocks_Trait {
    public static function register_overview_block(): void {
        $file = plugin_dir_path(self::PLUGIN_FILE) . 'assets/okticket-overview-block.js';

        wp_register_script(
            'okticket-overview-block',
            plugins_url('assets/okticket-overview-block.js', self::PLUGIN_FILE),
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
        foreach (self::get_widget_series() as $series_id => $series_label) {
            $series[$series_id] = ['value' => (string) $series_id, 'label' => $series_label];
        }

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
        wp_enqueue_script('okticket-appointments-widget-block');
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
        wp_add_inline_script(
            'okticket-appointments-widget-block',
            'window.OKTicketAppointmentsWidgetBlockData = ' . wp_json_encode([
                'series' => array_values($series),
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
        $file = plugin_dir_path(self::PLUGIN_FILE) . 'assets/okticket-details-block.js';

        wp_register_script(
            'okticket-details-block',
            plugins_url('assets/okticket-details-block.js', self::PLUGIN_FILE),
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
                'vvkEnabled' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'vvkFields' => [
                    'type' => 'array',
                    'default' => self::vvk_field_keys(),
                ],
                'vvkLimit' => [
                    'type' => 'number',
                    'default' => 5,
                ],
                'vvkSortByVenuePostcode' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);
    }

    public static function render_details_block(array $attributes): string {
        $event_id = isset($attributes['eventId']) ? absint($attributes['eventId']) : 0;
        $fields = isset($attributes['fields']) && is_array($attributes['fields'])
            ? array_values(array_intersect(self::field_keys(), array_map('sanitize_key', $attributes['fields'])))
            : self::field_keys();
        $vvk_fields = isset($attributes['vvkFields']) && is_array($attributes['vvkFields'])
            ? array_values(array_intersect(self::vvk_field_keys(), array_map('sanitize_key', $attributes['vvkFields'])))
            : self::vvk_field_keys();
        $vvk_limit = isset($attributes['vvkLimit']) ? min(50, max(1, absint($attributes['vvkLimit']))) : 5;
        $vvk_enabled = !empty($attributes['vvkEnabled']);
        $vvk_sort_by_venue_postcode = !empty($attributes['vvkSortByVenuePostcode']);

        if ($event_id <= 0) {
            return '';
        }

        return self::render_events_shortcode([
            'preview_config' => [
                'event_ids' => [$event_id],
                'fields' => $fields,
                'vvk_enabled' => $vvk_enabled,
                'vvk_fields' => $vvk_fields,
                'vvk_limit' => $vvk_limit,
                'vvk_sort_by_venue_postcode' => $vvk_sort_by_venue_postcode,
            ],
        ]);
    }

    public static function register_appointments_widget_block(): void {
        $file = plugin_dir_path(self::PLUGIN_FILE) . 'assets/okticket-appointments-widget-block.js';

        wp_register_script(
            'okticket-appointments-widget-block',
            plugins_url('assets/okticket-appointments-widget-block.js', self::PLUGIN_FILE),
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
            file_exists($file) ? (string) filemtime($file) : '0.2.5',
            true
        );

        register_block_type('okticket/termine', [
            'api_version' => 3,
            'editor_script' => 'okticket-appointments-widget-block',
            'render_callback' => [self::class, 'render_appointments_widget_block'],
            'attributes' => [
                'eventId' => ['type' => 'string', 'default' => ''],
                'title' => ['type' => 'string', 'default' => 'Nächste Aufführungen'],
                'limit' => ['type' => 'number', 'default' => 5],
                'showAll' => ['type' => 'boolean', 'default' => false],
                'showAvailability' => ['type' => 'boolean', 'default' => true],
            ],
        ]);
    }

    public static function register_appointments_legacy_widget(): void {
        register_widget(Altmuehlbuehne_OKTicket_Appointments_Widget::class);
    }

    /**
     * @return array<int, string> Parent event ID => event series name.
     */
    public static function get_widget_series(): array {
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
            $series[$parent_id] = (string) ($parent_details['Titel'] ?? $parent_details['Name'] ?? $event['name'] ?? 'Veranstaltung');
        }

        natcasesort($series);
        return $series;
    }

    public static function render_appointments_widget_block(array $attributes): string {
        $event_id = isset($attributes['eventId']) ? absint($attributes['eventId']) : 0;
        $title = isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : '';
        $limit = isset($attributes['limit']) ? min(10, max(1, absint($attributes['limit']))) : 5;
        $show_all = !empty($attributes['showAll']);
        $show_availability = !empty($attributes['showAvailability']);

        if ($event_id <= 0 || self::get_token() === '') {
            return '';
        }

        $result = self::get_events();
        if (($result['status'] ?? 0) !== 200 || empty($result['events'])) {
            return '';
        }

        $selected_details = self::get_event_details($event_id);
        $parent_id = (int) ($selected_details['ParentID'] ?? 0);
        $parent_id = $parent_id > 0 ? $parent_id : $event_id;
        $appointments = [];

        foreach ($result['events'] as $event) {
            $child_id = (int) ($event['event_id'] ?? 0);
            if ($child_id <= 0) {
                continue;
            }
            $details = self::get_event_details($child_id);
            $child_parent_id = (int) ($details['ParentID'] ?? 0);
            $child_parent_id = $child_parent_id > 0 ? $child_parent_id : $child_id;
            if ($child_parent_id !== $parent_id) {
                continue;
            }
            $appointments[] = ['event' => $event, 'details' => $details];
        }

        usort($appointments, static function (array $a, array $b): int {
            return strtotime((string) ($a['details']['Datum'] ?? $a['event']['datum'] ?? '')) <=> strtotime((string) ($b['details']['Datum'] ?? $b['event']['datum'] ?? ''));
        });
        if (!$show_all) {
            $appointments = array_slice($appointments, 0, $limit);
        }

        if (!$appointments) {
            return '';
        }

        ob_start();
        ?>
        <div class="okticket-bootstrap okticket-termin-widget">
            <?php if ($title !== '') : ?><h3 class="okticket-termin-widget__title"><?php echo esc_html($title); ?></h3><?php endif; ?>
            <div class="okticket-termin-widget__termine">
                <ul>
                    <?php foreach ($appointments as $appointment) : ?>
                        <?php
                        $details = $appointment['details'];
                        $event = $appointment['event'];
                        $date = trim((string) ($details['Datum'] ?? $event['datum'] ?? ''));
                        $time = trim((string) ($details['Zeit'] ?? ''));
                        $time = preg_replace('/^(\d{1,2}:\d{2})(?::\d{2})?$/', '$1', $time) ?? $time;
                        $timestamp = strtotime($date);
                        $label = $timestamp ? wp_date('l, d.m.Y', $timestamp) : $date;
                        $traffic_light = self::get_traffic_light((int) ($event['free'] ?? 0), (int) ($event['total'] ?? 0));
                        ?>
                        <li>
                            <?php echo esc_html($label . ($time !== '' ? ', ' . $time . ' Uhr' : '')); ?>
                            <?php if ($show_availability) : ?> <span class="<?php echo esc_attr($traffic_light['class']); ?>" title="<?php echo esc_attr($traffic_light['label']); ?>"><span class="okticket-veranstaltung__status-dot" aria-hidden="true">●</span><span class="screen-reader-text"><?php echo esc_html($traffic_light['label']); ?></span></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

}
