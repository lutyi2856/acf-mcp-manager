<?php
/**
 * REST API для управления типами записей
 *
 * @package ACF_MCP_Manager
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс ACF_MCP_REST_API
 */
class ACF_MCP_REST_API {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Namespace для REST API
     */
    private $namespace = 'acf-mcp-manager/v1';

    private function normalize_request_params(WP_REST_Request $request): array {
        $params = array();

        foreach ($request->get_params() as $key => $value) {
            $params[$key] = $value;
        }

        foreach ((array) $request->get_body_params() as $key => $value) {
            $params[$key] = $value;
        }

        $json_params = $request->get_json_params();
        if (is_array($json_params)) {
            foreach ($json_params as $key => $value) {
                $params[$key] = $value;
            }
        }

        return $params;
    }
    
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
        // Конструктор пустой, регистрация происходит через register_routes()
    }
    
    /**
     * Регистрация REST API маршрутов
     */
    public function register_routes() {
        // Получить список типов записей
        register_rest_route($this->namespace, '/post-types', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_types'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Создать новый тип записи
        register_rest_route($this->namespace, '/post-types', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_post_type'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'key' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Уникальный ключ типа записи (например: products)'
                ),
                'label' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Название типа записи (например: Продукты)'
                ),
                'singular_label' => array(
                    'type' => 'string',
                    'description' => 'Единственное число (например: Продукт)'
                ),
                'description' => array(
                    'type' => 'string',
                    'description' => 'Описание типа записи'
                ),
                'menu_icon' => array(
                    'type' => 'string',
                    'description' => 'Иконка меню (dashicon или URL)'
                ),
                'menu_position' => array(
                    'type' => 'integer',
                    'description' => 'Позиция в меню админки'
                ),
                'post_type_args' => array(
                    'type' => 'object',
                    'description' => 'Дополнительные аргументы для register_post_type'
                )
            )
        ));
        
        // Обновить тип записи
        register_rest_route($this->namespace, '/post-types/(?P<post_type>[a-zA-Z0-9_-]+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_post_type'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Активировать/деактивировать тип записи
        register_rest_route($this->namespace, '/post-types/(?P<post_type>[a-zA-Z0-9_-]+)/toggle', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_post_type'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'active' => array(
                    'type' => 'boolean',
                    'description' => 'Активировать (true) или деактивировать (false)'
                )
            )
        ));
        
        // Удалить тип записи
        register_rest_route($this->namespace, '/post-types/(?P<post_type>[a-zA-Z0-9_-]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_post_type'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'permanent' => array(
                    'type' => 'boolean',
                    'description' => 'Полное удаление в ACF (true) или мягкая деактивация (false). По умолчанию: false'
                )
            )
        ));
        
        // Получить шаблоны типов записей
        register_rest_route($this->namespace, '/post-types/templates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === OPTIONS PAGES ROUTES ===
        
        // Получить список страниц опций
        register_rest_route($this->namespace, '/options-pages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_options_pages'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Создать новую страницу опций
        register_rest_route($this->namespace, '/options-pages', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_options_page'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'page_title' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Заголовок страницы'
                ),
                'menu_slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Уникальный slug'
                ),
                'menu_title' => array(
                    'type' => 'string',
                    'description' => 'Название в меню'
                ),
                'icon_url' => array(
                    'type' => 'string',
                    'description' => 'Иконка меню'
                ),
                'capability' => array(
                    'type' => 'string',
                    'description' => 'Права доступа'
                ),
                'parent_slug' => array(
                    'type' => 'string',
                    'description' => 'Slug родительской страницы'
                ),
                'sub_pages' => array(
                    'type' => 'array',
                    'description' => 'Массив подстраниц'
                ),
                'description' => array(
                    'type' => 'string',
                    'description' => 'Описание страницы опций'
                ),
                'show_in_graphql' => array(
                    'type' => 'boolean',
                    'description' => 'Показывать в GraphQL'
                ),
                'graphql_single_name' => array(
                    'type' => 'string',
                    'description' => 'Название типа в GraphQL (PascalCase)'
                )
            )
        ));
        
        // Обновить страницу опций
        register_rest_route($this->namespace, '/options-pages/(?P<menu_slug>[a-zA-Z0-9_-]+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_options_page'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Активировать/деактивировать страницу опций
        register_rest_route($this->namespace, '/options-pages/(?P<menu_slug>[a-zA-Z0-9_-]+)/toggle', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_options_page'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'active' => array(
                    'type' => 'boolean',
                    'description' => 'Активировать (true) или деактивировать (false)'
                )
            )
        ));
        
        // Удалить страницу опций
        register_rest_route($this->namespace, '/options-pages/(?P<menu_slug>[a-zA-Z0-9_-]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_options_page'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Получить шаблоны страниц опций
        register_rest_route($this->namespace, '/options-pages/templates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_options_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === TAXONOMIES ROUTES ===
        
        // Получить список таксономий
        register_rest_route($this->namespace, '/taxonomies', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_taxonomies'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Создать новую таксономию
        register_rest_route($this->namespace, '/taxonomies', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_taxonomy'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Обновить таксономию
        register_rest_route($this->namespace, '/taxonomies/(?P<taxonomy_key>[a-zA-Z0-9_-]+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_taxonomy'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Переключить состояние таксономии
        register_rest_route($this->namespace, '/taxonomies/(?P<taxonomy_key>[a-zA-Z0-9_-]+)/toggle', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_taxonomy'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'active' => array(
                    'type' => 'boolean',
                    'description' => 'Активировать (true) или деактивировать (false)'
                )
            )
        ));
        
        // Удалить таксономию
        register_rest_route($this->namespace, '/taxonomies/(?P<taxonomy_key>[a-zA-Z0-9_-]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_taxonomy'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Получить шаблоны таксономий
        register_rest_route($this->namespace, '/taxonomies/templates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_taxonomy_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === FIELD GROUPS ROUTES ===
        
        // Получить список групп полей
        register_rest_route($this->namespace, '/field-groups', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_field_groups'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Получить одну группу полей
        register_rest_route($this->namespace, '/field-groups/(?P<group_key>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Создать новую группу полей
        register_rest_route($this->namespace, '/field-groups', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Обновить группу полей
        register_rest_route($this->namespace, '/field-groups/(?P<group_key>[a-zA-Z0-9_-]+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Переключить состояние группы полей
        register_rest_route($this->namespace, '/field-groups/(?P<group_key>[a-zA-Z0-9_-]+)/toggle', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_field_group'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'active' => array(
                    'type' => 'boolean',
                    'description' => 'Активировать (true) или деактивировать (false)'
                )
            )
        ));
        
        // Удалить группу полей
        register_rest_route($this->namespace, '/field-groups/(?P<group_key>[a-zA-Z0-9_-]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Добавить поле в группу
        register_rest_route($this->namespace, '/field-groups/(?P<group_key>[a-zA-Z0-9_-]+)/fields', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_field_to_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Удалить поле из группы
        register_rest_route($this->namespace, '/field-groups/(?P<group_key>[a-zA-Z0-9_-]+)/fields/(?P<field_key>[a-zA-Z0-9_-]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'remove_field_from_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Получить типы полей
        register_rest_route($this->namespace, '/field-types', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_field_types'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Получить шаблоны групп полей
        register_rest_route($this->namespace, '/field-groups/templates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_field_group_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Экспорт группы полей в JSON
        register_rest_route($this->namespace, '/field-groups/(?P<group_key>[a-zA-Z0-9_-]+)/export-json', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_field_group_to_json'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Экспорт всех групп полей в JSON
        register_rest_route($this->namespace, '/field-groups/export-all-json', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_all_field_groups_to_json'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === ACF VALUES ROUTES ===
        
        // Обновить ACF поля объекта
        register_rest_route($this->namespace, '/acf-values', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_acf_values'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Получить ACF поля объекта
        register_rest_route($this->namespace, '/acf-values/(?P<post_id>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_acf_values'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Удалить значение ACF поля
        register_rest_route($this->namespace, '/acf-values/(?P<post_id>[a-zA-Z0-9_-]+)/(?P<field_name>[a-zA-Z0-9_-]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_acf_value'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === TERMS MANAGEMENT ROUTES ===
        
        // Получить список термов таксономии
        register_rest_route($this->namespace, '/terms/(?P<taxonomy>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_terms'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Создать терм
        register_rest_route($this->namespace, '/terms/(?P<taxonomy>[a-zA-Z0-9_-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_term'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Обновить терм
        register_rest_route($this->namespace, '/terms/(?P<taxonomy>[a-zA-Z0-9_-]+)/(?P<term_id>[\d]+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_term'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Удалить терм
        register_rest_route($this->namespace, '/terms/(?P<taxonomy>[a-zA-Z0-9_-]+)/(?P<term_id>[\d]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_term'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Привязать термы к записи
        register_rest_route($this->namespace, '/terms/assign', array(
            'methods' => 'POST',
            'callback' => array($this, 'assign_terms'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Получить термы записи
        register_rest_route($this->namespace, '/posts/(?P<post_id>[\d]+)/terms', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_terms'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === OPTIONS PAGE VALUES ROUTES ===
        
        // Получить значения полей страницы опций
        register_rest_route($this->namespace, '/options-values/(?P<page_slug>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_options_values'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Обновить значения полей страницы опций
        register_rest_route($this->namespace, '/options-values/(?P<page_slug>[a-zA-Z0-9_-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_options_values'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === MEDIA ROUTES ===
        
        // Загрузить изображение по URL
        register_rest_route($this->namespace, '/media/upload-from-url', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_media_from_url'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Загрузить несколько изображений по URL
        register_rest_route($this->namespace, '/media/upload-multiple', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_multiple_media'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Установить featured image
        register_rest_route($this->namespace, '/media/set-featured', array(
            'methods' => 'POST',
            'callback' => array($this, 'set_featured_image'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Получить список медиа
        register_rest_route($this->namespace, '/media', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_media'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * Проверка разрешений
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * Получить список типов записей
     */
    public function get_post_types($request) {
        $stored_post_types = ACF_MCP_CPT_Creator::get_instance()->get_post_types();
        
        // Добавляем информацию о состоянии
        foreach ($stored_post_types as $key => &$post_type) {
            $post_type['is_registered'] = post_type_exists($key);
            $post_type['posts_count'] = wp_count_posts($key)->publish ?? 0;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $stored_post_types
        ));
    }
    
    /**
     * Создать новый тип записи
     */
    public function create_post_type($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_CPT_Creator::get_instance()->create_post_type($params);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }

    /**
     * Обновить тип записи
     */
    public function update_post_type($request) {
        $post_type = $request->get_param('post_type');
        $params = $this->normalize_request_params($request);

        $result = ACF_MCP_CPT_Creator::get_instance()->update_post_type($post_type, $params);

        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }

        return rest_ensure_response($result);
    }
    
    /**
     * Активировать/деактивировать тип записи
     */
    public function toggle_post_type($request) {
        $post_type = $request->get_param('post_type');
        $active = $request->get_param('active') ?? true;
        
        $result = ACF_MCP_CPT_Creator::get_instance()->toggle_post_type($post_type, $active);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Удалить тип записи
     */
    public function delete_post_type($request) {
        $post_type = $request->get_param('post_type');
        $permanent = $request->get_param('permanent') === 'true' || $request->get_param('permanent') === true;
        
        $result = ACF_MCP_CPT_Creator::get_instance()->delete_post_type($post_type, $permanent);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Получить шаблоны типов записей
     */
    public function get_templates($request) {
        $templates = array(
            'blog_post' => array(
                'name' => 'Блог',
                'description' => 'Стандартный тип записи для блога',
                'args' => array(
                    'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'comments'),
                    'has_archive' => true,
                    'menu_icon' => 'dashicons-admin-post'
                )
            ),
            'portfolio' => array(
                'name' => 'Портфолио',
                'description' => 'Тип записи для проектов портфолио',
                'args' => array(
                    'supports' => array('title', 'editor', 'thumbnail'),
                    'has_archive' => true,
                    'menu_icon' => 'dashicons-portfolio'
                )
            ),
            'testimonials' => array(
                'name' => 'Отзывы',
                'description' => 'Тип записи для отзывов клиентов',
                'args' => array(
                    'supports' => array('title', 'editor'),
                    'has_archive' => true,
                    'menu_icon' => 'dashicons-testimonial'
                )
            ),
            'team' => array(
                'name' => 'Команда',
                'description' => 'Тип записи для сотрудников',
                'args' => array(
                    'supports' => array('title', 'editor', 'thumbnail'),
                    'has_archive' => true,
                    'menu_icon' => 'dashicons-groups'
                )
            ),
            'events' => array(
                'name' => 'События',
                'description' => 'Тип записи для мероприятий',
                'args' => array(
                    'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
                    'has_archive' => true,
                    'menu_icon' => 'dashicons-calendar-alt'
                )
            )
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'templates' => $templates
        ));
    }
    
    /**
     * Получить список страниц опций
     */
    public function get_options_pages($request) {
        $pages = ACF_MCP_Options_Page_Creator::get_instance()->get_options_pages();
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $pages
        ));
    }
    
    /**
     * Создать новую страницу опций
     */
    public function create_options_page($request) {
        $params = $request->get_params();
        
        $result = ACF_MCP_Options_Page_Creator::get_instance()->create_options_page($params);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Обновить страницу опций
     */
    public function update_options_page($request) {
        $menu_slug = $request->get_param('menu_slug');
        $params = $request->get_params();
        
        $result = ACF_MCP_Options_Page_Creator::get_instance()->update_options_page($menu_slug, $params);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Активировать/деактивировать страницу опций
     */
    public function toggle_options_page($request) {
        $menu_slug = $request->get_param('menu_slug');
        $active = $request->get_param('active') ?? true;
        
        $result = ACF_MCP_Options_Page_Creator::get_instance()->toggle_options_page($menu_slug, $active);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Удалить страницу опций
     */
    public function delete_options_page($request) {
        $menu_slug = $request->get_param('menu_slug');
        $permanent = $request->get_param('permanent') === 'true' || $request->get_param('permanent') === true;
        
        $result = ACF_MCP_Options_Page_Creator::get_instance()->delete_options_page($menu_slug, $permanent);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Получить шаблоны страниц опций
     */
    public function get_options_templates($request) {
        $templates = ACF_MCP_Options_Page_Creator::get_instance()->get_templates();
        
        return rest_ensure_response(array(
            'success' => true,
            'templates' => $templates
        ));
    }
    
    /**
     * Получить список таксономий
     */
    public function get_taxonomies($request) {
        $taxonomies = ACF_MCP_Taxonomy_Creator::get_instance()->get_taxonomies();
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $taxonomies
        ));
    }
    
    /**
     * Создать новую таксономию
     */
    public function create_taxonomy($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Taxonomy_Creator::get_instance()->create_taxonomy($params);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Обновить таксономию
     */
    public function update_taxonomy($request) {
        $taxonomy_key = $request->get_param('taxonomy_key');
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Taxonomy_Creator::get_instance()->update_taxonomy($taxonomy_key, $params);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Переключить состояние таксономии
     */
    public function toggle_taxonomy($request) {
        $taxonomy_key = $request->get_param('taxonomy_key');
        $active = $request->get_param('active') === 'true' || $request->get_param('active') === true;
        
        $result = ACF_MCP_Taxonomy_Creator::get_instance()->toggle_taxonomy($taxonomy_key, $active);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Удалить таксономию
     */
    public function delete_taxonomy($request) {
        $taxonomy_key = $request->get_param('taxonomy_key');
        $permanent = $request->get_param('permanent') === 'true' || $request->get_param('permanent') === true;
        
        $result = ACF_MCP_Taxonomy_Creator::get_instance()->delete_taxonomy($taxonomy_key, $permanent);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Получить шаблоны таксономий
     */
    public function get_taxonomy_templates($request) {
        $templates = ACF_MCP_Taxonomy_Creator::get_instance()->get_templates();
        
        return rest_ensure_response(array(
            'success' => true,
            'templates' => $templates
        ));
    }
    
    // === FIELD GROUPS METHODS ===
    
    /**
     * Получить список групп полей
     */
    public function get_field_groups($request) {
        $field_groups = ACF_MCP_Field_Group_Creator::get_instance()->get_field_groups();
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $field_groups
        ));
    }
    
    /**
     * Получить одну группу полей
     */
    public function get_field_group($request) {
        $group_key = $request->get_param('group_key');
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->get_field_group($group_key);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 404)
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $result
        ));
    }
    
    /**
     * Создать новую группу полей
     */
    public function create_field_group($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->create_field_group($params);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Обновить группу полей
     */
    public function update_field_group($request) {
        $group_key = $request->get_param('group_key');
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->update_field_group($group_key, $params);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Переключить состояние группы полей
     */
    public function toggle_field_group($request) {
        $group_key = $request->get_param('group_key');
        $active = $request->get_param('active') === 'true' || $request->get_param('active') === true;
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->toggle_field_group($group_key, $active);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Удалить группу полей
     */
    public function delete_field_group($request) {
        $group_key = $request->get_param('group_key');
        $permanent = $request->get_param('permanent') === 'true' || $request->get_param('permanent') === true;
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->delete_field_group($group_key, $permanent);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Добавить поле в группу
     */
    public function add_field_to_group($request) {
        $group_key = $request->get_param('group_key');
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->add_field_to_group($group_key, $params);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Удалить поле из группы
     */
    public function remove_field_from_group($request) {
        $group_key = $request->get_param('group_key');
        $field_key = $request->get_param('field_key');
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->remove_field_from_group($group_key, $field_key);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Получить типы полей
     */
    public function get_field_types($request) {
        $field_types = ACF_MCP_Field_Group_Creator::get_instance()->get_field_types();
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $field_types
        ));
    }
    
    /**
     * Получить шаблоны групп полей
     */
    public function get_field_group_templates($request) {
        $templates = ACF_MCP_Field_Group_Creator::get_instance()->get_templates();
        
        return rest_ensure_response(array(
            'success' => true,
            'templates' => $templates
        ));
    }
    
    /**
     * Экспорт группы полей в JSON
     */
    public function export_field_group_to_json($request) {
        $group_key = $request->get_param('group_key');
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->export_to_json($group_key);
        
        return rest_ensure_response(array(
            'success' => !empty($result['saved']),
            'group_key' => $group_key,
            'result' => $result
        ));
    }
    
    /**
     * Экспорт всех групп полей в JSON
     */
    public function export_all_field_groups_to_json($request) {
        $result = ACF_MCP_Field_Group_Creator::get_instance()->export_all_to_json();
        
        return rest_ensure_response($result);
    }
    
    // === ACF VALUES METHODS ===
    
    /**
     * Обновить ACF поля объекта
     */
    public function update_acf_values($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Values_Manager::get_instance()->update_fields($params);
        
        if (!$result['success']) {
            return new WP_Error(
                'acf_update_failed',
                $result['error'] ?? 'Ошибка обновления полей',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Получить ACF поля объекта
     */
    public function get_acf_values($request) {
        $post_id = $request->get_param('post_id');
        $fields = $request->get_param('fields');
        
        $result = ACF_MCP_Values_Manager::get_instance()->get_fields(array(
            'post_id' => $post_id,
            'fields' => $fields
        ));
        
        if (!$result['success']) {
            return new WP_Error(
                'acf_get_failed',
                $result['error'] ?? 'Ошибка получения полей',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Удалить значение ACF поля
     */
    public function delete_acf_value($request) {
        $post_id = $request->get_param('post_id');
        $field_name = $request->get_param('field_name');
        
        $result = ACF_MCP_Values_Manager::get_instance()->delete_field(array(
            'post_id' => $post_id,
            'field_name' => $field_name
        ));
        
        if (!$result['success']) {
            return new WP_Error(
                'acf_delete_failed',
                $result['error'] ?? 'Ошибка удаления поля',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    // === TERMS MANAGEMENT METHODS ===
    
    /**
     * Получить список термов таксономии
     */
    public function get_terms($request) {
        $taxonomy = $request->get_param('taxonomy');
        $params = $this->normalize_request_params($request);
        $params['taxonomy'] = $taxonomy;
        
        $result = ACF_MCP_Term_Manager::get_instance()->list_terms($params);
        
        if (!$result['success']) {
            return new WP_Error(
                'terms_get_failed',
                $result['error'] ?? 'Ошибка получения термов',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Создать терм
     */
    public function create_term($request) {
        $taxonomy = $request->get_param('taxonomy');
        $params = $this->normalize_request_params($request);
        $params['taxonomy'] = $taxonomy;
        
        $result = ACF_MCP_Term_Manager::get_instance()->create_term($params);
        
        if (!$result['success']) {
            return new WP_Error(
                'term_create_failed',
                $result['error'] ?? 'Ошибка создания терма',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Обновить терм
     */
    public function update_term($request) {
        $taxonomy = $request->get_param('taxonomy');
        $term_id = $request->get_param('term_id');
        $params = $this->normalize_request_params($request);
        $params['taxonomy'] = $taxonomy;
        $params['term_id'] = $term_id;
        
        $result = ACF_MCP_Term_Manager::get_instance()->update_term($params);
        
        if (!$result['success']) {
            return new WP_Error(
                'term_update_failed',
                $result['error'] ?? 'Ошибка обновления терма',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Удалить терм
     */
    public function delete_term($request) {
        $taxonomy = $request->get_param('taxonomy');
        $term_id = $request->get_param('term_id');
        
        $result = ACF_MCP_Term_Manager::get_instance()->delete_term(array(
            'taxonomy' => $taxonomy,
            'term_id' => $term_id
        ));
        
        if (!$result['success']) {
            return new WP_Error(
                'term_delete_failed',
                $result['error'] ?? 'Ошибка удаления терма',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Привязать термы к записи
     */
    public function assign_terms($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Term_Manager::get_instance()->assign_terms($params);
        
        if (!$result['success']) {
            return new WP_Error(
                'terms_assign_failed',
                $result['error'] ?? 'Ошибка привязки термов',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Получить термы записи
     */
    public function get_post_terms($request) {
        $post_id = $request->get_param('post_id');
        $taxonomy = $request->get_param('taxonomy');
        $include_acf = $request->get_param('include_acf') === 'true' || $request->get_param('include_acf') === true;
        
        $result = ACF_MCP_Term_Manager::get_instance()->get_post_terms(array(
            'post_id' => $post_id,
            'taxonomy' => $taxonomy,
            'include_acf' => $include_acf
        ));
        
        if (!$result['success']) {
            return new WP_Error(
                'post_terms_get_failed',
                $result['error'] ?? 'Ошибка получения термов записи',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    // === OPTIONS PAGE VALUES METHODS ===
    
    /**
     * Получить значения полей страницы опций
     */
    public function get_options_values($request) {
        $page_slug = $request->get_param('page_slug');
        
        // Используем ACF Values Manager с post_id = 'option'
        $result = ACF_MCP_Values_Manager::get_instance()->get_fields(array(
            'post_id' => 'option'
        ));
        
        if (!$result['success']) {
            return new WP_Error(
                'options_get_failed',
                $result['error'] ?? 'Ошибка получения значений опций',
                array('status' => 400)
            );
        }
        
        $result['page_slug'] = $page_slug;
        return rest_ensure_response($result);
    }
    
    /**
     * Обновить значения полей страницы опций
     */
    public function update_options_values($request) {
        $page_slug = $request->get_param('page_slug');
        $params = $this->normalize_request_params($request);
        
        // Устанавливаем post_id как 'option' для ACF
        $params['post_id'] = 'option';
        
        $result = ACF_MCP_Values_Manager::get_instance()->update_fields($params);
        
        if (!$result['success']) {
            return new WP_Error(
                'options_update_failed',
                $result['error'] ?? 'Ошибка обновления значений опций',
                array('status' => 400)
            );
        }
        
        $result['page_slug'] = $page_slug;
        return rest_ensure_response($result);
    }
    
    // === MEDIA METHODS ===
    
    /**
     * Загрузить изображение по URL
     */
    public function upload_media_from_url($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Media_Manager::get_instance()->upload_from_url($params);
        
        if (!$result['success']) {
            return new WP_Error(
                'upload_failed',
                $result['error'] ?? 'Ошибка загрузки файла',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Загрузить несколько изображений по URL
     */
    public function upload_multiple_media($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Media_Manager::get_instance()->upload_multiple_from_urls($params);
        
        if (!$result['success'] && $result['uploaded_count'] === 0) {
            return new WP_Error(
                'upload_failed',
                'Не удалось загрузить ни одного файла',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Установить featured image
     */
    public function set_featured_image($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Media_Manager::get_instance()->set_featured_image($params);
        
        if (!$result['success']) {
            return new WP_Error(
                'featured_image_failed',
                $result['error'] ?? 'Ошибка установки изображения',
                array('status' => 400)
            );
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Получить список медиа
     */
    public function list_media($request) {
        $params = $this->normalize_request_params($request);
        
        $result = ACF_MCP_Media_Manager::get_instance()->list_media($params);
        
        return rest_ensure_response($result);
    }
}
