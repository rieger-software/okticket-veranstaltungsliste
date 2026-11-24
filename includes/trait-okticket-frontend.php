<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Frontend_Trait {
    public static function render_events(array $config): string {
        if (self::get_token() === '') {
            return '';
        }

        $fields = isset($config['fields']) && is_array($config['fields']) ? $config['fields'] : self::field_keys();
        $selected_ids = isset($config['event_ids']) && is_array($config['event_ids']) ? array_slice(array_map('absint', $config['event_ids']), 0, 1) : [];
        $vvk_enabled = !empty($config['vvk_enabled']);
        $vvk_fields = isset($config['vvk_fields']) && is_array($config['vvk_fields'])
            ? array_values(array_intersect(self::vvk_field_keys(), array_map('sanitize_key', $config['vvk_fields'])))
            : [];
        $vvk_limit = isset($config['vvk_limit'])
            ? min(50, max(1, absint($config['vvk_limit'])))
            : 5;
        $vvk_sort_by_venue_postcode = !empty($config['vvk_sort_by_venue_postcode']);

        $result = self::get_events();

        if (($result['status'] ?? 0) !== 200 || empty($result['events'])) {
            return '';
        }

        // Resolve an appointment ID to its parent event, so the full series is displayed.
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
                $venue_postcode = trim((string) ($details['OrtPLZ'] ?? ''));
                $vvks = $vvk_enabled
                    ? array_slice(self::get_vvks_for_venue_postcode($venue_postcode, $vvk_sort_by_venue_postcode), 0, $vvk_limit)
                    : [];
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
                        <?php if ($vvks && $vvk_fields) : ?>
                            <div class="okticket-veranstaltung__vvks">
                                <h4>Vorverkaufsstellen</h4>
                                <ul>
                                    <?php foreach ($vvks as $vvk) : ?>
                                        <?php
                                        if (!is_array($vvk)) {
                                            continue;
                                        }
                                        $vvk_values = [];
                                        foreach (self::vvk_field_labels() as $field_key => $label) {
                                            if ($field_key === 'zip') {
                                                $place_parts = [];
                                                foreach (['zip', 'city'] as $place_field) {
                                                    if (!in_array($place_field, $vvk_fields, true)) {
                                                        continue;
                                                    }
                                                    $place_value = trim((string) ($vvk[$place_field] ?? ''));
                                                    if ($place_value !== '') {
                                                        $place_parts[] = $place_value;
                                                    }
                                                }
                                                if ($place_parts) {
                                                    $vvk_values[] = [
                                                        'value' => implode(' ', $place_parts),
                                                        'is_name' => false,
                                                    ];
                                                }
                                                continue;
                                            }
                                            if ($field_key === 'city') {
                                                continue;
                                            }
                                            if (!in_array($field_key, $vvk_fields, true)) {
                                                continue;
                                            }
                                            $value = trim((string) ($vvk[$field_key] ?? ''));
                                            if ($field_key === 'distance' && $value !== '' && is_numeric($value)) {
                                                $value = number_format_i18n((float) $value, 1) . ' km';
                                            }
                                            if ($value !== '') {
                                                $vvk_values[] = [
                                                    'value' => $value,
                                                    'is_name' => $field_key === 'name',
                                                ];
                                            }
                                        }
                                        ?>
                                        <?php if ($vvk_values) : ?>
                                            <li class="okticket-veranstaltung__vvk">
                                                <?php foreach ($vvk_values as $vvk_value) : ?>
                                                    <div class="okticket-veranstaltung__vvk-value">
                                                        <?php if ($vvk_value['is_name']) : ?><strong><?php endif; ?>
                                                        <?php echo nl2br(esc_html($vvk_value['value'])); ?>
                                                        <?php if ($vvk_value['is_name']) : ?></strong><?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </li>
                                        <?php endif; ?>
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

    public static function render_events_overview(array $config): string {
        if (self::get_token() === '') {
            return '';
        }

        $fields = isset($config['fields']) && is_array($config['fields']) ? $config['fields'] : self::overview_field_keys();
        if (!isset($config['version']) && !in_array('appointments', $fields, true)) {
            $fields[] = 'appointments';
        }
        $selected_ids = isset($config['event_ids']) && is_array($config['event_ids']) ? array_map('absint', $config['event_ids']) : [];
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

    private static function vvk_field_keys(): array {
        return array_keys(self::vvk_field_labels());
    }

    private static function vvk_field_labels(): array {
        return [
            'name' => 'Name',
            'zusatz' => 'Zusatz',
            'street' => 'Straße',
            'zip' => 'PLZ',
            'city' => 'Ort',
            'phone' => 'Telefon',
            'opentimes' => 'Öffnungszeiten',
            'distance' => 'Entfernung',
        ];
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

        if ($percent > 30) {
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
