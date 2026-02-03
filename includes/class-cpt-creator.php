<?php
/**
 * Класс для создания пользовательских типов записей
 *
 * @package ACF_MCP_Manager
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс ACF_MCP_CPT_Creator
 */
class ACF_MCP_CPT_Creator {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     * ACF Pro сам регистрирует post types, плагин только создаёт их через ACF API
     */
    private function __construct() {
        // Ничего не делаем - ACF Pro сам регистрирует типы записей
    }
    
    /**
     * Создать новый тип записи через ACF Pro систему
     */
    public function create_post_type($args) {
        // Валидация обязательных параметров
        if (empty($args['key']) || empty($args['label'])) {
            return new WP_Error('missing_params', 'Требуются параметры key и label');
        }
        
        // Проверяем что тип записи не существует
        if (post_type_exists($args['key'])) {
            return new WP_Error('post_type_exists', 'Тип записи уже существует');
        }
        
        // Создаем ACF Post Type в стиле ACF Pro
        $acf_post_type = $this->prepare_acf_post_type($args);
        
        // Сохраняем через ACF систему
        if (function_exists('acf_update_post_type')) {
            $result = acf_update_post_type($acf_post_type);
            
            if (is_wp_error($result)) {
                return $result;
            }
            
            error_log("ACF CPT Manager: Создан ACF Post Type ID: " . $result['ID']);
        } else {
            return new WP_Error('acf_not_available', 'ACF Pro функции недоступны');
        }
        
        // Дополнительно сохраняем в нашей системе для совместимости
        $stored_post_types = get_option('acf_mcp_manager_post_types', array());
        $stored_post_types[$args['key']] = array(
            'acf_id' => $result['ID'],
            // Храним фактически сохранённый объект из ACF (с корректным ID и нормализованными настройками)
            'args' => $result,
            'active' => true,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'method' => 'acf_pro'
        );
        
        update_option('acf_mcp_manager_post_types', $stored_post_types);
        
        // Сбрасываем rewrite rules
        flush_rewrite_rules();
        
        return array(
            'success' => true,
            'post_type' => $args['key'],
            'acf_id' => $result['ID'],
            'message' => sprintf('Тип записи "%s" успешно создан через ACF Pro', $args['label'])
        );
    }

    /**
     * Обновить тип записи в ACF Pro
     */
    public function update_post_type($post_type_key, array $args) {
        $stored_post_types = get_option('acf_mcp_manager_post_types', array());

        if (!isset($stored_post_types[$post_type_key])) {
            return new WP_Error('post_type_not_found', 'Тип записи не найден');
        }

        if (!function_exists('acf_get_post_type') || !function_exists('acf_update_post_type')) {
            return new WP_Error('acf_not_available', 'ACF Pro функции недоступны');
        }

        $acf_id = (int) ($stored_post_types[$post_type_key]['acf_id'] ?? 0);
        if (!$acf_id) {
            return new WP_Error('acf_id_missing', 'Не найден ACF ID типа записи');
        }

        $current = acf_get_post_type($acf_id);
        if (empty($current) || !is_array($current)) {
            return new WP_Error('acf_post_type_not_found', 'ACF тип записи не найден');
        }

        // Запрещаем менять ключ/имя post_type через update, чтобы не ломать существующие записи
        unset($args['key'], $args['post_type']);

        // Разрешаем обновлять заголовок, описание, иконку и любые поля ACF, если они переданы
        $merged = wp_parse_args($args, $current);

        $updated = acf_update_post_type($merged);
        if (is_wp_error($updated)) {
            return $updated;
        }

        $stored_post_types[$post_type_key]['args'] = $updated;
        $stored_post_types[$post_type_key]['active'] = (bool) ($updated['active'] ?? $stored_post_types[$post_type_key]['active']);
        $stored_post_types[$post_type_key]['updated_at'] = current_time('mysql');
        $stored_post_types[$post_type_key]['updated_by'] = get_current_user_id();
        update_option('acf_mcp_manager_post_types', $stored_post_types);

        flush_rewrite_rules();

        return array(
            'success' => true,
            'post_type' => $post_type_key,
            'acf_id' => $acf_id,
            'message' => sprintf('Тип записи "%s" успешно обновлён', $post_type_key)
        );
    }
    
    /**
     * Подготовка массива для ACF Pro Post Type
     */
    private function prepare_acf_post_type($args) {
        // Получаем дефолтные настройки ACF Post Type
        if (class_exists('ACF_Post_Type')) {
            $acf_instance = acf_get_internal_post_type_instance('acf-post-type');
            $defaults = $acf_instance ? $acf_instance->get_settings_array() : array();
        } else {
            $defaults = array();
        }
        
        // Базовые настройки ACF Post Type
        $acf_post_type = array(
            'key' => 'post_type_' . $args['key'], // ACF требует префикс
            'title' => $args['label'],
            'post_type' => $args['key'],
            'active' => true,
            'labels' => array(
                'name' => $args['label'],
                'singular_name' => $args['singular_label'] ?? $args['label'],
                'menu_name' => $args['menu_name'] ?? $args['label'],
                'add_new_item' => sprintf('Добавить %s', $args['singular_label'] ?? $args['label']),
                'edit_item' => sprintf('Редактировать %s', $args['singular_label'] ?? $args['label']),
                'new_item' => sprintf('Новый %s', $args['singular_label'] ?? $args['label']),
                'view_item' => sprintf('Просмотреть %s', $args['singular_label'] ?? $args['label']),
                'search_items' => sprintf('Найти %s', $args['label']),
                'not_found' => 'Не найдено',
                'not_found_in_trash' => 'Не найдено в корзине'
            ),
            'description' => $args['description'] ?? '',
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'rest_base' => $args['key'],
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail'),
            'menu_icon' => $args['menu_icon'] ?? 'dashicons-admin-post'
        );
        
        // Объединяем с дефолтами ACF если доступны
        if (!empty($defaults)) {
            $acf_post_type = wp_parse_args($acf_post_type, $defaults);
        }
        
        return $acf_post_type;
    }
    
