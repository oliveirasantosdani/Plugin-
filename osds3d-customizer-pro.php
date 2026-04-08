<?php
/**
 * Plugin Name: OSDS3D Customizer Pro
 * Plugin URI:  https://osds3d.ch
 * Description: Plugin de personnalisation de produits 2D/3D pour WooCommerce.
 * Version:     0.1.0
 * Author:      OSDS3D
 * Author URI:  https://osds3d.ch
 * Text Domain: osds3d-customizer-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Éviter le chargement multiple.
 */
if (defined('OSDS3D_PRO_VERSION')) {
    return;
}

/**
 * Constantes plugin
 */
define('OSDS3D_PRO_VERSION', '0.1.0');
define('OSDS3D_PRO_PLUGIN_FILE', __FILE__);
define('OSDS3D_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OSDS3D_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OSDS3D_PRO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Chargement des fichiers du plugin
 */
require_once OSDS3D_PRO_PLUGIN_DIR . 'includes/class-core.php';
require_once OSDS3D_PRO_PLUGIN_DIR . 'includes/class-database.php';
require_once OSDS3D_PRO_PLUGIN_DIR . 'includes/class-logger.php';
require_once OSDS3D_PRO_PLUGIN_DIR . 'includes/class-ajax.php';
require_once OSDS3D_PRO_PLUGIN_DIR . 'includes/class-woocommerce.php';
require_once OSDS3D_PRO_PLUGIN_DIR . 'includes/class-email.php';
require_once OSDS3D_PRO_PLUGIN_DIR . 'includes/class-settings.php';
require_once OSDS3D_PRO_PLUGIN_DIR . 'admin/class-admin.php';
require_once OSDS3D_PRO_PLUGIN_DIR . 'public/class-customizer.php';

/**
 * Charger les traductions.
 *
 * @return void
 */
function osds3d_pro_load_textdomain()
{
    load_plugin_textdomain(
        'osds3d-customizer-pro',
        false,
        dirname(OSDS3D_PRO_PLUGIN_BASENAME) . '/languages'
    );
}

/**
 * Vérifie si WooCommerce est actif.
 *
 * @return bool
 */
function osds3d_pro_is_woocommerce_active()
{
    return class_exists('WooCommerce');
}

/**
 * Notice admin si WooCommerce manque.
 *
 * @return void
 */
function osds3d_pro_missing_woocommerce_notice()
{
    echo '<div class="notice notice-error"><p><strong>OSDS3D Customizer Pro</strong> ' .
        esc_html__('requiert WooCommerce. Veuillez activer WooCommerce pour utiliser ce plugin.', 'osds3d-customizer-pro') .
        '</p></div>';
}

/**
 * Initialisation du plugin.
 *
 * @return void
 */
function osds3d_pro_init()
{
    osds3d_pro_load_textdomain();

    if (!osds3d_pro_is_woocommerce_active()) {
        if (is_admin()) {
            add_action('admin_notices', 'osds3d_pro_missing_woocommerce_notice');
        }
        return;
    }

    if (class_exists('OSDS3D_Core')) {
        OSDS3D_Core::init();
    }
}
add_action('plugins_loaded', 'osds3d_pro_init');

/**
 * Activation du plugin.
 *
 * @return void
 */
function osds3d_pro_activate()
{
    if (class_exists('OSDS3D_Database')) {
        OSDS3D_Database::create_tables();
    }

    if (class_exists('OSDS3D_Settings')) {
        OSDS3D_Settings::set_defaults();
    }
}
register_activation_hook(__FILE__, 'osds3d_pro_activate');

/**
 * Désactivation du plugin.
 *
 * @return void
 */
function osds3d_pro_deactivate()
{
    // Réservé pour futurs nettoyages légers si nécessaire.
}
register_deactivation_hook(__FILE__, 'osds3d_pro_deactivate');