<?php
/**
 * Plugin Name: OSDS3D - Epoxy Workflow V1
 * Description: Intégration V1 epoxy alignée sur les conventions OSDS3D existantes.
 * Version: 0.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class OSDS3D_Epoxy_Workflow_V1 {
    private const META_BUSINESS_FAMILY = '_osds3d_business_family';
    private const META_PERSONALIZATION_MODE = '_osds3d_personalization_mode';
    private const META_PRODUCT_TYPE = '_osds3d_product_type';

    private const META_ALLOWED_MATERIALS = '_osds3d_epoxy_allowed_materials';
    private const META_ALLOWED_COLORS = '_osds3d_epoxy_allowed_colors';
    private const META_ALLOWED_EFFECTS = '_osds3d_epoxy_allowed_effects';
    private const META_ALLOWED_FINISHES = '_osds3d_epoxy_allowed_finishes';
    private const META_ALLOW_CUSTOM_TEXT = '_osds3d_epoxy_allow_custom_text';
    private const META_ALLOW_UPLOAD = '_osds3d_epoxy_allow_upload';
    private const META_DEFAULT_WORKSHOP_NOTE = '_osds3d_epoxy_default_workshop_note';

    private const PERSONALIZATION_KEY = '_osds3d_personalization';

    public static function bootstrap(): void {
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'render_product_fields']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_product_fields']);

        add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'render_front_form']);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_front_form'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'attach_cart_item_data'], 20, 2);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'render_cart_item_data'], 20, 2);

        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'persist_order_item_meta'], 20, 4);
        add_action('woocommerce_before_order_itemmeta', [__CLASS__, 'render_admin_order_item_meta'], 20, 3);
    }

    private static function is_epoxy_workflow(int $product_id): bool {
        $family = (string) get_post_meta($product_id, self::META_BUSINESS_FAMILY, true);
        $mode = (string) get_post_meta($product_id, self::META_PERSONALIZATION_MODE, true);
        $type = (string) get_post_meta($product_id, self::META_PRODUCT_TYPE, true);

        if ($type === '') {
            $product = wc_get_product($product_id);
            $type = $product ? $product->get_type() : '';
        }

        return $family === 'epoxy' && $mode === 'form' && $type === 'simple';
    }

    private static function explode_csv(string $value): array {
        $items = array_map('trim', explode(',', $value));
        return array_values(array_filter($items, static fn($item) => $item !== ''));
    }

    public static function render_product_fields(): void {
        global $post;

        $family = (string) get_post_meta($post->ID, self::META_BUSINESS_FAMILY, true);
        if ($family !== 'epoxy') {
            return;
        }

        echo '<div class="options_group show_if_simple">';

        woocommerce_wp_text_input([
            'id' => self::META_ALLOWED_MATERIALS,
            'label' => __('OSDS3D Epoxy: matériaux autorisés', 'osds3d'),
            'description' => __('Valeurs séparées par virgules.', 'osds3d'),
            'desc_tip' => true,
            'value' => (string) get_post_meta($post->ID, self::META_ALLOWED_MATERIALS, true),
        ]);

        woocommerce_wp_text_input([
            'id' => self::META_ALLOWED_COLORS,
            'label' => __('OSDS3D Epoxy: couleurs autorisées', 'osds3d'),
            'description' => __('Valeurs séparées par virgules.', 'osds3d'),
            'desc_tip' => true,
            'value' => (string) get_post_meta($post->ID, self::META_ALLOWED_COLORS, true),
        ]);

        woocommerce_wp_text_input([
            'id' => self::META_ALLOWED_EFFECTS,
            'label' => __('OSDS3D Epoxy: effets autorisés', 'osds3d'),
            'description' => __('V1: texte simple séparé par virgules.', 'osds3d'),
            'desc_tip' => true,
            'value' => (string) get_post_meta($post->ID, self::META_ALLOWED_EFFECTS, true),
        ]);

        woocommerce_wp_text_input([
            'id' => self::META_ALLOWED_FINISHES,
            'label' => __('OSDS3D Epoxy: finitions autorisées', 'osds3d'),
            'description' => __('V1: texte simple séparé par virgules.', 'osds3d'),
            'desc_tip' => true,
            'value' => (string) get_post_meta($post->ID, self::META_ALLOWED_FINISHES, true),
        ]);

        woocommerce_wp_checkbox([
            'id' => self::META_ALLOW_CUSTOM_TEXT,
            'label' => __('OSDS3D Epoxy: texte personnalisé autorisé', 'osds3d'),
            'value' => get_post_meta($post->ID, self::META_ALLOW_CUSTOM_TEXT, true) === 'yes' ? 'yes' : 'no',
        ]);

        woocommerce_wp_checkbox([
            'id' => self::META_ALLOW_UPLOAD,
            'label' => __('OSDS3D Epoxy: upload autorisé', 'osds3d'),
            'value' => get_post_meta($post->ID, self::META_ALLOW_UPLOAD, true) === 'yes' ? 'yes' : 'no',
        ]);

        woocommerce_wp_textarea_input([
            'id' => self::META_DEFAULT_WORKSHOP_NOTE,
            'label' => __('OSDS3D Epoxy: note atelier par défaut', 'osds3d'),
            'desc_tip' => true,
            'value' => (string) get_post_meta($post->ID, self::META_DEFAULT_WORKSHOP_NOTE, true),
        ]);

        echo '</div>';
    }

    public static function save_product_fields($product): void {
        if ((string) $product->get_meta(self::META_BUSINESS_FAMILY, true) !== 'epoxy') {
            return;
        }

        $text_fields = [
            self::META_ALLOWED_MATERIALS,
            self::META_ALLOWED_COLORS,
            self::META_ALLOWED_EFFECTS,
            self::META_ALLOWED_FINISHES,
            self::META_DEFAULT_WORKSHOP_NOTE,
        ];

        foreach ($text_fields as $field) {
            $value = isset($_POST[$field]) ? wp_kses_post(wp_unslash((string) $_POST[$field])) : '';
            $product->update_meta_data($field, $value);
        }

        $product->update_meta_data(self::META_ALLOW_CUSTOM_TEXT, isset($_POST[self::META_ALLOW_CUSTOM_TEXT]) ? 'yes' : 'no');
        $product->update_meta_data(self::META_ALLOW_UPLOAD, isset($_POST[self::META_ALLOW_UPLOAD]) ? 'yes' : 'no');
    }

    public static function render_front_form(): void {
        global $product;

        if (!$product || !self::is_epoxy_workflow($product->get_id())) {
            return;
        }

        $pid = $product->get_id();
        $materials = self::explode_csv((string) get_post_meta($pid, self::META_ALLOWED_MATERIALS, true));
        $colors = self::explode_csv((string) get_post_meta($pid, self::META_ALLOWED_COLORS, true));
        $effects = self::explode_csv((string) get_post_meta($pid, self::META_ALLOWED_EFFECTS, true));
        $finishes = self::explode_csv((string) get_post_meta($pid, self::META_ALLOWED_FINISHES, true));
        $allow_custom_text = get_post_meta($pid, self::META_ALLOW_CUSTOM_TEXT, true) === 'yes';
        $allow_upload = get_post_meta($pid, self::META_ALLOW_UPLOAD, true) === 'yes';
        $default_note = (string) get_post_meta($pid, self::META_DEFAULT_WORKSHOP_NOTE, true);

        echo '<div class="osds3d-epoxy-form" style="margin:16px 0;padding:14px;border:1px solid #ddd;border-radius:8px">';
        echo '<h4 style="margin-top:0">Personnalisation Epoxy</h4>';

        self::render_select('osds3d_epoxy_material', 'Matériau / style', $materials, true);
        self::render_select('osds3d_epoxy_color', 'Couleur', $colors, true);
        self::render_select('osds3d_epoxy_effect', 'Effet', $effects, false);
        self::render_select('osds3d_epoxy_finish', 'Finition', $finishes, false);

        if ($allow_custom_text) {
            echo '<p><label for="osds3d_epoxy_custom_text">Texte personnalisé</label>';
            echo '<input id="osds3d_epoxy_custom_text" name="osds3d_epoxy_custom_text" type="text" maxlength="120" style="width:100%" /></p>';
        }

        echo '<p><label for="osds3d_epoxy_note">Note</label>';
        echo '<textarea id="osds3d_epoxy_note" name="osds3d_epoxy_note" rows="3" style="width:100%">' . esc_textarea($default_note) . '</textarea></p>';

        if ($allow_upload) {
            echo '<p><label for="osds3d_epoxy_upload">Upload</label>';
            echo '<input id="osds3d_epoxy_upload" name="osds3d_epoxy_upload" type="file" accept="image/*,.pdf" /></p>';
        }

        echo '</div>';
    }

    private static function render_select(string $name, string $label, array $options, bool $required): void {
        if (empty($options)) {
            return;
        }

        echo '<p><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" style="width:100%" ' . ($required ? 'required' : '') . '>';
        echo '<option value="">' . esc_html__('Choisir…', 'osds3d') . '</option>';
        foreach ($options as $option) {
            echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
        }
        echo '</select></p>';
    }

    public static function validate_front_form(bool $passed, int $product_id, int $quantity): bool {
        unset($quantity);

        if (!self::is_epoxy_workflow($product_id)) {
            return $passed;
        }

        $required = [
            'osds3d_epoxy_material' => 'matériau/style',
            'osds3d_epoxy_color' => 'couleur',
        ];

        foreach ($required as $field => $label) {
            $value = isset($_POST[$field]) ? trim((string) wp_unslash($_POST[$field])) : '';
            if ($value === '') {
                wc_add_notice(sprintf(__('Veuillez choisir un %s.', 'osds3d'), $label), 'error');
                return false;
            }
        }

        return $passed;
    }

    private static function build_epoxy_payload(): array {
        $upload_name = '';
        if (isset($_FILES['osds3d_epoxy_upload']['name']) && is_string($_FILES['osds3d_epoxy_upload']['name'])) {
            $upload_name = sanitize_file_name($_FILES['osds3d_epoxy_upload']['name']);
        }

        return [
            'workflow' => 'epoxy',
            'material' => sanitize_text_field((string) ($_POST['osds3d_epoxy_material'] ?? '')),
            'color' => sanitize_text_field((string) ($_POST['osds3d_epoxy_color'] ?? '')),
            'effect' => sanitize_text_field((string) ($_POST['osds3d_epoxy_effect'] ?? '')),
            'finish' => sanitize_text_field((string) ($_POST['osds3d_epoxy_finish'] ?? '')),
            'custom_text' => sanitize_text_field((string) ($_POST['osds3d_epoxy_custom_text'] ?? '')),
            'note' => sanitize_textarea_field((string) ($_POST['osds3d_epoxy_note'] ?? '')),
            'upload_name' => $upload_name,
        ];
    }

    public static function attach_cart_item_data(array $cart_item_data, int $product_id): array {
        if (!self::is_epoxy_workflow($product_id)) {
            return $cart_item_data;
        }

        $existing = $cart_item_data[self::PERSONALIZATION_KEY] ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }

        $existing['business_family'] = 'epoxy';
        $existing['personalization_mode'] = 'form';
        $existing['product_type'] = 'simple';
        $existing['epoxy'] = self::build_epoxy_payload();

        $cart_item_data[self::PERSONALIZATION_KEY] = $existing;
        $cart_item_data['unique_key'] = md5(wp_json_encode($existing) . microtime());

        return $cart_item_data;
    }

    public static function render_cart_item_data(array $item_data, array $cart_item): array {
        $personalization = $cart_item[self::PERSONALIZATION_KEY] ?? null;
        $epoxy = is_array($personalization) ? ($personalization['epoxy'] ?? null) : null;
        if (!is_array($epoxy)) {
            return $item_data;
        }

        $labels = [
            'material' => 'Matériau/style',
            'color' => 'Couleur',
            'effect' => 'Effet',
            'finish' => 'Finition',
            'custom_text' => 'Texte personnalisé',
            'note' => 'Note',
            'upload_name' => 'Upload',
        ];

        foreach ($labels as $key => $label) {
            $value = trim((string) ($epoxy[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $item_data[] = ['key' => $label, 'value' => $value];
        }

        return $item_data;
    }

    public static function persist_order_item_meta($item, $cart_item_key, $values, $order): void {
        unset($cart_item_key, $order);

        $personalization = $values[self::PERSONALIZATION_KEY] ?? null;
        if (!is_array($personalization)) {
            return;
        }

        $item->add_meta_data(self::PERSONALIZATION_KEY, wp_json_encode($personalization), true);
    }

    public static function render_admin_order_item_meta(int $item_id, $item, $product): void {
        unset($item_id, $product);
        $raw = $item->get_meta(self::PERSONALIZATION_KEY, true);
        if (!is_string($raw) || $raw === '') {
            return;
        }

        $personalization = json_decode($raw, true);
        $epoxy = is_array($personalization) ? ($personalization['epoxy'] ?? null) : null;
        if (!is_array($epoxy)) {
            return;
        }

        echo '<div class="osds3d-epoxy-admin" style="margin:6px 0;padding:6px 8px;background:#f8f8f8">';
        echo '<strong>OSDS3D Epoxy</strong>';
        echo '<ul style="margin:4px 0 0 16px">';
        foreach ($epoxy as $k => $v) {
            if ((string) $v === '') {
                continue;
            }
            echo '<li><code>' . esc_html((string) $k) . '</code>: ' . esc_html((string) $v) . '</li>';
        }
        echo '</ul></div>';
    }
}

OSDS3D_Epoxy_Workflow_V1::bootstrap();
