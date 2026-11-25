<?php
defined('ABSPATH') || exit;

/**
 * Page-builder fallback. Gutenberg blocks remain the preferred editor UI, but
 * these saved embeds can be inserted in any editor capable of rendering a
 * normal WordPress shortcode.
 */
trait Altmuehlbuehne_OKTicket_Shortcodes_Trait {
    public static function register_shortcodes(): void {
        add_shortcode('okticket_detail', [self::class, 'render_detail_shortcode']);
        add_shortcode('okticket_uebersicht', [self::class, 'render_overview_shortcode']);
    }

    public static function add_embed_page(): void {
        add_options_page(
            'OKTicket Einbindungen',
            'OKTicket Einbindungen',
            'manage_options',
            'okticket-einbindungen',
            [self::class, 'render_embed_page']
        );
    }

    public static function render_detail_shortcode($attributes): string {
        $attributes = shortcode_atts(['id' => ''], $attributes, 'okticket_detail');
        $event_id = absint($attributes['id']);
        if ($event_id <= 0) {
            return '';
        }

        $settings = self::get_detail_embed_settings($event_id);
        $settings['event_ids'] = [$event_id];

        return self::render_events($settings);
    }

    public static function render_overview_shortcode($attributes): string {
        $attributes = shortcode_atts(['id' => ''], $attributes, 'okticket_uebersicht');
        $embed_id = sanitize_key((string) $attributes['id']);
        $embed = self::get_embeds(self::OPTION_OVERVIEW_EMBEDS)[$embed_id] ?? null;
        $event_ids = is_array($embed['event_ids'] ?? null)
            ? array_values(array_unique(array_filter(array_map('absint', $embed['event_ids']))))
            : [];

        if (!$event_ids) {
            return '';
        }

        return self::render_events_overview([
            'event_ids' => $event_ids,
            'fields' => self::get_selected_fields($embed['fields'] ?? null, self::overview_field_keys()),
            'version' => 2,
        ]);
    }

    public static function render_embed_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        $series = self::get_widget_series();
        $overview_embeds = self::get_embeds(self::OPTION_OVERVIEW_EMBEDS);
        $notice = isset($_GET['okticket_embed_notice']) ? sanitize_key(wp_unslash($_GET['okticket_embed_notice'])) : '';
        ?>
        <div class="wrap">
            <h1>OKTicket Einbindungen</h1>
            <p>Diese Einbindungen funktionieren in Gutenberg, im Classic Editor und in Page Buildern wie WPBakery, Elementor oder Divi. Legen Sie eine Einbindung an und fügen Sie den fertigen Shortcode an der gewünschten Stelle ein.</p>

            <?php if ($notice === 'created') : ?><div class="notice notice-success is-dismissible"><p>Einbindung angelegt.</p></div><?php endif; ?>
            <?php if ($notice === 'saved') : ?><div class="notice notice-success is-dismissible"><p>Anzeigeoptionen gespeichert.</p></div><?php endif; ?>
            <?php if ($notice === 'deleted') : ?><div class="notice notice-success is-dismissible"><p>Einbindung gelöscht.</p></div><?php endif; ?>
            <?php if ($notice === 'invalid') : ?><div class="notice notice-error"><p>Bitte füllen Sie alle erforderlichen Felder aus.</p></div><?php endif; ?>

            <hr>
            <h2>Detailseite einer Veranstaltungsreihe</h2>
            <p>Das Plugin stellt für jede aktuell verfügbare Veranstaltungsreihe automatisch einen Detail-Shortcode bereit. Kopieren Sie den gewünschten Shortcode in Ihren Page Builder.</p>
            <?php self::render_detail_shortcode_list($series); ?>

