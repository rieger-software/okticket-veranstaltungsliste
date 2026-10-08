<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Frontend_Trait {
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
