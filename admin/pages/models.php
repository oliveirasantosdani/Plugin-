<?php
/**
 * Page Modeles 3D
 *
 * Gestion :
 * - upload de fichiers GLB
 * - enregistrement en base
 * - activation / desactivation
 * - suppression
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Acces refuse.', 'osds3d-customizer-pro'));
}

$message = '';
$message_type = 'updated';

if (!function_exists('osds3d_get_model_scene_config_from_request')) {
    /**
     * Lire et normaliser la configuration de scene 3D depuis la requete admin.
     *
     * @return array
     */
    function osds3d_get_model_scene_config_from_request()
    {
        $excluded_meshes_raw = isset($_POST['excluded_meshes']) ? sanitize_text_field(wp_unslash($_POST['excluded_meshes'])) : '';
        $excluded_meshes = array_values(array_filter(array_map('trim', explode(',', $excluded_meshes_raw))));

        return array(
            'printable_mesh' => isset($_POST['printable_mesh']) ? sanitize_text_field(wp_unslash($_POST['printable_mesh'])) : '',
            'excluded_meshes' => $excluded_meshes,
            'default_rotation' => array(
                'x' => isset($_POST['rotation_x']) ? floatval(wp_unslash($_POST['rotation_x'])) : 0,
                'y' => isset($_POST['rotation_y']) ? floatval(wp_unslash($_POST['rotation_y'])) : 0,
                'z' => isset($_POST['rotation_z']) ? floatval(wp_unslash($_POST['rotation_z'])) : 0,
            ),
            'camera_distance' => isset($_POST['camera_distance']) ? max(0, floatval(wp_unslash($_POST['camera_distance']))) : 0,
        );
    }
}

if (!function_exists('osds3d_merge_model_dimensions_with_scene_config')) {
    /**
     * Fusionner scene_config dans dimensions sans casser la compatibilite existante.
     *
     * @param mixed $dimensions_payload
     * @param array $scene_config
     * @return array
     */
    function osds3d_merge_model_dimensions_with_scene_config($dimensions_payload, $scene_config)
    {
        $dimensions = is_array($dimensions_payload) ? $dimensions_payload : array();
        $dimensions['scene_config'] = $scene_config;
        return $dimensions;
    }
}

/**
 * Upload d'un modele GLB
 */
