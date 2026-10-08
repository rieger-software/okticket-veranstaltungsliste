<?php
defined('ABSPATH') || exit;

trait Altmuehlbuehne_OKTicket_Update_Client_Trait {
    public static function check_for_updates($update, array $plugin_data, string $plugin_file, array $locales) {
        unset($locales);

        if ($plugin_file !== plugin_basename(self::PLUGIN_FILE)) {
            return $update;
        }

        $release = self::get_latest_github_release();
        $version = is_array($release) ? self::get_release_version($release) : '';
        $package = is_array($release) && $version !== '' ? self::get_release_package($release, $version) : '';

        if (
            $version === ''
            || $package === ''
            || version_compare($version, (string) $plugin_data['Version'], '<=')
        ) {
            return $update;
        }

        return [
            'id' => self::UPDATE_URI,
            'slug' => 'okticket-veranstaltungsliste',
            'version' => $version,
            'url' => self::UPDATE_URI,
            'package' => $package,
            'tested' => '',
            'requires' => '6.4',
            'requires_php' => '7.4',
        ];
    }

    /**
     * Supplies the native WordPress plugin-details modal.
     *
     * @param false|object|array $result Existing API result.
     * @param string $action Requested API action.
     * @param object $args Request arguments.
     * @return false|object|array
     */
    public static function get_plugin_information($result, string $action, $args) {
        if (
            $action !== 'plugin_information'
            || !is_object($args)
            || !isset($args->slug)
            || $args->slug !== 'okticket-veranstaltungsliste'
        ) {
            return $result;
        }

        $release = self::get_latest_github_release();
        $version = is_array($release) ? self::get_release_version($release) : '';
        if ($version === '') {
            return $result;
        }

        $body = isset($release['body']) ? (string) $release['body'] : '';
        $published_at = isset($release['published_at']) ? sanitize_text_field((string) $release['published_at']) : '';

        return (object) [
            'name' => 'OKTicket Veranstaltungsliste',
            'slug' => 'okticket-veranstaltungsliste',
            'version' => $version,
            'author' => '<a href="https://okticket.de/">okticket.de</a>',
            'homepage' => 'https://okticket.de/',
            'requires' => '6.4',
            'requires_php' => '7.4',
            'last_updated' => $published_at,
            'download_link' => self::get_release_package($release, $version),
            'sections' => [
                'description' => '<p>Verbindet WordPress mit der OKTicket-API und stellt Veranstaltungsdaten als Blöcke bereit.</p>',
                'changelog' => wpautop(esc_html($body !== '' ? $body : 'Keine Änderungsnotizen verfügbar.')),
            ],
        ];
    }

    /**
     * Retrieves the latest stable release metadata from GitHub.
     *
     * @return array<string, mixed>|null
     */
    private static function get_latest_github_release(): ?array {
        $response = wp_remote_get(self::GITHUB_RELEASE_API_URL, [
            'timeout' => 5,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'OKTicket-Veranstaltungsliste',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (
            !is_array($release)
            || !empty($release['draft'])
            || !empty($release['prerelease'])
        ) {
            return null;
        }

        return $release;
    }

    /**
     * Converts a GitHub tag such as v0.2.1 to a WordPress plugin version.
     */
    private static function get_release_version(array $release): string {
        $tag = isset($release['tag_name']) ? trim((string) $release['tag_name']) : '';
        $version = preg_replace('/^v/i', '', $tag);

        if (!is_string($version) || !preg_match('/^[0-9][0-9A-Za-z.+_-]*$/', $version)) {
            return '';
        }

        return $version;
    }

    /**
     * Returns only the explicitly uploaded, versioned WordPress plugin ZIP.
     */
    private static function get_release_package(array $release, string $version): string {
        $expected_name = 'okticket-veranstaltungsliste-' . $version . '.zip';
        $assets = isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : [];

        foreach ($assets as $asset) {
            if (!is_array($asset) || ($asset['name'] ?? '') !== $expected_name) {
                continue;
            }

            $url = isset($asset['browser_download_url']) ? esc_url_raw((string) $asset['browser_download_url']) : '';
            if (
                $url !== ''
                && wp_parse_url($url, PHP_URL_SCHEME) === 'https'
                && wp_parse_url($url, PHP_URL_HOST) === 'github.com'
            ) {
                return $url;
            }
        }

        return '';
    }

}