            <hr>
            <h2>Veranstaltungsübersicht</h2>
            <p>Wählen Sie die Veranstaltung/en aus, die gemeinsam als Übersicht ausgegeben werden sollen.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="altmuehlbuehne_okticket_create_embed">
                <input type="hidden" name="embed_type" value="overview">
                <?php wp_nonce_field('altmuehlbuehne_okticket_create_embed'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="okticket-overview-name">Bezeichnung</label></th><td><input class="regular-text" id="okticket-overview-name" name="embed_name" type="text" required placeholder="z. B. Programm 2026"></td></tr>
                    <tr><th scope="row"><label for="okticket-overview-events">Veranstaltungsreihen</label></th><td><select class="regular-text" id="okticket-overview-events" name="event_ids[]" multiple size="6" required><?php foreach ($series as $event_id => $label) : ?><option value="<?php echo esc_attr((string) $event_id); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select><p class="description">Mehrere Reihen mit Strg bzw. Cmd auswählen.</p></td></tr>
                    <tr><th scope="row">Anzeigeoptionen</th><td><?php self::render_checkbox_options(self::overview_field_labels(), self::overview_field_keys(), 'fields[]'); ?></td></tr>
                </table>
                <?php submit_button('Übersicht anlegen'); ?>
            </form>
            <?php self::render_embed_list($overview_embeds, 'overview'); ?>
        </div>
        <script>
        (function () {
            function copyShortcode(value) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(value);
                }
                var input = document.createElement('textarea');
                input.value = value;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                return Promise.resolve();
            }

            document.addEventListener('click', function (event) {
                var button = event.target.closest('.okticket-copy-shortcode');
                if (!button) { return; }
                copyShortcode(button.getAttribute('data-shortcode') || '').then(function () {
                    var label = button.getAttribute('aria-label');
                    button.setAttribute('aria-label', 'Shortcode kopiert');
                    button.setAttribute('title', 'Shortcode kopiert');
                    window.setTimeout(function () {
                        button.setAttribute('aria-label', label);
                        button.setAttribute('title', label);
                    }, 1800);
                });
            });
        }());
        </script>
        <?php
    }

    public static function create_embed(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }
        check_admin_referer('altmuehlbuehne_okticket_create_embed');

        $type = isset($_POST['embed_type']) ? sanitize_key(wp_unslash($_POST['embed_type'])) : '';
        $name = isset($_POST['embed_name']) ? sanitize_text_field(wp_unslash($_POST['embed_name'])) : '';
        $series = self::get_widget_series();
        $option = $type === 'overview' ? self::OPTION_OVERVIEW_EMBEDS : '';

        if ($name === '' || $option === '') {
            self::redirect_embed_page('invalid');
        }

        $embed = ['name' => $name];
        $submitted_ids = isset($_POST['event_ids']) && is_array($_POST['event_ids']) ? wp_unslash($_POST['event_ids']) : [];
        $event_ids = array_values(array_unique(array_filter(array_map('absint', $submitted_ids))));
        $event_ids = array_values(array_filter($event_ids, static function (int $event_id) use ($series): bool {
            return isset($series[$event_id]);
        }));
        if (!$event_ids) {
            self::redirect_embed_page('invalid');
        }
        $embed['event_ids'] = $event_ids;
        $embed['fields'] = self::get_posted_fields('fields', self::overview_field_keys());

        $embeds = self::get_embeds($option);
        $embed_id = self::create_embed_id($name, $embeds);
        $embeds[$embed_id] = $embed;
        update_option($option, $embeds, false);
        self::redirect_embed_page('created');
    }

    public static function delete_embed(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }
        check_admin_referer('altmuehlbuehne_okticket_delete_embed');

        $type = isset($_POST['embed_type']) ? sanitize_key(wp_unslash($_POST['embed_type'])) : '';
        $embed_id = isset($_POST['embed_id']) ? sanitize_key(wp_unslash($_POST['embed_id'])) : '';
        $option = $type === 'overview' ? self::OPTION_OVERVIEW_EMBEDS : '';
        if ($option === '' || $embed_id === '') {
            self::redirect_embed_page('invalid');
        }

        $embeds = self::get_embeds($option);
        unset($embeds[$embed_id]);
        update_option($option, $embeds, false);
        self::redirect_embed_page('deleted');
    }

    public static function save_detail_embed_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }
        check_admin_referer('altmuehlbuehne_okticket_save_detail_embed_settings');

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        if ($event_id <= 0 || !isset(self::get_widget_series()[$event_id])) {
            self::redirect_embed_page('invalid');
        }

        $settings = self::get_detail_embed_settings($event_id);
        $settings['fields'] = self::get_posted_fields('fields', self::field_keys());
        $settings['vvk_enabled'] = !empty($_POST['vvk_enabled']);
        $settings['vvk_fields'] = self::get_posted_fields('vvk_fields', self::vvk_field_keys());
        $settings['vvk_limit'] = isset($_POST['vvk_limit']) ? min(50, max(1, absint($_POST['vvk_limit']))) : 5;
        $settings['vvk_sort_by_venue_postcode'] = !empty($_POST['vvk_sort_by_venue_postcode']);

        $all_settings = get_option(self::OPTION_DETAIL_EMBED_SETTINGS, []);
        $all_settings = is_array($all_settings) ? $all_settings : [];
        $all_settings[$event_id] = $settings;
        update_option(self::OPTION_DETAIL_EMBED_SETTINGS, $all_settings, false);
        self::redirect_embed_page('saved');
    }

    public static function save_overview_embed_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }
        check_admin_referer('altmuehlbuehne_okticket_save_overview_embed_settings');

        $embed_id = isset($_POST['embed_id']) ? sanitize_key(wp_unslash($_POST['embed_id'])) : '';
        $embeds = self::get_embeds(self::OPTION_OVERVIEW_EMBEDS);
        if ($embed_id === '' || !isset($embeds[$embed_id]) || !is_array($embeds[$embed_id])) {
            self::redirect_embed_page('invalid');
        }

        $embeds[$embed_id]['fields'] = self::get_posted_fields('fields', self::overview_field_keys());
        update_option(self::OPTION_OVERVIEW_EMBEDS, $embeds, false);
        self::redirect_embed_page('saved');
    }

    private static function render_embed_list(array $embeds, string $type): void {
        if (!$embeds) {
            echo '<p><em>Noch keine Einbindung angelegt.</em></p>';
            return;
        }
        $shortcode_name = 'okticket_uebersicht';
        ?>
        <table class="widefat striped"><thead><tr><th>Bezeichnung</th><th>Shortcode</th><th>Kopieren</th><th></th></tr></thead><tbody>
        <?php foreach ($embeds as $embed_id => $embed) : ?>
            <?php $shortcode = '[' . $shortcode_name . ' id="' . $embed_id . '"]'; ?>
            <tr><td><?php echo esc_html((string) ($embed['name'] ?? $embed_id)); ?></td><td><input class="large-text code" type="text" readonly value="<?php echo esc_attr($shortcode); ?>"></td><td><button type="button" class="button button-small okticket-copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>" aria-label="Shortcode kopieren" title="Shortcode kopieren"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span></button></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="altmuehlbuehne_okticket_delete_embed"><input type="hidden" name="embed_type" value="<?php echo esc_attr($type); ?>"><input type="hidden" name="embed_id" value="<?php echo esc_attr($embed_id); ?>"><?php wp_nonce_field('altmuehlbuehne_okticket_delete_embed'); ?><button type="submit" class="button-link-delete" onclick="return confirm('Einbindung wirklich löschen?');">Löschen</button></form></td></tr>
            <tr><td colspan="4"><details><summary>Anzeigeoptionen</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="altmuehlbuehne_okticket_save_overview_embed_settings"><input type="hidden" name="embed_id" value="<?php echo esc_attr($embed_id); ?>"><?php wp_nonce_field('altmuehlbuehne_okticket_save_overview_embed_settings'); ?><?php self::render_checkbox_options(self::overview_field_labels(), self::get_selected_fields($embed['fields'] ?? null, self::overview_field_keys()), 'fields[]'); ?><?php submit_button('Anzeigeoptionen speichern', 'secondary small', 'submit', false); ?></form></details></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php
    }

    private static function render_detail_shortcode_list(array $series): void {
        if (!$series) {
            echo '<p><em>Nach der ersten Datenaktualisierung erscheinen hier die verfügbaren Veranstaltungsreihen.</em></p>';
            return;
        }
        ?>
        <table class="widefat striped"><thead><tr><th>Veranstaltungsreihe</th><th>Shortcode</th><th>Kopieren</th></tr></thead><tbody>
        <?php foreach ($series as $event_id => $label) : ?>
            <?php $shortcode = '[okticket_detail id="' . absint($event_id) . '"]'; $settings = self::get_detail_embed_settings((int) $event_id); ?>
            <tr><td><?php echo esc_html($label); ?></td><td><input class="large-text code" type="text" readonly value="<?php echo esc_attr($shortcode); ?>"></td><td><button type="button" class="button button-small okticket-copy-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>" aria-label="Shortcode kopieren" title="Shortcode kopieren"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span></button></td></tr>
            <tr><td colspan="3"><details><summary>Anzeigeoptionen</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="altmuehlbuehne_okticket_save_detail_embed_settings"><input type="hidden" name="event_id" value="<?php echo esc_attr((string) $event_id); ?>"><?php wp_nonce_field('altmuehlbuehne_okticket_save_detail_embed_settings'); ?><p><strong>Inhalte</strong><br><?php self::render_checkbox_options(self::field_labels(), $settings['fields'], 'fields[]'); ?></p><p><label><input type="checkbox" name="vvk_enabled" value="1" <?php checked(!empty($settings['vvk_enabled'])); ?>> Vorverkaufsstellen anzeigen</label></p><p><strong>Angaben zu Vorverkaufsstellen</strong><br><?php self::render_checkbox_options(self::vvk_field_labels(), $settings['vvk_fields'], 'vvk_fields[]'); ?></p><p><label>Anzahl Vorverkaufsstellen <input class="small-text" type="number" min="1" max="50" name="vvk_limit" value="<?php echo esc_attr((string) $settings['vvk_limit']); ?>"></label></p><p><label><input type="checkbox" name="vvk_sort_by_venue_postcode" value="1" <?php checked(!empty($settings['vvk_sort_by_venue_postcode'])); ?>> Nach Nähe zum Veranstaltungsort sortieren</label></p><?php submit_button('Anzeigeoptionen speichern', 'secondary small', 'submit', false); ?></form></details></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php
    }

    private static function render_checkbox_options(array $labels, array $selected, string $name): void {
        foreach ($labels as $key => $label) {
            ?><label style="display:inline-block;margin:0 1em .45em 0;"><input type="checkbox" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected, true)); ?>> <?php echo esc_html($label); ?></label><?php
        }
    }

    private static function get_posted_fields(string $key, array $allowed): array {
        $submitted = isset($_POST[$key]) && is_array($_POST[$key]) ? wp_unslash($_POST[$key]) : [];
        return self::get_selected_fields($submitted, $allowed);
    }

    private static function get_selected_fields($selected, array $allowed): array {
        if (!is_array($selected)) {
            return $allowed;
        }

        return array_values(array_intersect($allowed, array_map('sanitize_key', $selected)));
    }

    private static function get_detail_embed_settings(int $event_id): array {
        $all_settings = get_option(self::OPTION_DETAIL_EMBED_SETTINGS, []);
        $stored = is_array($all_settings) && isset($all_settings[$event_id]) && is_array($all_settings[$event_id])
            ? $all_settings[$event_id]
            : [];

        return [
            'fields' => self::get_selected_fields($stored['fields'] ?? null, self::field_keys()),
            'vvk_enabled' => !empty($stored['vvk_enabled']),
            'vvk_fields' => self::get_selected_fields($stored['vvk_fields'] ?? null, self::vvk_field_keys()),
            'vvk_limit' => isset($stored['vvk_limit']) ? min(50, max(1, absint($stored['vvk_limit']))) : 5,
            'vvk_sort_by_venue_postcode' => !empty($stored['vvk_sort_by_venue_postcode']),
        ];
    }

    private static function get_embeds(string $option): array {
        $embeds = get_option($option, []);
        return is_array($embeds) ? $embeds : [];
    }

    private static function create_embed_id(string $name, array $embeds): string {
        $base = sanitize_title($name);
        $base = $base !== '' ? $base : 'okticket';
        $embed_id = $base;
        $suffix = 2;
        while (isset($embeds[$embed_id])) {
            $embed_id = $base . '-' . $suffix;
            $suffix++;
        }
        return $embed_id;
    }

    private static function redirect_embed_page(string $notice): void {
        wp_safe_redirect(add_query_arg(
            'okticket_embed_notice',
            sanitize_key($notice),
            admin_url('options-general.php?page=okticket-einbindungen')
        ));
        exit;
    }
}