if (
    isset($_POST['osds3d_models_nonce']) &&
    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['osds3d_models_nonce'])), 'osds3d_save_model_3d') &&
    isset($_POST['osds3d_model_action']) &&
    sanitize_text_field(wp_unslash($_POST['osds3d_model_action'])) === 'upload'
) {
    $model_name     = isset($_POST['model_name']) ? sanitize_text_field(wp_unslash($_POST['model_name'])) : '';
    $model_category = isset($_POST['model_category']) ? sanitize_text_field(wp_unslash($_POST['model_category'])) : '';
    $sort_order     = isset($_POST['sort_order']) ? intval(wp_unslash($_POST['sort_order'])) : 0;
    $is_active      = isset($_POST['is_active']) ? 1 : 0;
    $scene_config   = osds3d_get_model_scene_config_from_request();

    if (empty($model_name)) {
        $message = __('Veuillez saisir un nom de modele.', 'osds3d-customizer-pro');
        $message_type = 'error';
    } elseif (empty($_FILES['model_file']['name'])) {
        $message = __('Veuillez selectionner un fichier GLB.', 'osds3d-customizer-pro');
        $message_type = 'error';
    } else {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $file = $_FILES['model_file'];
        $filename = isset($file['name']) ? sanitize_file_name($file['name']) : '';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension !== 'glb') {
            $message = __('Seuls les fichiers .glb sont autorises pour le moment.', 'osds3d-customizer-pro');
            $message_type = 'error';
        } else {
            $upload_dir_filter = static function ($dirs) {
                $dirs['subdir'] = '/osds3d-models';
                $dirs['path']   = $dirs['basedir'] . '/osds3d-models';
                $dirs['url']    = $dirs['baseurl'] . '/osds3d-models';
                return $dirs;
            };

            add_filter('upload_dir', $upload_dir_filter);

            $overrides = array(
                'test_form' => false,
                'mimes'     => array(
                    'glb' => 'model/gltf-binary',
                ),
                'unique_filename_callback' => null,
            );

            $uploaded = wp_handle_upload($file, $overrides);

            remove_filter('upload_dir', $upload_dir_filter);

            if (isset($uploaded['error'])) {
                $message = sprintf(
                    __('Erreur lors de l’upload du fichier : %s', 'osds3d-customizer-pro'),
                    $uploaded['error']
                );
                $message_type = 'error';
            } else {
                $model_url  = isset($uploaded['url']) ? esc_url_raw($uploaded['url']) : '';
                $file_path  = isset($uploaded['file']) ? $uploaded['file'] : '';
                $file_size  = (!empty($file_path) && file_exists($file_path)) ? filesize($file_path) : 0;

                $model_id = OSDS3D_Database::add_model_3d(array(
                    'model_name'     => $model_name,
                    'model_url'      => $model_url,
                    'model_format'   => 'glb',
                    'model_category' => $model_category,
                    'file_size'      => $file_size,
                    'dimensions'     => osds3d_merge_model_dimensions_with_scene_config(array(), $scene_config),
                    'is_active'      => $is_active,
                    'sort_order'     => $sort_order,
                ));

                if ($model_id) {
                    $message = __('Modele 3D ajoute avec succes.', 'osds3d-customizer-pro');
                    $message_type = 'updated';
                } else {
                    if (!empty($file_path) && file_exists($file_path)) {
                        @unlink($file_path);
                    }

                    $message = __('Erreur lors de l’enregistrement du modele en base de donnees.', 'osds3d-customizer-pro');
                    $message_type = 'error';
                }
            }
        }
    }
}

/**
 * Mise a jour de la configuration de scene d'un modele existant.
 */
if (
    isset($_POST['osds3d_models_nonce']) &&
    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['osds3d_models_nonce'])), 'osds3d_save_model_3d') &&
    isset($_POST['osds3d_model_action']) &&
    sanitize_text_field(wp_unslash($_POST['osds3d_model_action'])) === 'update_config'
) {
    $model_id = isset($_POST['model_id']) ? intval(wp_unslash($_POST['model_id'])) : 0;
    $model = OSDS3D_Database::get_model_3d($model_id);

    if (!$model) {
        $message = __('Modele introuvable.', 'osds3d-customizer-pro');
        $message_type = 'error';
    } else {
        $scene_config = osds3d_get_model_scene_config_from_request();
        $dimensions = osds3d_merge_model_dimensions_with_scene_config(
            isset($model->dimensions_payload) ? $model->dimensions_payload : array(),
            $scene_config
        );

        $updated = OSDS3D_Database::update_model_3d($model_id, array(
            'dimensions' => $dimensions,
        ));

        if ($updated) {
            $message = __('Configuration 3D mise a jour.', 'osds3d-customizer-pro');
            $message_type = 'updated';
        } else {
            $message = __('Impossible de mettre a jour la configuration 3D.', 'osds3d-customizer-pro');
            $message_type = 'error';
        }
    }
}

/**
 * Activation / desactivation / suppression
 */
