<?php
/**
 * Plugin Name: Ninimma Connect
 * Description: Łączy stronę z panelem agencji Ninimma: wspólne logowanie klienta i przycisk „Zgłoś problem”.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Author: Ninimma
 * License: MIT
 * Text Domain: ninimma-connect
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Ninimma_Connect
{
    const OPTION = 'ninimma_connect';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'register']);
        add_action('admin_bar_menu', [self::class, 'admin_bar'], 90);
        add_action('admin_post_ninimma_sso', [self::class, 'sso']);
        add_shortcode('ninimma_zglos', [self::class, 'shortcode']);
    }

    private static function opt(string $key): string
    {
        $o = get_option(self::OPTION, []);

        return isset($o[$key]) ? (string) $o[$key] : '';
    }

    private static function configured(): bool
    {
        return self::opt('panel_url') !== '' && self::opt('secret') !== '';
    }

    /** Kto może zgłaszać: domyślnie redaktorzy i administratorzy strony. */
    private static function allowed(): bool
    {
        return is_user_logged_in() && current_user_can(apply_filters('ninimma_connect_capability', 'edit_posts'));
    }

    public static function menu(): void
    {
        add_options_page('Ninimma Connect', 'Ninimma Connect', 'manage_options', 'ninimma-connect', [self::class, 'page']);
    }

    public static function register(): void
    {
        register_setting('ninimma_connect', self::OPTION, [
            'sanitize_callback' => function ($in) {
                return [
                    'panel_url' => esc_url_raw(rtrim((string) ($in['panel_url'] ?? ''), '/')),
                    'secret' => sanitize_text_field((string) ($in['secret'] ?? '')),
                ];
            },
        ]);
    }

    public static function page(): void
    {
        ?>
        <div class="wrap">
            <h1>Ninimma Connect</h1>
            <p>Adres panelu i sekret znajdziesz w panelu agencji: <em>Strony i SEO → strona → Wtyczka WordPress</em>.</p>
            <form method="post" action="options.php">
                <?php settings_fields('ninimma_connect'); ?>
                <table class="form-table">
                    <tr><th><label for="nc-url">Adres panelu</label></th>
                        <td><input id="nc-url" type="url" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[panel_url]" value="<?php echo esc_attr(self::opt('panel_url')); ?>" placeholder="https://panel.twojaagencja.pl"></td></tr>
                    <tr><th><label for="nc-secret">Sekret</label></th>
                        <td><input id="nc-secret" type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION); ?>[secret]" value="<?php echo esc_attr(self::opt('secret')); ?>"></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <p>Przycisk na stronie: shortcode <code>[ninimma_zglos]</code>. W pasku administratora pojawia się pozycja „Zgłoś problem”.</p>
        </div>
        <?php
    }

    /** Link do akcji SSO (z nonce WordPressa), z adresem strony, na której był użytkownik. */
    private static function link(string $target, string $page = ''): string
    {
        return wp_nonce_url(add_query_arg([
            'action' => 'ninimma_sso',
            'target' => rawurlencode($target),
            'page_url' => rawurlencode($page),
        ], admin_url('admin-post.php')), 'ninimma_sso');
    }

    public static function admin_bar(WP_Admin_Bar $bar): void
    {
        if (! self::configured() || ! self::allowed()) {
            return;
        }
        $page = is_admin() ? '' : home_url(add_query_arg([]));
        $bar->add_node(['id' => 'ninimma', 'title' => 'Zgłoś problem', 'href' => self::link('report', $page), 'meta' => ['target' => '_blank']]);
        $bar->add_node(['id' => 'ninimma-panel', 'parent' => 'ninimma', 'title' => 'Otwórz panel agencji', 'href' => self::link('panel'), 'meta' => ['target' => '_blank']]);
    }

    public static function shortcode($atts): string
    {
        if (! self::configured() || ! self::allowed()) {
            return '';
        }
        $a = shortcode_atts(['tekst' => 'Zgłoś problem'], $atts);

        return '<a class="ninimma-zglos button" target="_blank" rel="noopener" href="'.esc_url(self::link('report', home_url(add_query_arg([])))).'">'.esc_html($a['tekst']).'</a>';
    }

    /** Podpisuje link HMAC-SHA256 i przekierowuje do panelu (ważny 5 minut, jednorazowy). */
    public static function sso(): void
    {
        if (! self::allowed() || ! self::configured()) {
            wp_die('Brak dostępu.', 403);
        }
        check_admin_referer('ninimma_sso');
        $target = rawurldecode((string) ($_GET['target'] ?? 'panel'));
        $page = esc_url_raw(rawurldecode((string) ($_GET['page_url'] ?? '')));
        $redirect = $target === 'report'
            ? '/tickets/create?'.http_build_query(['type' => 'bug', 'page' => $page])
            : '/';

        $site = wp_parse_url(home_url(), PHP_URL_HOST);
        $site = preg_replace('/^www\./', '', (string) $site);
        $email = strtolower(wp_get_current_user()->user_email);
        $ts = time();
        $nonce = bin2hex(random_bytes(16));
        $sig = hash_hmac('sha256', implode('|', [$site, $email, $ts, $nonce, $redirect]), self::opt('secret'));

        wp_redirect(self::opt('panel_url').'/sso/wordpress?'.http_build_query(compact('site', 'email', 'ts', 'nonce', 'redirect', 'sig')));
        exit;
    }
}

Ninimma_Connect::boot();
