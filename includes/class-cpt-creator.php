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
     */
    private function __construct() {
        // Регистрируем типы записей СРАЗУ в конструкторе
        // так как конструктор уже вызывается в хуке init
        $this->register_stored_post_types();
    }
    
    /**
     * Регистрация сохраненных типов записей
     * Читает напрямую из ACF записей (acf-post-type posts)
     */
    public function register_stored_post_types() {
        error_log('ACF MCP Manager: register_stored_post_types called');
        
        // Читаем напрямую из ACF записей
        $acf_post_types = get_posts(array(
            'post_type'      => 'acf-post-type',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));
        
        error_log('ACF MCP Manager: Found ' . count($acf_post_types) . ' ACF post types');
        
        foreach ($acf_post_types as $acf_cpt) {
            $data = maybe_unserialize($acf_cpt->post_content);
            if (!is_array($data)) {
                continue;
            }
            
            // post_type slug хранится в post_excerpt
            $post_type_key = $acf_cpt->post_excerpt;
            if (empty($post_type_key)) {
                $post_type_key = $data['post_type'] ?? '';
            }
            
            if (empty($post_type_key)) {
                continue;
            }
            
            // Пропускаем если уже зарегистрирован ACF Pro
            if (post_type_exists($post_type_key)) {
                error_log("ACF CPT Manager: {$post_type_key} already registered by ACF Pro");
                continue;
            }
            
            error_log("ACF CPT Manager: Registering {$post_type_key}");
            
            // Формируем аргументы для register_post_type
            $labels = $data['labels'] ?? array();
            $register_args = array(
                'labels'              => $labels,
                'description'         => $data['description'] ?? '',
                'public'              => !empty($data['public']),
                'hierarchical'        => !empty($data['hierarchical']),
                'exclude_from_search' => !empty($data['exclude_from_search']),
                'publicly_queryable'  => !empty($data['publicly_queryable']),
                'show_ui'             => !empty($data['show_ui']),
                'show_in_menu'        => !empty($data['show_in_menu']),
                'show_in_admin_bar'   => !empty($data['show_in_admin_bar']),
                'show_in_nav_menus'   => !empty($data['show_in_nav_menus']),
                'show_in_rest'        => !empty($data['show_in_rest']),
                'menu_icon'           => $data['menu_icon'] ?? 'dashicons-admin-post',
                'supports'            => $data['supports'] ?? array('title', 'editor', 'thumbnail'),
                'has_archive'         => !empty($data['has_archive']),
                'rewrite'             => array('slug' => $post_type_key),
            );
            
            $result = register_post_type($post_type_key, $register_args);
            
            if (is_wp_error($result)) {
                error_log("ACF CPT Manager: Ошибка регистрации типа {$post_type_key}: " . $result->get_error_message());
            } else {
                error_log("ACF CPT Manager: Тип записи {$post_type_key} зарегистрирован успешно");
            }
        }
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
            'args' => $acf_post_type,
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
    public function delete_post_type($post_type_key) {
        $stored_post_types = get_option('acf_mcp_manager_post_types', array());
        
        if (!isset($stored_post_types[$post_type_key])) {
            return new WP_Error('post_type_not_found', 'Тип записи не найден');
        }
        
        // Деактивируем тип записи
        $stored_post_types[$post_type_key]['active'] = false;
        update_option('acf_mcp_manager_post_types', $stored_post_types);
        
        // Сбрасываем rewrite rules
        flush_rewrite_rules();
        
        return array(
            'success' => true,
            'message' => sprintf('Тип записи "%s" деактивирован', $post_type_key)
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
        
        $stored_post_types[$post_type_key]['active'] = $active;
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