if (
    isset($_GET['osds3d_action'], $_GET['model_id'], $_GET['_wpnonce']) &&
    in_array(sanitize_text_field(wp_unslash($_GET['osds3d_action'])), array('toggle_active', 'delete'), true)
) {
    $action   = sanitize_text_field(wp_unslash($_GET['osds3d_action']));
    $model_id = intval(wp_unslash($_GET['model_id']));
    $nonce    = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

    if (wp_verify_nonce($nonce, 'osds3d_model_action_' . $model_id)) {
        $model = OSDS3D_Database::get_model_3d($model_id);

        if ($model) {
            if ($action === 'toggle_active') {
                $new_status = ((int) $model->is_active === 1) ? 0 : 1;

                OSDS3D_Database::update_model_3d($model_id, array(
                    'is_active' => $new_status,
                ));

                $message = $new_status
                    ? __('Modele active.', 'osds3d-customizer-pro')
                    : __('Modele desactive.', 'osds3d-customizer-pro');

                $message_type = 'updated';
            }

            if ($action === 'delete') {
                $deleted = OSDS3D_Database::delete_model_3d($model_id);

                if ($deleted) {
                    $upload_dir = wp_upload_dir();
                    $baseurl    = trailingslashit($upload_dir['baseurl']);
                    $basedir    = trailingslashit($upload_dir['basedir']);

                    if (!empty($model->model_url) && strpos($model->model_url, $baseurl) === 0) {
                        $file_path = str_replace($baseurl, $basedir, $model->model_url);

                        if (file_exists($file_path) && is_file($file_path)) {
                            @unlink($file_path);
                        }
                    }

                    $message = __('Modele supprime.', 'osds3d-customizer-pro');
                    $message_type = 'updated';
                } else {
                    $message = __('Impossible de supprimer le modele.', 'osds3d-customizer-pro');
                    $message_type = 'error';
                }
            }
        } else {
            $message = __('Modele introuvable.', 'osds3d-customizer-pro');
            $message_type = 'error';
        }
    } else {
        $message = __('Nonce invalide.', 'osds3d-customizer-pro');
        $message_type = 'error';
    }
}

