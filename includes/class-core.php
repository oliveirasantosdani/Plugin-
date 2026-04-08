<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principale du cœur du plugin.
 */
class OSDS3D_Core
{
    /**
     * Initialiser tous les modules du plugin.
     *
     * @return void
     */
    public static function init()
    {
        if (class_exists('OSDS3D_Settings')) {
            OSDS3D_Settings::init();
        }

        if (class_exists('OSDS3D_Database')) {
            OSDS3D_Database::init();
        }

        if (class_exists('OSDS3D_Logger')) {
            OSDS3D_Logger::init();
        }

        if (class_exists('OSDS3D_Ajax')) {
            OSDS3D_Ajax::init();
        }

        if (class_exists('OSDS3D_Customizer')) {
            OSDS3D_Customizer::init();
        }

        if (class_exists('OSDS3D_WooCommerce')) {
            OSDS3D_WooCommerce::init();
        }

        if (class_exists('OSDS3D_Email')) {
            OSDS3D_Email::init();
        }

        if (class_exists('OSDS3D_Admin')) {
            OSDS3D_Admin::init();
        }
    }
}