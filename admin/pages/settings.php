<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Vous n’avez pas l’autorisation d’accéder à cette page.', 'osds3d-customizer-pro'));
}

$notice = '';

if (
    isset($_POST['osds3d_settings_nonce']) &&
    wp_verify_nonce(wp_unslash($_POST['osds3d_settings_nonce']), 'osds3d_save_settings')
) {
    OSDS3D_Settings::set(
        'customizer_page_id',
        isset($_POST['customizer_page_id']) ? absint(wp_unslash($_POST['customizer_page_id'])) : 0
    );

    OSDS3D_Settings::set(
        'admin_email',
        isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : get_option('admin_email')
    );

    OSDS3D_Settings::set(
        'email_from_name',
        isset($_POST['email_from_name']) ? sanitize_text_field(wp_unslash($_POST['email_from_name'])) : get_option('blogname')
    );

    OSDS3D_Settings::set(
        'email_from_email',
        isset($_POST['email_from_email']) ? sanitize_email(wp_unslash($_POST['email_from_email'])) : get_option('admin_email')
    );

    OSDS3D_Settings::set(
        'log_actions',
        isset($_POST['log_actions']) ? 1 : 0
    );

    $notice = __('Paramètres sauvegardés.', 'osds3d-customizer-pro');
}

$options = OSDS3D_Settings::get_all();
?>
<div class="wrap">
    <h1><?php echo esc_html__('Paramètres OSDS3D', 'osds3d-customizer-pro'); ?></h1>

    <?php if (!empty($notice)) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($notice); ?></p>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('osds3d_save_settings', 'osds3d_settings_nonce'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="customizer_page_id">
                        <?php echo esc_html__('Page du customizer', 'osds3d-customizer-pro'); ?>
                    </label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name'              => 'customizer_page_id',
                        'id'                => 'customizer_page_id',
                        'selected'          => absint($options['customizer_page_id']),
                        'show_option_none'  => esc_html__('-- Sélectionner une page --', 'osds3d-customizer-pro'),
                        'option_none_value' => '0',
                    ));
                    ?>
                    <p class="description">
                        <?php echo esc_html__('Choisissez une page existante qui contiendra le shortcode [osds3d_customizer].', 'osds3d-customizer-pro'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="admin_email">
                        <?php echo esc_html__('Email administrateur', 'osds3d-customizer-pro'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="email"
                        id="admin_email"
                        name="admin_email"
                        value="<?php echo esc_attr($options['admin_email']); ?>"
                        class="regular-text"
                    />
                    <p class="description">
                        <?php echo esc_html__('Adresse qui recevra les notifications de nouveaux designs.', 'osds3d-customizer-pro'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="email_from_name">
                        <?php echo esc_html__('Nom de l’expéditeur', 'osds3d-customizer-pro'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="email_from_name"
                        name="email_from_name"
                        value="<?php echo esc_attr($options['email_from_name']); ?>"
                        class="regular-text"
                    />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="email_from_email">
                        <?php echo esc_html__('Email de l’expéditeur', 'osds3d-customizer-pro'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="email"
                        id="email_from_email"
                        name="email_from_email"
                        value="<?php echo esc_attr($options['email_from_email']); ?>"
                        class="regular-text"
                    />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="log_actions">
                        <?php echo esc_html__('Activer le log', 'osds3d-customizer-pro'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        id="log_actions"
                        name="log_actions"
                        value="1"
                        <?php checked(!empty($options['log_actions']), true); ?>
                    />
                    <p class="description">
                        <?php echo esc_html__('Si coché, les actions du plugin seront enregistrées pour débogage.', 'osds3d-customizer-pro'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Enregistrer les changements', 'osds3d-customizer-pro')); ?>
    </form>
</div>