$models = OSDS3D_Database::get_models_3d(false);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Modeles 3D', 'osds3d-customizer-pro'); ?></h1>
    <hr class="wp-header-end">

    <?php if (!empty($message)) : ?>
        <div class="notice <?php echo $message_type === 'error' ? 'notice-error' : 'notice-success'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div style="margin-top:20px; background:#fff; padding:20px; border:1px solid #ddd; border-radius:8px; max-width:900px;">
        <h2 style="margin-top:0;"><?php echo esc_html__('Ajouter un modele GLB', 'osds3d-customizer-pro'); ?></h2>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('osds3d_save_model_3d', 'osds3d_models_nonce'); ?>
            <input type="hidden" name="osds3d_model_action" value="upload">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="model_name"><?php echo esc_html__('Nom du modele', 'osds3d-customizer-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="model_name" id="model_name" class="regular-text" required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="model_category"><?php echo esc_html__('Categorie', 'osds3d-customizer-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="model_category" id="model_category" class="regular-text" placeholder="<?php echo esc_attr__('Ex: mugs, t-shirts, plaques...', 'osds3d-customizer-pro'); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="sort_order"><?php echo esc_html__('Ordre', 'osds3d-customizer-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="sort_order" id="sort_order" value="0" min="0" class="small-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="model_file"><?php echo esc_html__('Fichier GLB', 'osds3d-customizer-pro'); ?></label>
                    </th>
                    <td>
                        <input type="file" name="model_file" id="model_file" accept=".glb" required>
                        <p class="description">
                            <?php echo esc_html__('Seuls les fichiers .glb sont acceptes.', 'osds3d-customizer-pro'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Actif', 'osds3d-customizer-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1" checked>
                            <?php echo esc_html__('Rendre ce modele disponible immediatement', 'osds3d-customizer-pro'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="printable_mesh"><?php echo esc_html__('Mesh imprimable', 'osds3d-customizer-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="printable_mesh" id="printable_mesh" class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Nom exact du mesh a utiliser en priorite pour la personnalisation.', 'osds3d-customizer-pro'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="excluded_meshes"><?php echo esc_html__('Meshes a exclure', 'osds3d-customizer-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="excluded_meshes" id="excluded_meshes" class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Optionnel. Noms de meshes separes par des virgules a ignorer pour la surface imprimable.', 'osds3d-customizer-pro'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__('Rotation par defaut', 'osds3d-customizer-pro'); ?></th>
                    <td>
                        <input type="number" name="rotation_x" step="0.01" class="small-text" value="0"> X
                        <input type="number" name="rotation_y" step="0.01" class="small-text" value="0"> Y
                        <input type="number" name="rotation_z" step="0.01" class="small-text" value="0"> Z
                        <p class="description">
                            <?php echo esc_html__('Optionnel. Rotation initiale en radians.', 'osds3d-customizer-pro'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="camera_distance"><?php echo esc_html__('Distance camera', 'osds3d-customizer-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="camera_distance" id="camera_distance" step="0.01" min="0" class="small-text" value="0">
                        <p class="description">
                            <?php echo esc_html__('Optionnel. Laisser 0 pour le cadrage automatique existant.', 'osds3d-customizer-pro'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Ajouter le modele 3D', 'osds3d-customizer-pro')); ?>
        </form>
    </div>

    <div style="margin-top:30px;">
        <h2><?php echo esc_html__('Bibliotheque des modeles 3D', 'osds3d-customizer-pro'); ?></h2>

        <?php if (empty($models)) : ?>
            <p><?php echo esc_html__('Aucun modele 3D enregistre pour le moment.', 'osds3d-customizer-pro'); ?></p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:70px;"><?php echo esc_html__('ID', 'osds3d-customizer-pro'); ?></th>
                        <th><?php echo esc_html__('Nom', 'osds3d-customizer-pro'); ?></th>
                        <th><?php echo esc_html__('Categorie', 'osds3d-customizer-pro'); ?></th>
                        <th style="width:100px;"><?php echo esc_html__('Format', 'osds3d-customizer-pro'); ?></th>
                        <th style="width:120px;"><?php echo esc_html__('Taille', 'osds3d-customizer-pro'); ?></th>
                        <th style="width:100px;"><?php echo esc_html__('Statut', 'osds3d-customizer-pro'); ?></th>
                        <th><?php echo esc_html__('Fichier', 'osds3d-customizer-pro'); ?></th>
                        <th style="width:220px;"><?php echo esc_html__('Actions', 'osds3d-customizer-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($models as $model) : ?>
                        <?php
                        $nonce = wp_create_nonce('osds3d_model_action_' . $model->id);
                        $model_detail = OSDS3D_Database::get_model_3d($model->id);
                        $scene_config = isset($model_detail->scene_config) && is_array($model_detail->scene_config)
                            ? $model_detail->scene_config
                            : array(
                                'printable_mesh' => '',
                                'excluded_meshes' => array(),
                                'default_rotation' => array('x' => 0, 'y' => 0, 'z' => 0),
                                'camera_distance' => 0,
                            );

                        $toggle_url = add_query_arg(
                            array(
                                'page'          => 'osds3d-models',
                                'osds3d_action' => 'toggle_active',
                                'model_id'      => $model->id,
                                '_wpnonce'      => $nonce,
                            ),
                            admin_url('admin.php')
                        );

                        $delete_url = add_query_arg(
                            array(
                                'page'          => 'osds3d-models',
                                'osds3d_action' => 'delete',
                                'model_id'      => $model->id,
                                '_wpnonce'      => $nonce,
                            ),
                            admin_url('admin.php')
                        );

                        $file_size = !empty($model->file_size) ? size_format((int) $model->file_size, 2) : '-';
                        ?>
                        <tr>
                            <td><?php echo intval($model->id); ?></td>
                            <td><strong><?php echo esc_html($model->model_name); ?></strong></td>
                            <td><?php echo esc_html(!empty($model->model_category) ? $model->model_category : '-'); ?></td>
                            <td><?php echo esc_html(strtoupper(!empty($model->model_format) ? $model->model_format : 'glb')); ?></td>
                            <td><?php echo esc_html($file_size); ?></td>
                            <td>
                                <?php if ((int) $model->is_active === 1) : ?>
                                    <span style="color:#16a34a;font-weight:600;"><?php echo esc_html__('Actif', 'osds3d-customizer-pro'); ?></span>
                                <?php else : ?>
                                    <span style="color:#64748b;font-weight:600;"><?php echo esc_html__('Inactif', 'osds3d-customizer-pro'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($model->model_url)) : ?>
                                    <a href="<?php echo esc_url($model->model_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html__('Ouvrir le fichier', 'osds3d-customizer-pro'); ?>
                                    </a>
                                    <br>
                                    <button
                                        type="button"
                                        class="button button-link osds3d-load-meshes"
                                        data-model-url="<?php echo esc_url($model->model_url); ?>"
                                        data-output-id="osds3d-mesh-list-<?php echo intval($model->id); ?>"
                                    >
                                        <?php echo esc_html__('Lister les meshes', 'osds3d-customizer-pro'); ?>
                                    </button>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($toggle_url); ?>" class="button button-secondary">
                                    <?php echo ((int) $model->is_active === 1)
                                        ? esc_html__('Desactiver', 'osds3d-customizer-pro')
                                        : esc_html__('Activer', 'osds3d-customizer-pro'); ?>
                                </a>

                                <a href="<?php echo esc_url($delete_url); ?>" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Supprimer ce modele ?', 'osds3d-customizer-pro')); ?>');">
                                    <?php echo esc_html__('Supprimer', 'osds3d-customizer-pro'); ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="8" style="background:#fcfcfc;">
                                <form method="post" style="padding:12px 0;">
                                    <?php wp_nonce_field('osds3d_save_model_3d', 'osds3d_models_nonce'); ?>
                                    <input type="hidden" name="osds3d_model_action" value="update_config">
                                    <input type="hidden" name="model_id" value="<?php echo intval($model->id); ?>">

                                    <table class="form-table" role="presentation" style="margin:0;">
                                        <tr>
                                            <th scope="row" style="width:180px;">
                                                <label for="printable_mesh_<?php echo intval($model->id); ?>"><?php echo esc_html__('Mesh imprimable', 'osds3d-customizer-pro'); ?></label>
                                            </th>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="printable_mesh"
                                                    id="printable_mesh_<?php echo intval($model->id); ?>"
                                                    class="regular-text"
                                                    value="<?php echo esc_attr(isset($scene_config['printable_mesh']) ? $scene_config['printable_mesh'] : ''); ?>"
                                                >
                                                <p class="description"><?php echo esc_html__('Si renseigne, ce mesh est prioritaire pour recevoir la personnalisation.', 'osds3d-customizer-pro'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="excluded_meshes_<?php echo intval($model->id); ?>"><?php echo esc_html__('Meshes exclus', 'osds3d-customizer-pro'); ?></label>
                                            </th>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="excluded_meshes"
                                                    id="excluded_meshes_<?php echo intval($model->id); ?>"
                                                    class="regular-text"
                                                    value="<?php echo esc_attr(implode(', ', isset($scene_config['excluded_meshes']) ? (array) $scene_config['excluded_meshes'] : array())); ?>"
                                                >
                                                <p class="description"><?php echo esc_html__('Optionnel. Liste separee par des virgules.', 'osds3d-customizer-pro'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php echo esc_html__('Rotation par defaut', 'osds3d-customizer-pro'); ?></th>
                                            <td>
                                                <input type="number" name="rotation_x" step="0.01" class="small-text" value="<?php echo esc_attr(isset($scene_config['default_rotation']['x']) ? $scene_config['default_rotation']['x'] : 0); ?>"> X
                                                <input type="number" name="rotation_y" step="0.01" class="small-text" value="<?php echo esc_attr(isset($scene_config['default_rotation']['y']) ? $scene_config['default_rotation']['y'] : 0); ?>"> Y
                                                <input type="number" name="rotation_z" step="0.01" class="small-text" value="<?php echo esc_attr(isset($scene_config['default_rotation']['z']) ? $scene_config['default_rotation']['z'] : 0); ?>"> Z
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="camera_distance_<?php echo intval($model->id); ?>"><?php echo esc_html__('Distance camera', 'osds3d-customizer-pro'); ?></label>
                                            </th>
                                            <td>
                                                <input
                                                    type="number"
                                                    name="camera_distance"
                                                    id="camera_distance_<?php echo intval($model->id); ?>"
                                                    step="0.01"
                                                    min="0"
                                                    class="small-text"
                                                    value="<?php echo esc_attr(isset($scene_config['camera_distance']) ? $scene_config['camera_distance'] : 0); ?>"
                                                >
                                                <p class="description"><?php echo esc_html__('0 conserve le cadrage automatique.', 'osds3d-customizer-pro'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php echo esc_html__('Meshes detectes', 'osds3d-customizer-pro'); ?></th>
                                            <td>
                                                <div
                                                    id="osds3d-mesh-list-<?php echo intval($model->id); ?>"
                                                    class="osds3d-mesh-list-output"
                                                    style="padding:10px 12px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; min-height:44px;"
                                                >
                                                    <span style="color:#64748b;">
                                                        <?php echo esc_html__('Cliquez sur "Lister les meshes" pour afficher les noms detectes dans ce GLB.', 'osds3d-customizer-pro'); ?>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>

                                    <?php submit_button(__('Enregistrer la configuration 3D', 'osds3d-customizer-pro'), 'secondary', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script type="module">
    const meshButtons = document.querySelectorAll('.osds3d-load-meshes');

    if (meshButtons.length) {
        let threeModulesPromise = null;

        const escapeHtml = (value) => {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const loadThreeModules = async () => {
            if (!threeModulesPromise) {
                threeModulesPromise = Promise.all([
                    import('https://esm.sh/three@0.160.0'),
                    import('https://esm.sh/three@0.160.0/examples/jsm/loaders/GLTFLoader.js'),
                ]).then(([THREE, loaders]) => ({
                    THREE,
                    GLTFLoader: loaders.GLTFLoader,
                }));
            }

            return threeModulesPromise;
        };

        const renderMeshList = (output, meshes) => {
            if (!meshes.length) {
                output.innerHTML = '<span style="color:#b45309;"><?php echo esc_js(__('Aucun mesh detecte dans ce modele.', 'osds3d-customizer-pro')); ?></span>';
                return;
            }

            const items = meshes.map((mesh) => {
                return '<li><code>' + escapeHtml(mesh) + '</code></li>';
            }).join('');

            output.innerHTML =
                '<div style="margin-bottom:8px; font-weight:600;"><?php echo esc_js(__('Meshes detectes :', 'osds3d-customizer-pro')); ?></div>' +
                '<ul style="margin:0; padding-left:18px;">' + items + '</ul>';
        };

        meshButtons.forEach((button) => {
            button.addEventListener('click', async () => {
                const modelUrl = button.getAttribute('data-model-url');
                const outputId = button.getAttribute('data-output-id');
                const output = outputId ? document.getElementById(outputId) : null;

                if (!modelUrl || !output) {
                    return;
                }

                button.disabled = true;
                output.innerHTML = '<span style="color:#64748b;"><?php echo esc_js(__('Lecture du modele en cours...', 'osds3d-customizer-pro')); ?></span>';

                try {
                    const { GLTFLoader } = await loadThreeModules();
                    const loader = new GLTFLoader();

                    loader.load(
                        modelUrl,
                        (gltf) => {
                            const object = gltf && gltf.scene ? gltf.scene : null;
                            const meshNames = [];

                            if (object) {
                                object.traverse((child) => {
                                    if (!child || !child.isMesh) {
                                        return;
                                    }

                                    const meshName = String(child.name || '').trim();
                                    meshNames.push(meshName || '(sans nom)');
                                });
                            }

                            const uniqueMeshNames = Array.from(new Set(meshNames));
                            renderMeshList(output, uniqueMeshNames);
                            button.disabled = false;
                        },
                        undefined,
                        (error) => {
                            console.error('[OSDS3D Admin]', error);
                            output.innerHTML = '<span style="color:#b91c1c;"><?php echo esc_js(__('Impossible de lire ce GLB ou de detecter ses meshes.', 'osds3d-customizer-pro')); ?></span>';
                            button.disabled = false;
                        }
                    );
                } catch (error) {
                    console.error('[OSDS3D Admin]', error);
                    output.innerHTML = '<span style="color:#b91c1c;"><?php echo esc_js(__('Le chargement du lecteur de meshes a echoue.', 'osds3d-customizer-pro')); ?></span>';
                    button.disabled = false;
                }
            });
        });
    }
</script>
