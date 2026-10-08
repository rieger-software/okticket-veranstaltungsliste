<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Admin_Trait {
    public static function enqueue_admin_assets(): void {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($page !== 'okticket-settings') {
            return;
        }

        wp_enqueue_style(
            'okticket-tabler-ui',
            'https://cdn.jsdelivr.net/npm/@tabler/core@1.5.1/dist/css/tabler.min.css',
            [],
            '1.5.1'
        );
        wp_enqueue_script(
            'okticket-tabler-ui',
            'https://cdn.jsdelivr.net/npm/@tabler/core@1.5.1/dist/js/tabler.min.js',
            [],
            '1.5.1',
            true
        );

        $file = plugin_dir_path(self::PLUGIN_FILE) . 'assets/admin.css';
        wp_enqueue_style(
            'okticket-veranstaltungsliste-admin',
            plugins_url('assets/admin.css', self::PLUGIN_FILE),
            [],
            file_exists($file) ? (string) filemtime($file) : '0.2.0'
        );
    }

    public static function enqueue_frontend_grid(): void {
        wp_enqueue_style(
            'okticket-tabler-ui',
            'https://cdn.jsdelivr.net/npm/@tabler/core@1.5.1/dist/css/tabler.min.css',
            [],
            '1.5.1'
        );
        wp_register_style('okticket-veranstaltungsliste-grid', false, ['okticket-tabler-ui'], '0.2.0');
        wp_enqueue_style('okticket-veranstaltungsliste-grid');
        wp_add_inline_style('okticket-veranstaltungsliste-grid', '
            .okticket-bootstrap .okticket-veranstaltung__termine ul{margin:0;padding:0;list-style:none}
            .okticket-bootstrap .okticket-veranstaltung__termin{margin:0 0 .5rem}
            .okticket-bootstrap .okticket-veranstaltung__date{display:block;padding:.5rem .75rem;color:inherit;text-decoration:none;background:inherit;border:1px solid #c9c9c9;border-radius:.4rem;box-shadow:none;transition:border-color .15s ease,background-color .15s ease}
            .okticket-bootstrap .okticket-veranstaltung__date:hover,.okticket-bootstrap .okticket-veranstaltung__date:focus{color:inherit;text-decoration:none;background:rgba(0,0,0,.02);border-color:#999}
            .okticket-bootstrap .okticket-veranstaltung__date-row{align-items:center}
            .okticket-bootstrap .okticket-veranstaltung__date-full{font-weight:700}
            .okticket-bootstrap .okticket-veranstaltung__date-time-column{display:flex;justify-content:center}
            .okticket-bootstrap .okticket-veranstaltung__date-time{display:inline-block;font-weight:bold;padding:2px .55em;border-radius:.4rem;background:rgba(0,0,0,.05);opacity:.75;white-space:nowrap}
            .okticket-bootstrap .okticket-veranstaltung__beschreibung{margin:0 0 .5rem;line-height:1.4}
            .okticket-bootstrap .okticket-veranstaltung__langbeschreibung{padding-bottom:10px}
            .okticket-bootstrap .okticket-veranstaltung__titel{margin:0 0 .35rem!important;font-size:1.2em;font-weight:700}
            .okticket-bootstrap .okticket-veranstaltung__ort{margin:0 0 1rem;font-size:.95em}
            .okticket-bootstrap .okticket-veranstaltung__date-status{white-space:nowrap}
            .okticket-bootstrap .okticket-veranstaltung__status-dot{display:inline;font-size:.85em;line-height:1;background:none}
            .okticket-bootstrap .okticket-veranstaltung__status--available .okticket-veranstaltung__status-dot{color:#198754}
            .okticket-bootstrap .okticket-veranstaltung__status--few .okticket-veranstaltung__status-dot{color:#d97706}
            .okticket-bootstrap .okticket-veranstaltung__status--critical .okticket-veranstaltung__status-dot{color:#dc3545}
            .okticket-bootstrap .okticket-veranstaltung__status--sold-out .okticket-veranstaltung__status-dot{color:#6c757d}
            .okticket-bootstrap .okticket-veranstaltung__date-chevron-column{display:flex;justify-content:flex-end}
            .okticket-bootstrap .okticket-veranstaltung__date-chevron{font-size:1.5em;line-height:1}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht{width:100%}
            .okticket-bootstrap .searchItem{margin:0 0 1.5rem;padding:1rem;background:#fff;border:1px solid #c9c9c9;border-radius:.4rem}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__link{display:block;color:inherit;text-decoration:none}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__link:hover,.okticket-bootstrap .okticket-veranstaltungsuebersicht__link:focus{color:inherit;text-decoration:none}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__kopf{display:grid;grid-template-columns:160px minmax(0,1fr);gap:1rem;align-items:start}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__kopf--ohne-bild{grid-template-columns:minmax(0,1fr)}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__bild{display:block;width:160px;height:112px;object-fit:cover}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__inhalt{min-width:0}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__titel{margin:0 0 .35rem!important;font-size:1.2em;font-weight:700}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__ort{margin:0 0 1rem;font-size:.95em}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__beschreibung{margin:0;line-height:1.4}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__termine{margin-top:1.25rem}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__termine-titel{margin:0 0 .5rem;font-size:1em;font-weight:700}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__termine-liste{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem 1rem;margin:0;padding:0;list-style:none}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__termin{margin:0;padding:.35rem .5rem;border-radius:.25rem;background:rgba(0,0,0,.05);font-weight:600}
            .okticket-bootstrap .okticket-veranstaltungsuebersicht__weitere{background:transparent;color:inherit;font-weight:400}
            @media (max-width:650px){.okticket-bootstrap .searchItem{padding:.75rem}.okticket-bootstrap .okticket-veranstaltungsuebersicht__kopf{grid-template-columns:125px minmax(0,1fr);gap:.75rem}.okticket-bootstrap .okticket-veranstaltungsuebersicht__bild{width:125px;height:88px}.okticket-bootstrap .okticket-veranstaltungsuebersicht__beschreibung{display:none}.okticket-bootstrap .okticket-veranstaltungsuebersicht__ort{margin-bottom:0}.okticket-bootstrap .okticket-veranstaltungsuebersicht__termine-liste{grid-template-columns:1fr}.okticket-bootstrap .okticket-veranstaltungsuebersicht__kopf--ohne-bild{grid-template-columns:minmax(0,1fr)}}
        ');
    }

    public static function add_settings_page(): void {
        add_options_page('OKTicket Einstellungen', 'OKTicket', 'manage_options', 'okticket-settings', [self::class, 'render_settings_page']);
    }

    public static function add_plugin_action_links(array $links): array {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=okticket-settings')) . '">Einstellungen</a>';
        $details_link = '<a href="' . esc_url(self_admin_url('plugin-install.php?tab=plugin-information&plugin=okticket-veranstaltungsliste&TB_iframe=true&width=772&height=865')) . '" class="thickbox open-plugin-details-modal" aria-label="Details zu OKTicket Veranstaltungsliste anzeigen">Details</a>';
        array_unshift($links, $settings_link, $details_link);

        return $links;
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
        ?>
        <div class="wrap okticket-admin">
            <div class="page-header d-print-none mb-4">
                <div class="container-fluid">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-md bg-transparent">
                                <img src="<?php echo esc_url(plugins_url('assets/okticket-logo.png', self::PLUGIN_FILE)); ?>" alt="OKTicket Logo" class="avatar-img">
                            </span>
                        </div>
                        <div class="col">
                            <h2 class="page-title">OKTicket Einstellungen</h2>
                            <div class="text-secondary mt-1">Verwalten Sie Ihre API-Anbindung und Synchronisation</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-body">
                <div class="container-fluid">

            <?php if ($notice === 'valid') : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Der API-Token ist gültig und wurde gespeichert.</p>
                </div>
            <?php elseif ($notice === 'invalid') : ?>
                <div class="notice notice-error">
                    <p>Der API-Token wurde von OKTicket nicht akzeptiert und deshalb nicht gespeichert.</p>
                </div>
            <?php elseif ($notice === 'unreachable') : ?>
                <div class="notice notice-error">
                    <p>Die OKTicket-API ist momentan nicht erreichbar. Der Token wurde nicht verändert.</p>
                </div>
            <?php elseif ($notice === 'missing') : ?>
                <div class="notice notice-warning">
                    <p>Bitte einen API-Token eingeben.</p>
                </div>
            <?php elseif ($notice === 'token_deleted') : ?>
                <div class="notice notice-success is-dismissible"><p>Der API-Token wurde gelöscht. Die Verbindung zu OKTicket ist getrennt.</p></div>
            <?php elseif ($notice === 'shortcode_saved') : ?>
                <div class="notice notice-success is-dismissible"><p>Der Shortcode wurde gespeichert.</p></div>
            <?php elseif ($notice === 'shortcode_deleted') : ?>
                <div class="notice notice-success is-dismissible"><p>Der Shortcode wurde gelöscht.</p></div>
            <?php elseif ($notice === 'shortcode_missing') : ?>
                <div class="notice notice-error"><p>Bitte Name, mindestens eine Veranstaltung und mindestens ein Feld auswählen.</p></div>
            <?php elseif ($notice === 'synced') : ?>
                <div class="notice notice-success is-dismissible"><p>Die OKTicket-Daten wurden vollständig aktualisiert und lokal gespeichert.</p></div>
            <?php elseif ($notice === 'sync_failed') : ?>
                <div class="notice notice-error"><p>Die OKTicket-Daten konnten nicht aktualisiert werden. Der zuletzt gespeicherte Datenstand bleibt erhalten.</p></div>
            <?php endif; ?>

            <script>
                document.addEventListener('click', function (event) {
                    var button = event.target.closest('.okticket-copy-shortcode');
                    if (!button || !navigator.clipboard) { return; }
                    navigator.clipboard.writeText(button.dataset.shortcode).then(function () {
                        var label = button.textContent;
                        button.textContent = 'Kopiert';
                        window.setTimeout(function () { button.textContent = label; }, 1500);
                    });
                });
            </script>

                    <div class="row row-cards">
                        <div class="col-12 col-xxl-7">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">API-Konfiguration</h3>
                                    <div class="card-actions">
                                        <span class="badge bg-<?php echo esc_attr($api_status_class); ?>-lt d-flex align-items-center gap-1">
                                            <span class="status-dot status-dot-animated bg-<?php echo esc_attr($api_status_class); ?>"></span>
                                            <?php echo esc_html($api_status_label); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form id="okticket-token-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="altmuehlbuehne_okticket_save_token">
                                        <?php wp_nonce_field('altmuehlbuehne_okticket_save_token'); ?>
                                        <div class="mb-3 row">
                                            <label for="okticket_api_token" class="col-3 col-form-label required">
                                                API-Token
                                                <button type="button" class="form-help border-0" data-bs-toggle="modal" data-bs-target="#okticket-api-help" aria-label="Hilfe zum API-Token öffnen">?</button>
                                            </label>
                                            <div class="col-9">
                            <input
                                type="password"
                                id="okticket_api_token"
                                name="okticket_api_token"
                                value=""
                                class="form-control"
                                placeholder="••••••••••••••••"
                                autocomplete="off"
                                spellcheck="false"
                            >
                            <small class="form-hint">
                                <?php if ($has_token) : ?>
                                    Leer lassen, um den gespeicherten Token erneut zu prüfen.
                                <?php else : ?>
                                    Der Token wird vor dem Speichern direkt bei OKTicket geprüft.
                                <?php endif; ?>
                            </small>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between bg-transparent">
                                    <div class="btn-list">
                                        <button type="submit" form="okticket-token-form" class="btn btn-primary btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-plug-connected" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M7 12l5 5l-1.5 1.5a3.536 3.536 0 1 1 -5 -5l1.5 -1.5z"/>
                                                <path d="M17 12l-5 -5l1.5 -1.5a3.536 3.536 0 1 1 5 5l-1.5 1.5z"/>
                                                <path d="M3 21l2.5 -2.5"/><path d="M18.5 5.5l2.5 -2.5"/><path d="M10 11l-2 -2"/><path d="M13 14l-2 -2"/>
                                            </svg>
                                            <?php echo esc_html($has_token ? 'Verbindung zu OKTicket prüfen' : 'Token prüfen und speichern'); ?>
                                        </button>
                                        <form class="d-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="altmuehlbuehne_okticket_refresh_events">
                                            <?php wp_nonce_field('altmuehlbuehne_okticket_refresh_events'); ?>
                                            <button type="submit" class="btn btn-outline-secondary btn-sm" <?php disabled(!$api_is_available); ?>>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-refresh" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                                </svg>
                                                Daten jetzt aktualisieren
                                            </button>
                                        </form>
                                    </div>
                                    <?php if ($has_token) : ?>
                                        <form class="d-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="altmuehlbuehne_okticket_delete_token">
                                            <?php wp_nonce_field('altmuehlbuehne_okticket_delete_token'); ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-unlink" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                    <path d="M10 14a3.5 3.5 0 0 0 5 0l4 -4a3.5 3.5 0 0 0 -5 -5l-.5 .5"/><path d="M14 10a3.5 3.5 0 0 0 -5 0l-4 4a3.5 3.5 0 0 0 5 5l.5 -.5"/>
                                                </svg>
                                                Token löschen &amp; Verbindung trennen
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

            <div class="modal modal-blur fade" id="okticket-api-help" tabindex="-1" aria-labelledby="okticket-api-help-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title" id="okticket-api-help-title">Hilfe zum API-Token</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <p>Der API-Token verbindet dieses Plugin mit OKTicket, damit Veranstaltungsdaten abgerufen werden können.</p>
                            <ol class="okticket-api-help-steps">
                                <li>
                                    <strong>API-Einstellungen öffnen:</strong> Im OKTicket-Veranstalterkonto oben rechts auf Ihren Veranstalter-Namen klicken und im geöffneten Menü <strong>„API“</strong> auswählen.
                                    <img class="okticket-api-help-image" src="<?php echo esc_url(plugins_url('assets/okticket-api-menu.png', self::PLUGIN_FILE)); ?>" alt="Das Veranstalter-Menü bei OKTicket mit dem Menüpunkt API">
                                </li>
                                <li>
                                    <strong>Token anlegen:</strong> Auf der API-Seite auf <strong>„Neues Token erstellen“</strong> klicken. Den danach angezeigten Token kopieren, hier einfügen und anschließend <strong>„Token prüfen und speichern“</strong> wählen.
                                    <img class="okticket-api-help-image" src="<?php echo esc_url(plugins_url('assets/okticket-api-token.png', self::PLUGIN_FILE)); ?>" alt="Die API-Seite bei OKTicket mit der Schaltfläche Neues Token erstellen; der Token ist maskiert">
                                </li>
                            </ol>
                            <p>Bereits gespeicherte Token werden aus Sicherheitsgründen nicht angezeigt. Bei Fragen zum Token wenden Sie sich bitte an den OKTicket-Support.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Verstanden</button>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            </div>
        </div>
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
                update_option(self::OPTION_TOKEN, $submitted_token, false);
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
        self::redirect_with_notice(self::sync_events() ? 'synced' : 'sync_failed', 'dashboard');
    }

    public static function preview_overview(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        $id = isset($_GET['overview_id']) ? sanitize_key(wp_unslash($_GET['overview_id'])) : '';
        check_admin_referer('altmuehlbuehne_okticket_preview_overview_' . $id);
        $config = $id !== '' ? (self::get_overview_shortcode_configs()[$id] ?? null) : null;
        $preview_config = self::get_preview_overview_config();

        if (is_array($preview_config)) {
            $config = $preview_config;
        }

        if (!is_array($config)) {
            wp_die('Diese Veranstaltungsübersicht wurde nicht gefunden.', 'Vorschau nicht verfügbar', ['response' => 404]);
        }

        nocache_headers();
        do_action('wp_enqueue_scripts');
        if (get_template_directory() !== get_stylesheet_directory()) {
            wp_enqueue_style('okticket-preview-parent-theme', get_template_directory_uri() . '/style.css', [], null);
        }
        $stylesheet_uri = get_stylesheet_uri();
        if ($stylesheet_uri !== '') {
            wp_enqueue_style('okticket-preview-active-theme', $stylesheet_uri, [], null);
        }
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html((string) ($config['name'] ?? 'Veranstaltungsübersicht')); ?> – Vorschau</title>
            <?php wp_head(); ?>
            <style>.okticket-preview-notice{max-width:1200px;margin:18px auto;padding:10px 16px;background:#f0f6fc;border-left:4px solid #2271b1;box-sizing:border-box;font:14px/1.4 sans-serif}.okticket-preview-content{max-width:1200px;margin:0 auto;padding:0 16px 32px;box-sizing:border-box}.okticket-preview .okticket-veranstaltungsuebersicht__beschreibung{display:block!important}</style>
        </head>
        <body <?php body_class('okticket-preview'); ?>>
            <?php wp_body_open(); ?>
            <div class="okticket-preview-notice">Vorschau: <?php echo esc_html((string) ($config['name'] ?? $id)); ?>. Diese Ansicht ist nur für Administratoren sichtbar.</div>
            <main class="okticket-preview-content">
                <?php echo self::render_events_overview_shortcode(['id' => $id, 'preview_config' => $config]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    public static function preview_event_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        $id = isset($_GET['event_page_id']) ? sanitize_key(wp_unslash($_GET['event_page_id'])) : '';
        check_admin_referer('altmuehlbuehne_okticket_preview_event_page_' . $id);
        $config = $id !== '' ? (self::get_shortcode_configs()[$id] ?? null) : null;
        $preview_config = self::get_preview_shortcode_config();

        if (is_array($preview_config)) {
            $config = $preview_config;
        }

        if (!is_array($config)) {
            wp_die('Diese Veranstaltungsseite wurde nicht gefunden.', 'Vorschau nicht verfügbar', ['response' => 404]);
        }

        nocache_headers();
        do_action('wp_enqueue_scripts');
        if (get_template_directory() !== get_stylesheet_directory()) {
            wp_enqueue_style('okticket-preview-parent-theme', get_template_directory_uri() . '/style.css', [], null);
        }
        $stylesheet_uri = get_stylesheet_uri();
        if ($stylesheet_uri !== '') {
            wp_enqueue_style('okticket-preview-active-theme', $stylesheet_uri, [], null);
        }
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html((string) ($config['name'] ?? 'Veranstaltungsseite')); ?> – Vorschau</title>
            <?php wp_head(); ?>
            <style>.okticket-preview-notice{max-width:1200px;margin:18px auto;padding:10px 16px;background:#f0f6fc;border-left:4px solid #2271b1;box-sizing:border-box;font:14px/1.4 sans-serif}.okticket-preview-content{max-width:1200px;margin:0 auto;padding:0 16px 32px;box-sizing:border-box}</style>
        </head>
        <body <?php body_class('okticket-preview'); ?>>
            <?php wp_body_open(); ?>
            <div class="okticket-preview-notice">Vorschau: <?php echo esc_html((string) ($config['name'] ?? $id)); ?>. Diese Ansicht ist nur für Administratoren sichtbar.</div>
            <main class="okticket-preview-content">
                <?php echo self::render_events_shortcode(['id' => $id, 'preview_config' => $config]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    private static function render_dashboard(?array $result, bool $has_token): void {
        $connection_label = !$has_token
            ? 'Nicht eingerichtet'
            : (($result['status'] ?? 0) === 200 ? 'Verbunden' : 'Nicht erreichbar');
        $connection_class = !$has_token || ($result['status'] ?? 0) !== 200 ? 'notice-error' : 'notice-success';
        ?>
        <div class="okticket-dashboard-grid">
            <div class="okticket-dashboard-card">
                <div class="okticket-dashboard-card-header">
                    <h2>Verbindung</h2>
                    <p class="<?php echo esc_attr($connection_class); ?>"><strong><?php echo esc_html($connection_label); ?></strong></p>
                </div>
                <p><?php echo $has_token ? 'Ein API-Token ist gespeichert.' : 'Es ist noch kein API-Token gespeichert.'; ?></p>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dashboard&tab=api')); ?>">Verbindung verwalten</a>
            </div>
        </div>
        <div class="okticket-dashboard-actions">
            <h2>Daten aktualisieren</h2>
            <p>Lädt alle Veranstaltungs- und Detaildaten einmal von OKTicket und speichert sie lokal. Backend und Frontend verwenden anschließend ausschließlich diesen gespeicherten Datenstand.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="altmuehlbuehne_okticket_refresh_events">
                <?php wp_nonce_field('altmuehlbuehne_okticket_refresh_events'); ?>
                <?php submit_button('Daten jetzt aktualisieren', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    private static function render_views_manager(?array $result): void {
        ?>
        <p>Dieser ältere Direktlink fasst die Veranstaltungsübersicht und die einzelnen Veranstaltungsseiten zusammen.</p>
        <?php self::render_shortcode_manager($result); ?>
        <hr>
        <?php self::render_overview_shortcode_manager($result); ?>
        <?php
    }

    public static function save_shortcode(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_save_shortcode');

        $name = isset($_POST['shortcode_name']) ? sanitize_text_field(wp_unslash($_POST['shortcode_name'])) : '';
        $event_ids = isset($_POST['event_ids']) && is_array($_POST['event_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['event_ids'])))))
            : [];
        $event_ids = array_slice($event_ids, 0, 1);
        $fields = isset($_POST['fields']) && is_array($_POST['fields'])
            ? array_values(array_intersect(self::field_keys(), array_map('sanitize_key', wp_unslash($_POST['fields']))))
            : [];

        if ($name === '' || !$event_ids || !$fields) {
            self::redirect_with_notice('shortcode_missing', 'pages');
        }

        $configs = self::get_shortcode_configs();
        $editing_id = isset($_POST['shortcode_id']) ? sanitize_key(wp_unslash($_POST['shortcode_id'])) : '';

        if ($editing_id !== '' && isset($configs[$editing_id])) {
            $id = $editing_id;
        } else {
            $base_id = sanitize_title($name);
            $id = $base_id !== '' ? $base_id : 'veranstaltungen';
            $suffix = 2;
            while (isset($configs[$id])) {
                $id = $base_id . '-' . $suffix++;
            }
        }

        $configs[$id] = [
            'name' => $name,
            'event_ids' => $event_ids,
            'fields' => $fields,
        ];
        update_option(self::OPTION_SHORTCODES, $configs, false);
        self::redirect_with_notice('shortcode_saved', 'pages');
    }

    public static function delete_shortcode(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_delete_shortcode');
        $id = isset($_POST['shortcode_id']) ? sanitize_key(wp_unslash($_POST['shortcode_id'])) : '';
        $configs = self::get_shortcode_configs();

        if ($id !== '' && isset($configs[$id])) {
            unset($configs[$id]);
            update_option(self::OPTION_SHORTCODES, $configs, false);
        }

        self::redirect_with_notice('shortcode_deleted', 'pages');
    }

    public static function save_overview_shortcode(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_save_overview_shortcode');

        $name = isset($_POST['shortcode_name']) ? sanitize_text_field(wp_unslash($_POST['shortcode_name'])) : '';
        $event_ids = isset($_POST['event_ids']) && is_array($_POST['event_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['event_ids'])))))
            : [];
        $fields = isset($_POST['fields']) && is_array($_POST['fields'])
            ? array_values(array_intersect(self::overview_field_keys(), array_map('sanitize_key', wp_unslash($_POST['fields']))))
            : [];

        if ($name === '' || !$event_ids || !$fields) {
            self::redirect_with_notice('shortcode_missing', 'overview');
        }

        $configs = self::get_overview_shortcode_configs();
        $editing_id = isset($_POST['shortcode_id']) ? sanitize_key(wp_unslash($_POST['shortcode_id'])) : '';

        if ($editing_id !== '' && isset($configs[$editing_id])) {
            $id = $editing_id;
        } else {
            $base_id = sanitize_title($name);
            $id = $base_id !== '' ? $base_id : 'veranstaltungen';
            $suffix = 2;
            while (isset($configs[$id])) {
                $id = $base_id . '-' . $suffix++;
            }
        }

        $configs[$id] = [
            'name' => $name,
            'event_ids' => $event_ids,
            'fields' => $fields,
        ];
        $configs[$id]['version'] = 2;
        update_option(self::OPTION_OVERVIEW_SHORTCODES, $configs, false);
        self::redirect_with_notice('shortcode_saved', 'overview');
    }

    public static function delete_overview_shortcode(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
        }

        check_admin_referer('altmuehlbuehne_okticket_delete_overview_shortcode');
        $id = isset($_POST['shortcode_id']) ? sanitize_key(wp_unslash($_POST['shortcode_id'])) : '';
        $configs = self::get_overview_shortcode_configs();

        if ($id !== '' && isset($configs[$id])) {
            unset($configs[$id]);
            update_option(self::OPTION_OVERVIEW_SHORTCODES, $configs, false);
        }

        self::redirect_with_notice('shortcode_deleted', 'overview');
    }

}