    /**
     * Подготовка аргументов для register_post_type (legacy)
     */
    private function prepare_post_type_args($args) {
        // Значения по умолчанию
        $defaults = array(
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'rest_base' => $args['key'],
            'rest_namespace' => 'wp/v2',
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail'),
            'rewrite' => array('slug' => $args['key']),
            'can_export' => true,
            'delete_with_user' => false
        );
        
        // Подготавливаем labels
        $labels = array(
            'name' => $args['label'],
            'singular_name' => $args['singular_label'] ?? $args['label'],
            'menu_name' => $args['menu_name'] ?? $args['label'],
            'add_new_item' => sprintf('Добавить %s', $args['singular_label'] ?? $args['label']),
            'edit_item' => sprintf('Редактировать %s', $args['singular_label'] ?? $args['label']),
            'new_item' => sprintf('Новый %s', $args['singular_label'] ?? $args['label']),
            'view_item' => sprintf('Просмотреть %s', $args['singular_label'] ?? $args['label']),
            'search_items' => sprintf('Найти %s', $args['label']),
            'not_found' => 'Не найдено',
            'not_found_in_trash' => 'Не найдено в корзине'
        );
        
        // Объединяем с пользовательскими аргументами
        $post_type_args = wp_parse_args($args['post_type_args'] ?? array(), $defaults);
        $post_type_args['labels'] = $labels;
        
        // Дополнительные настройки
        if (isset($args['description'])) {
            $post_type_args['description'] = $args['description'];
        }
        
        if (isset($args['menu_icon'])) {
            $post_type_args['menu_icon'] = $args['menu_icon'];
        }
        
        if (isset($args['menu_position'])) {
            $post_type_args['menu_position'] = (int) $args['menu_position'];
        }
        
        return $post_type_args;
    }
    
    /**
     * Получить список созданных типов записей
     */
    public function get_post_types() {
        return get_option('acf_mcp_manager_post_types', array());
    }
    
    /**
     * Удалить тип записи
     */
    public function delete_post_type($post_type_key, $permanent = false) {
        $stored_post_types = get_option('acf_mcp_manager_post_types', array());
        
        if (!isset($stored_post_types[$post_type_key])) {
            return new WP_Error('post_type_not_found', 'Тип записи не найден');
        }

        $acf_id = (int) ($stored_post_types[$post_type_key]['acf_id'] ?? 0);
        if (!$acf_id) {
            return new WP_Error('acf_id_missing', 'Не найден ACF ID типа записи');
        }

        if (!function_exists('acf_update_post_type_active_status')) {
            return new WP_Error('acf_not_available', 'ACF Pro функции недоступны');
        }

        // По умолчанию — безопасная "мягкая" деактивация, permanent=true — удаление из ACF
        if ($permanent) {
            if (!function_exists('acf_delete_post_type')) {
                return new WP_Error('acf_not_available', 'ACF Pro функции удаления недоступны');
            }
            $deleted = acf_delete_post_type($acf_id);
            if (!$deleted) {
                return new WP_Error('acf_delete_failed', 'Не удалось удалить тип записи в ACF');
            }

            unset($stored_post_types[$post_type_key]);
        } else {
            $ok = acf_update_post_type_active_status($acf_id, false);
            if (!$ok) {
                return new WP_Error('acf_deactivate_failed', 'Не удалось деактивировать тип записи в ACF');
            }
            $stored_post_types[$post_type_key]['active'] = false;
        }

        update_option('acf_mcp_manager_post_types', $stored_post_types);
        
        // Сбрасываем rewrite rules
        flush_rewrite_rules();
        
        return array(
            'success' => true,
            'message' => $permanent
                ? sprintf('Тип записи "%s" удалён', $post_type_key)
                : sprintf('Тип записи "%s" деактивирован', $post_type_key)
        );
    }
    
    /**
     * Активировать/деактивировать тип записи
     */
    public function toggle_post_type($post_type_key, $active = true) {
        $stored_post_types = get_option('acf_mcp_manager_post_types', array());
        
        if (!isset($stored_post_types[$post_type_key])) {
            return new WP_Error('post_type_not_found', 'Тип записи не найден');
        }

        $acf_id = (int) ($stored_post_types[$post_type_key]['acf_id'] ?? 0);
        if (!$acf_id) {
            return new WP_Error('acf_id_missing', 'Не найден ACF ID типа записи');
        }

        if (!function_exists('acf_update_post_type_active_status')) {
            return new WP_Error('acf_not_available', 'ACF Pro функции недоступны');
        }

        $ok = acf_update_post_type_active_status($acf_id, (bool) $active);
        if (!$ok) {
            return new WP_Error('acf_toggle_failed', 'Не удалось изменить активность типа записи в ACF');
        }

        $stored_post_types[$post_type_key]['active'] = (bool) $active;
        $stored_post_types[$post_type_key]['updated_at'] = current_time('mysql');
        $stored_post_types[$post_type_key]['updated_by'] = get_current_user_id();
        update_option('acf_mcp_manager_post_types', $stored_post_types);
        
        // Сбрасываем rewrite rules
        flush_rewrite_rules();
        
        $status = $active ? 'активирован' : 'деактивирован';
        return array(
            'success' => true,
            'message' => sprintf('Тип записи "%s" %s', $post_type_key, $status)
        );
    }
}
