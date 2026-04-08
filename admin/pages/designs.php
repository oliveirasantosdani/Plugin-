<?php
// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}
// Récupérer la liste des designs (limiter à 100 pour éviter les gros tableaux)
global $wpdb;
$table   = $wpdb->prefix . 'osds3d_designs';
$designs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
?>
<div class="wrap">
    <h1><?php echo esc_html__('Designs enregistrés', 'osds3d-customizer-pro'); ?></h1>
    <?php if (empty($designs)) : ?>
        <p><?php echo esc_html__('Aucun design n\'a encore été enregistré.', 'osds3d-customizer-pro'); ?></p>
    <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('ID', 'osds3d-customizer-pro'); ?></th>
                    <th><?php echo esc_html__('Produit', 'osds3d-customizer-pro'); ?></th>
                    <th><?php echo esc_html__('Utilisateur', 'osds3d-customizer-pro'); ?></th>
                    <th><?php echo esc_html__('Statut', 'osds3d-customizer-pro'); ?></th>
                    <th><?php echo esc_html__('Créé le', 'osds3d-customizer-pro'); ?></th>
                    <th><?php echo esc_html__('Actions', 'osds3d-customizer-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($designs as $design) : ?>
                    <tr>
                        <td><?php echo esc_html($design->id); ?></td>
                        <td><?php echo esc_html($design->product_id); ?></td>
                        <td><?php echo esc_html($design->user_id ? get_userdata($design->user_id)->display_name : __('Invité', 'osds3d-customizer-pro')); ?></td>
                        <td><?php echo esc_html($design->status); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($design->created_at))); ?></td>
                        <td>
                            <?php if (!empty($design->preview_url)) : ?>
                                <a href="<?php echo esc_url($design->preview_url); ?>" target="_blank" class="button button-small">
                                    <?php echo esc_html__('Voir', 'osds3d-customizer-pro'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>