<?php
// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}
// Récupérer quelques statistiques simples
global $wpdb;
$table = $wpdb->prefix . 'osds3d_designs';
$design_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
?>
<div class="wrap">
    <h1><?php echo esc_html__('Tableau de bord OSDS3D', 'osds3d-customizer-pro'); ?></h1>
    <p><?php echo esc_html__('Bienvenue sur le tableau de bord du plugin OSDS3D Customizer Pro. Vous trouverez ci‑dessous quelques statistiques.', 'osds3d-customizer-pro'); ?></p>
    <div style="margin-top:20px;padding:20px;background:#fff;border:1px solid #eee;border-radius:6px;max-width:400px;">
        <h2 style="margin-top:0;">
            <?php echo esc_html__('Total des designs enregistrés', 'osds3d-customizer-pro'); ?>
        </h2>
        <p style="font-size:32px;font-weight:bold;color:#0073aa;margin:0;">
            <?php echo esc_html($design_count); ?>
        </p>
    </div>
</div>