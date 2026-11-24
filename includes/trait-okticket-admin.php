<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Admin_Trait {
    public static function enqueue_admin_assets(): void {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if (!in_array($page, ['okticket-settings', 'okticket-anleitung'], true)) {
            return;
        }

        $file = plugin_dir_path(self::PLUGIN_FILE) . 'assets/admin.css';
        wp_enqueue_style(
            'okticket-veranstaltungsliste-admin',
            plugins_url('assets/admin.css', self::PLUGIN_FILE),
            [],
            file_exists($file) ? (string) filemtime($file) : '0.2.0'
        );
    }

    public static function enqueue_frontend_grid(): void {
        $file = plugin_dir_path(self::PLUGIN_FILE) . 'assets/frontend.css';
        wp_enqueue_style(
            'okticket-veranstaltungsliste-frontend',
            plugins_url('assets/frontend.css', self::PLUGIN_FILE),
            [],
            file_exists($file) ? (string) filemtime($file) : '0.2.9'
        );
    }

    public static function add_settings_page(): void {
        add_options_page('OKTicket Einstellungen', 'OKTicket', 'manage_options', 'okticket-settings', [self::class, 'render_settings_page']);
        add_submenu_page(null, 'OKTicket Anleitung', 'OKTicket Anleitung', 'manage_options', 'okticket-anleitung', [self::class, 'render_help_page']);
    }

    public static function add_plugin_action_links(array $links): array {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=okticket-settings')) . '">Einstellungen</a>';
        $details_link = '<a href="' . esc_url(self_admin_url('plugin-install.php?tab=plugin-information&plugin=okticket-veranstaltungsliste&TB_iframe=true&width=772&height=865')) . '" class="thickbox open-plugin-details-modal" aria-label="Details zu OKTicket Veranstaltungsliste anzeigen">Details</a>';
        $help_link = '<a href="' . esc_url(admin_url('admin.php?page=okticket-anleitung&TB_iframe=true&width=900&height=800')) . '" class="thickbox" aria-label="Anleitung für OKTicket Veranstaltungsliste öffnen">Anleitung</a>';
        array_unshift($links, $settings_link, $details_link, $help_link);

        return $links;
    }

    public static function render_help_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }
        ?>
        <div class="wrap okticket-guide">
            <h1>OKTicket Veranstaltungsliste – Anleitung</h1>
            <p class="description">Mit dem Plugin zeigen Sie OKTicket-Veranstaltungen direkt auf Ihrer WordPress-Website an. Die Daten werden regelmäßig lokal gespeichert, damit Website-Besucher keine Verbindung zur OKTicket-API benötigen.</p>

            <h2>1. Einstellungen öffnen</h2>
            <p>Öffnen Sie in WordPress <strong>Plugins → Installierte Plugins</strong>. In der Zeile „OKTicket Veranstaltungsliste“ führt der Link <strong>Einstellungen</strong> direkt zur Verbindungskonfiguration.</p>
            <figure>
                <img src="<?php echo esc_url(plugins_url('assets/okticket-plugin-actions.png', self::PLUGIN_FILE)); ?>" alt="Die Plugin-Zeile in WordPress mit den Links Einstellungen, Details, Anleitung und Deaktivieren">
                <figcaption>Über „Einstellungen“ öffnen Sie die OKTicket-Konfiguration.</figcaption>
            </figure>

            <h2>2. API-Token bei OKTicket erstellen</h2>
            <ol>
                <li>Melden Sie sich in Ihrem OKTicket-Veranstalterkonto an.</li>
                <li>Klicken Sie oben rechts auf Ihren Veranstalter-Namen und wählen Sie <strong>API</strong>.</li>
                <li>Wählen Sie <strong>Neues Token erstellen</strong> und kopieren Sie den angezeigten Token.</li>
            </ol>
            <div class="okticket-guide__screenshots">
                <figure><img src="<?php echo esc_url(plugins_url('assets/okticket-api-menu.png', self::PLUGIN_FILE)); ?>" alt="Das Veranstalter-Menü bei OKTicket mit dem Menüpunkt API"><figcaption>Schritt 1: Menüpunkt „API“ wählen.</figcaption></figure>
                <figure><img src="<?php echo esc_url(plugins_url('assets/okticket-api-token.png', self::PLUGIN_FILE)); ?>" alt="Die API-Seite bei OKTicket mit der Schaltfläche Neues Token erstellen"><figcaption>Schritt 2: Neuen Token erstellen. Der Token ist hier absichtlich maskiert.</figcaption></figure>
            </div>

            <h2>3. Verbindung in WordPress einrichten</h2>
            <ol>
                <li>Fügen Sie den kopierten Token unter <strong>Einstellungen → OKTicket</strong> ein.</li>
                <li>Klicken Sie auf <strong>Token prüfen und speichern</strong>.</li>
                <li>Nach erfolgreicher Prüfung klicken Sie auf <strong>Daten jetzt aktualisieren</strong>.</li>
            </ol>
            <p>Damit werden Veranstaltungen, Detaildaten und Vorverkaufsstellen erstmals auf die Website übertragen.</p>

            <h2>4. OKTicket-Blöcke in eine Seite einfügen</h2>
            <ol>
                <li>Öffnen Sie die gewünschte WordPress-Seite oder erstellen Sie eine neue Seite.</li>
                <li>Klicken Sie im Editor auf das Plus-Symbol <strong>Block hinzufügen</strong>.</li>
                <li>Suchen Sie nach <strong>OKTicket</strong>.</li>
                <li>Wählen Sie den passenden Block und legen Sie rechts in der Seitenleiste die gewünschten Einstellungen fest.</li>
            </ol>
            <table class="widefat striped">
                <thead><tr><th>Block</th><th>Verwendung</th></tr></thead>
                <tbody>
                    <tr><td><strong>OKTicket Veranstaltungsübersicht</strong></td><td>Zeigt mehrere ausgewählte Veranstaltungsreihen als Übersicht.</td></tr>
                    <tr><td><strong>OKTicket Veranstaltungsdetails</strong></td><td>Zeigt eine ausgewählte Veranstaltungsreihe mit Bild, Beschreibung, Terminen und optionalen Vorverkaufsstellen.</td></tr>
                    <tr><td><strong>OKTicket Termine</strong></td><td>Zeigt die nächsten Termine einer Veranstaltungsreihe, zum Beispiel in einer Seitenleiste.</td></tr>
                </tbody>
            </table>
            <p>Der Block zeigt im Editor eine Vorschau. Änderungen speichern oder aktualisieren Sie anschließend wie gewohnt mit dem WordPress-Button <strong>Aktualisieren</strong> bzw. <strong>Veröffentlichen</strong>.</p>

            <h2>5. Das Widget „OKTicket Termine“ verwenden</h2>
            <p>Das Widget eignet sich für eine Seitenleiste, den Footer oder andere Widget-Bereiche. In klassischen WordPress-Themes öffnen Sie dazu <strong>Design → Widgets</strong>. Bei einem Block-Theme öffnen Sie den Website-Editor unter <strong>Design → Editor</strong> und bearbeiten den gewünschten Template-Bereich.</p>
            <ol>
                <li>Klicken Sie im gewünschten Widget-Bereich auf das Plus-Symbol.</li>
                <li>Suchen Sie nach <strong>OKTicket Termine</strong> und fügen Sie das Widget hinzu.</li>
                <li>Wählen Sie die Veranstaltungsreihe aus.</li>
                <li>Stellen Sie bei Bedarf Überschrift, Anzahl der Termine, Ticket-Ampel und die Anzeige vergangener Termine ein.</li>
                <li>Speichern Sie den Widget-Bereich bzw. die Vorlage.</li>
            </ol>
            <p>Optional kann der nächste Termin hervorgehoben oder die vollständige Terminliste angezeigt werden. Das Widget verwendet denselben lokal gespeicherten Datenbestand wie die OKTicket-Blöcke.</p>

            <h2>6. Aktualisierung und Caching</h2>
            <p>Veranstaltungsdaten werden lokal gespeichert. Die automatische Aktualisierung erfolgt alle 15 Minuten; Vorverkaufsstellen werden täglich aktualisiert. Bei einer nicht erreichbaren API bleibt der zuletzt erfolgreich gespeicherte Datenstand sichtbar.</p>
            <p>Bei zusätzlichem Seiten-, Hosting- oder CDN-Cache sollte die Cache-Laufzeit höchstens <strong>15 Minuten</strong> betragen. Sonst können neue Termine oder Ticketstände erst verspätet sichtbar werden.</p>

            <h2>7. Wenn etwas nicht angezeigt wird</h2>
            <ul>
                <li>Prüfen Sie unter <strong>Einstellungen → OKTicket</strong>, ob die API-Verbindung erreichbar ist.</li>
                <li>Starten Sie eine manuelle Aktualisierung der Daten.</li>
                <li>Leeren Sie bei Bedarf den Seiten- oder Hosting-Cache.</li>
                <li>Prüfen Sie, ob im Block eine Veranstaltungsreihe ausgewählt wurde.</li>
            </ul>
        </div>
        <?php
    }

    /**
     * Provides WordPress with updates from the latest public GitHub release.
     *
     * WordPress decides when to run update checks and whether an automatic
     * update is enabled. This plugin only supplies the release metadata.
     *
     * @param array|false $update Existing update response.
     * @param array<string, mixed> $plugin_data Plugin header data.
     * @param string $plugin_file Plugin basename.
     * @param string[] $locales Installed locales.
     * @return array|false
     */
    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $has_token = self::get_token() !== '';
        $notice = isset($_GET['okticket_notice'])
            ? sanitize_key(wp_unslash($_GET['okticket_notice']))
            : '';
        $events_result = $has_token ? self::get_events() : null;
        $api_is_available = $has_token && self::validate_token(self::get_token()) === 'valid';
        $api_status_class = $api_is_available ? 'success' : ($has_token ? 'danger' : 'secondary');
        $api_status_label = $api_is_available ? 'API erreichbar' : ($has_token ? 'API momentan nicht erreichbar' : 'Kein Token gespeichert');

        self::render_core_settings_page($has_token, $notice, $api_is_available, $api_status_class, $api_status_label);
    }

    private static function render_core_settings_page(bool $has_token, string $notice, bool $api_is_available, string $api_status_class, string $api_status_label): void {
        ?>
        <div class="wrap okticket-admin">
            <h1 class="wp-heading-inline"><img class="okticket-admin-logo" src="<?php echo esc_url(plugins_url('assets/okticket-logo.png', self::PLUGIN_FILE)); ?>" alt=""> OKTicket Einstellungen</h1>
            <p class="description">Verwalten Sie Ihre API-Anbindung und Synchronisation.</p>

            <?php if ($notice === 'valid') : ?>
                <div class="notice notice-success is-dismissible"><p>Der API-Token ist gültig und wurde gespeichert.</p></div>
            <?php elseif ($notice === 'invalid') : ?>
                <div class="notice notice-error"><p>Der API-Token wurde von OKTicket nicht akzeptiert und deshalb nicht gespeichert.</p></div>
            <?php elseif ($notice === 'unreachable') : ?>
                <div class="notice notice-error"><p>Die OKTicket-API ist momentan nicht erreichbar. Der Token wurde nicht verändert.</p></div>
            <?php elseif ($notice === 'missing') : ?>
                <div class="notice notice-warning"><p>Bitte einen API-Token eingeben.</p></div>
            <?php elseif ($notice === 'token_storage_failed') : ?>
                <div class="notice notice-error"><p>Der API-Token konnte nicht sicher gespeichert werden. Bitte wenden Sie sich an die Administration.</p></div>
            <?php elseif ($notice === 'token_deleted') : ?>
                <div class="notice notice-success is-dismissible"><p>Der API-Token wurde gelöscht. Die Verbindung zu OKTicket ist getrennt.</p></div>
            <?php elseif ($notice === 'synced') : ?>
                <div class="notice notice-success is-dismissible"><p>Die OKTicket-Daten wurden vollständig aktualisiert und lokal gespeichert.</p></div>
            <?php elseif ($notice === 'sync_failed') : ?>
                <div class="notice notice-error"><p>Die OKTicket-Daten konnten nicht aktualisiert werden. Der zuletzt gespeicherte Datenstand bleibt erhalten.</p></div>
            <?php endif; ?>

            <h2>API-Verbindung <span class="okticket-status okticket-status--<?php echo esc_attr($api_status_class); ?>"><?php echo esc_html($api_status_label); ?></span></h2>
            <form id="okticket-token-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="altmuehlbuehne_okticket_save_token">
                <?php wp_nonce_field('altmuehlbuehne_okticket_save_token'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="okticket_api_token">API-Token</label></th>
                        <td>
                            <input type="password" id="okticket_api_token" name="okticket_api_token" value="" class="regular-text" autocomplete="off" spellcheck="false">
                            <button type="button" class="button-link okticket-help-button" data-okticket-modal-target="#okticket-api-help" aria-label="Hilfe zum API-Token öffnen">Hilfe</button>
                            <p class="description"><?php echo esc_html($has_token ? 'Leer lassen, um den gespeicherten Token erneut zu prüfen.' : 'Der Token wird vor dem Speichern direkt bei OKTicket geprüft.'); ?></p>
                        </td>
                    </tr>
                </table>
            </form>

            <div class="okticket-actions">
                <button type="submit" form="okticket-token-form" class="button button-primary"><?php echo esc_html($has_token ? 'Verbindung zu OKTicket prüfen' : 'Token prüfen und speichern'); ?></button>
                <form class="okticket-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="altmuehlbuehne_okticket_refresh_events">
                    <?php wp_nonce_field('altmuehlbuehne_okticket_refresh_events'); ?>
                    <button type="submit" class="button button-secondary" <?php disabled(!$api_is_available); ?>>Daten jetzt aktualisieren</button>
                </form>
                <?php if ($has_token) : ?>
                    <form class="okticket-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="altmuehlbuehne_okticket_delete_token">
                        <?php wp_nonce_field('altmuehlbuehne_okticket_delete_token'); ?>
                        <button type="submit" class="button button-secondary okticket-delete-button">Token löschen und Verbindung trennen</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="okticket-modal" id="okticket-api-help" role="dialog" aria-modal="true" aria-labelledby="okticket-api-help-title" aria-hidden="true" hidden>
                <div class="okticket-modal__content" role="document">
                    <div class="okticket-modal__header"><h2 id="okticket-api-help-title">Hilfe zum API-Token</h2><button type="button" class="button-link" data-okticket-modal-close aria-label="Schließen">Schließen</button></div>
                    <div class="okticket-modal__body">
                        <p>Der API-Token verbindet dieses Plugin mit OKTicket, damit Veranstaltungsdaten abgerufen werden können.</p>
                        <ol class="okticket-api-help-steps">
                            <li><strong>API-Einstellungen öffnen:</strong> Im OKTicket-Veranstalterkonto oben rechts auf Ihren Veranstalter-Namen klicken und im geöffneten Menü <strong>„API“</strong> auswählen.<img class="okticket-api-help-image" src="<?php echo esc_url(plugins_url('assets/okticket-api-menu.png', self::PLUGIN_FILE)); ?>" alt="Das Veranstalter-Menü bei OKTicket mit dem Menüpunkt API"></li>
                            <li><strong>Token anlegen:</strong> Auf der API-Seite auf <strong>„Neues Token erstellen“</strong> klicken. Den danach angezeigten Token kopieren, hier einfügen und anschließend <strong>„Token prüfen und speichern“</strong> wählen.<img class="okticket-api-help-image" src="<?php echo esc_url(plugins_url('assets/okticket-api-token.png', self::PLUGIN_FILE)); ?>" alt="Die API-Seite bei OKTicket mit der Schaltfläche Neues Token erstellen; der Token ist maskiert"></li>
                        </ol>
                    </div>
                    <p class="okticket-modal__footer"><button type="button" class="button button-primary" data-okticket-modal-close>Verstanden</button></p>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var closeModal = function (modal) { if (modal) { modal.hidden = true; modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('okticket-modal-open'); } };
            document.addEventListener('click', function (event) {
                var opener = event.target.closest('[data-okticket-modal-target]');
                if (opener) { var modal = document.querySelector(opener.dataset.okticketModalTarget); if (modal) { modal.hidden = false; modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('okticket-modal-open'); } return; }
                var closer = event.target.closest('[data-okticket-modal-close]');
                if (closer) { closeModal(closer.closest('.okticket-modal')); return; }
                if (event.target.classList.contains('okticket-modal')) { closeModal(event.target); }
            });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { closeModal(document.querySelector('.okticket-modal[aria-hidden="false"]')); } });
        }());
        </script>
        <?php
    }

    public static function save_token(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_save_token');

        $submitted_token = isset($_POST['okticket_api_token'])
            ? trim(sanitize_text_field(wp_unslash($_POST['okticket_api_token'])))
            : '';
        $token = $submitted_token !== '' ? $submitted_token : self::get_token();

        if ($token === '') {
            self::redirect_with_notice('missing', 'api');
        }

        $validation = self::validate_token($token);

        if ($validation === 'valid') {
            if ($submitted_token !== '') {
                if (!self::store_token($submitted_token)) {
                    self::redirect_with_notice('token_storage_failed', 'api');
                }
            }
            self::redirect_with_notice('valid', 'api');
        }

        self::redirect_with_notice($validation, 'api');
    }

    public static function delete_token(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_delete_token');
        delete_option(self::OPTION_TOKEN);
        self::redirect_with_notice('token_deleted', 'api');
    }

    public static function refresh_events(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_refresh_events');
        $events_synced = self::sync_events(true);
        $vvks_synced = self::sync_vvks();
        self::redirect_with_notice($events_synced && $vvks_synced ? 'synced' : 'sync_failed', 'dashboard');
    }

}
