<?php
/**
 * Interface d'administration pour OSDS3D Customizer Pro
 *
 * Gère :
 * - le menu admin
 * - le chargement des assets admin
 * - l'affichage des pages du plugin
 *
 * @package OSDS3D_Customizer_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSDS3D_Admin
{
    /**
     * Slug principal du menu
     *
     * @var string
     */
    const MENU_SLUG = 'osds3d-dashboard';

    /**
     * Capacité requise
     *
     * @var string
     */
    const CAPABILITY = 'manage_options';

    /**
     * Initialiser l'administration du plugin.
     *
     * @return void
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    /**
     * Enregistrer les pages de menu du plugin dans l'admin.
     *
     * @return void
     */
    public static function register_menu()
    {
        add_menu_page(
            __('OSDS3D', 'osds3d-customizer-pro'),
            'OSDS3D',
            self::CAPABILITY,
            self::MENU_SLUG,
            array(__CLASS__, 'render_dashboard_page'),
            'dashicons-art',
            56
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Tableau de bord', 'osds3d-customizer-pro'),
            __('Tableau de bord', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-dashboard',
            array(__CLASS__, 'render_dashboard_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Designs', 'osds3d-customizer-pro'),
            __('Designs', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-designs',
            array(__CLASS__, 'render_designs_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Templates', 'osds3d-customizer-pro'),
            __('Templates', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-templates',
            array(__CLASS__, 'render_templates_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Cliparts', 'osds3d-customizer-pro'),
            __('Cliparts', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-cliparts',
            array(__CLASS__, 'render_cliparts_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Polices', 'osds3d-customizer-pro'),
            __('Polices', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-fonts',
            array(__CLASS__, 'render_fonts_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Formes', 'osds3d-customizer-pro'),
            __('Formes', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-shapes',
            array(__CLASS__, 'render_shapes_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Modèles 3D', 'osds3d-customizer-pro'),
            __('Modèles 3D', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-models',
            array(__CLASS__, 'render_models_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Historique', 'osds3d-customizer-pro'),
            __('Historique', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-logs',
            array(__CLASS__, 'render_logs_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Paramètres', 'osds3d-customizer-pro'),
            __('Paramètres', 'osds3d-customizer-pro'),
            self::CAPABILITY,
            'osds3d-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Vérifie si on est sur une page admin du plugin.
     *
     * @param string $hook_suffix
     * @return bool
     */
    protected static function is_osds3d_admin_page($hook_suffix)
    {
        if (empty($hook_suffix)) {
            return false;
        }

        return strpos($hook_suffix, 'osds3d') !== false;
    }

    /**
     * Retourne une version d'asset basée sur filemtime si possible.
     *
     * @param string $relative_path
     * @return string
     */
    protected static function get_asset_version($relative_path)
    {
        if (!defined('OSDS3D_PRO_PLUGIN_DIR')) {
            return defined('OSDS3D_PRO_VERSION') ? OSDS3D_PRO_VERSION : '1.0.0';
        }

        $full_path = OSDS3D_PRO_PLUGIN_DIR . ltrim($relative_path, '/');

        if (file_exists($full_path)) {
            return (string) filemtime($full_path);
        }

        return defined('OSDS3D_PRO_VERSION') ? OSDS3D_PRO_VERSION : '1.0.0';
    }

    /**
     * Charger les ressources CSS/JS spécifiques aux pages du plugin.
     *
     * @param string $hook_suffix
     * @return void
     */
    public static function enqueue_assets($hook_suffix)
    {
        if (!self::is_osds3d_admin_page($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            'osds3d-admin-style',
            OSDS3D_PRO_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            self::get_asset_version('admin/css/admin-style.css')
        );

        wp_enqueue_script(
            'osds3d-admin-script',
            OSDS3D_PRO_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery'),
            self::get_asset_version('admin/js/admin-script.js'),
            true
        );

        wp_localize_script(
            'osds3d-admin-script',
            'osds3d_admin_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('osds3d_admin_nonce'),
            )
        );
    }

    /**
     * Inclure une page admin de façon sécurisée.
     *
     * @param string $filename
     * @return void
     */
    protected static function render_page($filename)
    {
        $file = OSDS3D_PRO_PLUGIN_DIR . 'admin/pages/' . $filename;

        if (file_exists($file)) {
            include $file;
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('OSDS3D', 'osds3d-customizer-pro') . '</h1>';
        echo '<div class="notice notice-error"><p>';
        echo esc_html(sprintf(__('Fichier admin introuvable : %s', 'osds3d-customizer-pro'), $filename));
        echo '</p></div>';
        echo '</div>';
    }

    /**
     * Afficher la page Tableau de bord.
     *
     * @return void
     */
    public static function render_dashboard_page()
    {
        self::render_page('dashboard.php');
    }

    /**
     * Afficher la page Designs.
     *
     * @return void
     */
    public static function render_designs_page()
    {
        self::render_page('designs.php');
    }

    /**
     * Afficher la page Paramètres.
     *
     * @return void
     */
    public static function render_settings_page()
    {
        self::render_page('settings.php');
    }

    /**
     * Afficher la page Templates.
     *
     * @return void
     */
    public static function render_templates_page()
    {
        self::render_page('templates.php');
    }

    /**
     * Afficher la page Cliparts.
     *
     * @return void
     */
    public static function render_cliparts_page()
    {
        self::render_page('cliparts.php');
    }

    /**
     * Afficher la page Polices.
     *
     * @return void
     */
    public static function render_fonts_page()
    {
        self::render_page('fonts.php');
    }

    /**
     * Afficher la page Formes.
     *
     * @return void
     */
    public static function render_shapes_page()
    {
        self::render_page('shapes.php');
    }

    /**
     * Afficher la page Modèles 3D.
     *
     * @return void
     */
    public static function render_models_page()
    {
        self::render_page('models.php');
    }

    /**
     * Afficher la page Logs.
     *
     * @return void
     */
    public static function render_logs_page()
    {
        self::render_page('logs.php');
    }
}