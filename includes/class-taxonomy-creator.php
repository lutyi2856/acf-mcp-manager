<?php
/**
 * Класс для создания и управления таксономиями через ACF UI
 * 
 * @package ACF_MCP_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACF_MCP_Taxonomy_Creator {
    
    private static $instance = null;
    
    /**
     * Singleton pattern
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
        // Регистрируем сохраненные таксономии сразу в конструкторе
        // так как конструктор уже вызывается в хуке init
        $this->register_stored_taxonomies();
    }
    
    /**
     * Регистрация сохраненных таксономий
     * Читает напрямую из ACF записей (acf-taxonomy posts)
     */
    public function register_stored_taxonomies() {
        // Читаем напрямую из ACF записей
        $acf_taxonomies = get_posts(array(
            'post_type'      => 'acf-taxonomy',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));
        
        $this->log_debug('register_stored_taxonomies', array(
            'count' => count($acf_taxonomies)
        ));
        
        foreach ($acf_taxonomies as $acf_tax) {
            $data = maybe_unserialize($acf_tax->post_content);
            if (!is_array($data)) {
                continue;
            }
            
            $taxonomy_key = $data['taxonomy'] ?? '';
            if (empty($taxonomy_key)) {
                continue;
            }
            
            // Формируем данные в формате, ожидаемом register_single_taxonomy
            $taxonomy_data = array(
                'active' => true,
                'args'   => $data,
            );
            
            $this->register_single_taxonomy($taxonomy_key, $taxonomy_data);
        }
    }

    private function register_single_taxonomy(string $taxonomy_key, array $taxonomy_data): void {
        $is_active = !empty($taxonomy_data['active']);

        if (!$is_active) {
            return;
        }

        $saved_args = $taxonomy_data['args'] ?? array();
        
        // Поддержка двух форматов: ACF полный и упрощенный
        $post_types = array();
        if (isset($saved_args['object_type'])) {
            // ACF формат
            $post_types = array_filter((array) $saved_args['object_type']);
        } elseif (isset($saved_args['post_types'])) {
            // Упрощенный формат
            $post_types = array_filter((array) $saved_args['post_types']);
        }
        
        if (empty($post_types)) {
            $post_types = array('post');
        }

        // Извлекаем labels из ACF формата или создаем из упрощенного
        if (isset($saved_args['labels']) && is_array($saved_args['labels'])) {
            // ACF формат - используем готовые labels
            $labels = $saved_args['labels'];
        } else {
            // Упрощенный формат - создаем labels
            $singular = $saved_args['singular_name'] ?? ucfirst($taxonomy_key);
            $plural = $saved_args['plural_name'] ?? $singular;

            $labels = array(
                'name' => $plural,
                'singular_name' => $singular,
                'search_items' => 'Найти ' . $plural,
                'all_items' => 'Все ' . $plural,
                'edit_item' => 'Редактировать ' . $singular,
                'view_item' => 'Просмотреть ' . $singular,
                'update_item' => 'Обновить ' . $singular,
                'add_new_item' => 'Добавить ' . $singular,
                'new_item_name' => 'Новый ' . $singular,
                'menu_name' => $plural
            );
        }

        $register_args = array(
            'labels' => $labels,
            'hierarchical' => !empty($saved_args['hierarchical']),
            'show_ui' => !empty($saved_args['show_ui']),
            'show_in_menu' => !empty($saved_args['show_in_menu']),
            'show_in_rest' => !empty($saved_args['show_in_rest']),
            'show_admin_column' => !empty($saved_args['show_admin_column']),
            'description' => $saved_args['description'] ?? '',
            'public' => !empty($saved_args['public']),
            'publicly_queryable' => !empty($saved_args['publicly_queryable']),
            'show_in_quick_edit' => !empty($saved_args['show_in_quick_edit']),
            'show_tagcloud' => !empty($saved_args['show_tagcloud']),
            'rewrite' => array(
                'slug' => $saved_args['rewrite']['slug'] ?? $taxonomy_key,
                'with_front' => $saved_args['rewrite']['with_front'] ?? true,
                'hierarchical' => !empty($saved_args['hierarchical'])
            )
        );

        if (!empty($saved_args['show_in_graphql'])) {
            $register_args['show_in_graphql'] = true;
            $register_args['graphql_single_name'] = $saved_args['graphql_single_name'] ?? $taxonomy_key;
            $register_args['graphql_plural_name'] = $saved_args['graphql_plural_name'] ?? $taxonomy_key . 's';
        }

        error_log('ACF Taxonomy Creator: Registering taxonomy ' . $taxonomy_key . ' for post types: ' . implode(', ', $post_types));
        register_taxonomy($taxonomy_key, $post_types, $register_args);

        if (taxonomy_exists($taxonomy_key)) {
            error_log('ACF Taxonomy Creator: Taxonomy ' . $taxonomy_key . ' registered successfully');
        } else {
            error_log('ACF Taxonomy Creator: ERROR - Taxonomy ' . $taxonomy_key . ' was NOT registered');
        }
    }
    
    /**
     * Создать новую таксономию
     */
    public function create_taxonomy($args) {
        $args = (array) $args;
        $raw_taxonomy = $this->extract_value($args, array('taxonomy', 'taxonomy_key', 'taxonomyKey', 'key'), '');
        $raw_singular = $this->extract_value($args, array('singular_name', 'singularLabel', 'singularName', 'label'), '');

        $taxonomy_key = sanitize_key((string) $raw_taxonomy);
        $singular_name = sanitize_text_field((string) $raw_singular);

        $this->log_debug('taxonomy_create', array(
            'raw_args' => $args,
            'normalized_taxonomy' => $taxonomy_key,
            'normalized_singular' => $singular_name
        ));

        if ($taxonomy_key === '' || $singular_name === '') {
            return new WP_Error(
                'missing_params',
                'Пустые параметры: taxonomy=' . var_export($taxonomy_key, true) . ', singular_name=' . var_export($singular_name, true)
            );
        }

        $plural_name_raw = $this->extract_value($args, array('plural_name', 'pluralLabel', 'pluralName'), $singular_name);
        $plural_name = sanitize_text_field((string) $plural_name_raw);

        // Проверяем существование таксономии в WordPress
        if (taxonomy_exists($taxonomy_key)) {
            return new WP_Error('taxonomy_exists', 'Таксономия с таким ключом уже зарегистрирована в WordPress');
        }
        
        // Проверяем существование в ACF UI
        $existing = get_posts(array(
            'post_type' => 'acf-taxonomy',
            'name' => $taxonomy_key,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ));
        
        if (!empty($existing)) {
            return new WP_Error('taxonomy_exists', 'Таксономия с таким ключом уже существует в ACF');
        }

        $post_types = isset($args['post_types']) ? (array) $args['post_types'] : (array) ($args['object_type'] ?? array('post'));
        $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));
        if (empty($post_types)) {
            $post_types = array('post');
        }

        $normalized_args = array_merge($args, array(
            'taxonomy' => $taxonomy_key,
            'singular_name' => $singular_name,
            'plural_name' => $plural_name,
            'post_types' => $post_types,
            'hierarchical' => !empty($args['hierarchical']),
            'show_ui' => array_key_exists('show_ui', $args) ? (bool) $args['show_ui'] : true,
            'show_in_menu' => array_key_exists('show_in_menu', $args) ? (bool) $args['show_in_menu'] : true,
            'show_in_rest' => array_key_exists('show_in_rest', $args) ? (bool) $args['show_in_rest'] : true,
            'show_admin_column' => array_key_exists('show_admin_column', $args) ? (bool) $args['show_admin_column'] : true,
            'show_in_graphql' => array_key_exists('show_in_graphql', $args) ? (bool) $args['show_in_graphql'] : false,
            'graphql_single_name' => isset($args['graphql_single_name']) ? sanitize_text_field((string) $args['graphql_single_name']) : '',
            'graphql_plural_name' => isset($args['graphql_plural_name']) ? sanitize_text_field((string) $args['graphql_plural_name']) : '',
            'description' => isset($args['description']) ? sanitize_textarea_field((string) $args['description']) : ''
        ));

        $taxonomy_data = $this->prepare_acf_ui_taxonomy($normalized_args);
        $acf_taxonomy = $this->prepare_acf_taxonomy_payload($taxonomy_data);

        if (!function_exists('acf_update_taxonomy')) {
            return new WP_Error('acf_not_available', 'Функция acf_update_taxonomy недоступна');
        }

        $result = acf_update_taxonomy($acf_taxonomy);

        if (is_wp_error($result)) {
            return $result;
        }

        $stored_taxonomies = get_option('acf_mcp_manager_taxonomies', array());
        $stored_taxonomies[$taxonomy_key] = array(
            'acf_post_id' => $result['ID'] ?? 0,
            'args' => $acf_taxonomy, // Сохраняем полный ACF формат вместо упрощенного
            'active' => true,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'method' => 'mcp',
            'taxonomy_key' => $taxonomy_key
        );

        update_option('acf_mcp_manager_taxonomies', $stored_taxonomies);

        if (!empty($stored_taxonomies[$taxonomy_key]['active'])) {
            $this->register_single_taxonomy($taxonomy_key, $stored_taxonomies[$taxonomy_key]);
            flush_rewrite_rules();
        }

        return array(
            'success' => true,
            'post_id' => $result['ID'] ?? 0,
            'taxonomy_key' => $taxonomy_key,
            'message' => sprintf('Таксономия "%s" успешно создана', $singular_name)
        );
    }

    private function prepare_acf_taxonomy_payload(array $taxonomy_data): array {
        $defaults = array();
        if (function_exists('acf_get_internal_post_type_instance')) {
            $instance = acf_get_internal_post_type_instance('acf-taxonomy');
            if ($instance && method_exists($instance, 'get_settings_array')) {
                $defaults = $instance->get_settings_array();
            }
        }

        $post_types = array_filter((array)($taxonomy_data['post_types'] ?? array()));
        if (empty($post_types)) {
            $post_types = array('post');
        }

        $labels_defaults = $defaults['labels'] ?? array();
        $labels = array(
            'name' => $taxonomy_data['plural_name'],
            'singular_name' => $taxonomy_data['singular_name'],
            'menu_name' => $taxonomy_data['plural_name'],
            'search_items' => 'Найти ' . $taxonomy_data['plural_name'],
            'all_items' => 'Все ' . $taxonomy_data['plural_name'],
            'edit_item' => 'Редактировать ' . $taxonomy_data['singular_name'],
            'view_item' => 'Просмотреть ' . $taxonomy_data['singular_name'],
            'update_item' => 'Обновить ' . $taxonomy_data['singular_name'],
            'add_new_item' => 'Добавить ' . $taxonomy_data['singular_name'],
            'new_item_name' => 'Новый ' . $taxonomy_data['singular_name']
        );
        $labels = array_merge($labels_defaults, array_filter($labels));

        $rewrite_defaults = $defaults['rewrite'] ?? array();
        $rewrite = array_merge($rewrite_defaults, array(
            'slug' => $taxonomy_data['taxonomy'],
            'with_front' => true,
            'rewrite_hierarchical' => !empty($taxonomy_data['hierarchical'])
        ));

        $acf_taxonomy = array_merge($defaults, array(
            'key' => 'taxonomy_' . $taxonomy_data['taxonomy'],
            'title' => $taxonomy_data['plural_name'],
            'taxonomy' => $taxonomy_data['taxonomy'],
            'object_type' => $post_types,
            'labels' => $labels,
            'description' => $taxonomy_data['description'] ?? '',
            'hierarchical' => !empty($taxonomy_data['hierarchical']),
            'show_ui' => !empty($taxonomy_data['show_ui']),
            'show_in_menu' => !empty($taxonomy_data['show_in_menu']),
            'show_in_nav_menus' => true,
            'show_in_rest' => !empty($taxonomy_data['show_in_rest']),
            'show_admin_column' => !empty($taxonomy_data['show_admin_column']),
            'show_tagcloud' => false,
            'show_in_quick_edit' => true,
            'rewrite' => $rewrite,
            'rest_base' => $taxonomy_data['taxonomy'],
            'public' => true,
            'publicly_queryable' => true,
            'active' => true
        ));

        if (!empty($taxonomy_data['show_in_graphql'])) {
            $acf_taxonomy['show_in_graphql'] = 1;
            $acf_taxonomy['graphql_single_name'] = $taxonomy_data['graphql_single_name'] ?? '';
            $acf_taxonomy['graphql_plural_name'] = $taxonomy_data['graphql_plural_name'] ?? '';
        } else {
            $acf_taxonomy['show_in_graphql'] = 0;
        }

        return $acf_taxonomy;
    }
    
    /**
     * Подготовка данных для ACF UI Taxonomy
     */
    private function prepare_acf_ui_taxonomy($args) {
        $defaults = array(
            'taxonomy' => '',
            'singular_name' => '',
            'plural_name' => '',
            'post_types' => array('post'),
            'hierarchical' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'description' => '',
            'show_in_graphql' => 0,
            'graphql_single_name' => '',
            'graphql_plural_name' => ''
        );
        
        $taxonomy_data = wp_parse_args($args, $defaults);
        
        // Если plural_name не указан, используем singular_name
        if (empty($taxonomy_data['plural_name'])) {
            $taxonomy_data['plural_name'] = $taxonomy_data['singular_name'];
        }
        
        // Автоматически генерируем graphql имена если show_in_graphql включен
        if (!empty($taxonomy_data['show_in_graphql'])) {
            if (empty($taxonomy_data['graphql_single_name'])) {
                $slug = str_replace(array('-', '_'), ' ', $taxonomy_data['taxonomy']);
                $slug = ucwords($slug);
                $taxonomy_data['graphql_single_name'] = str_replace(' ', '', $slug);
            }
            if (empty($taxonomy_data['graphql_plural_name'])) {
                $taxonomy_data['graphql_plural_name'] = $taxonomy_data['graphql_single_name'] . 's';
            }
        }
        
        return $taxonomy_data;
    }
    
    /**
     * Обновить таксономию
     */
    public function update_taxonomy($taxonomy_key, $args) {
        $stored_taxonomies = get_option('acf_mcp_manager_taxonomies', array());
        
        if (!isset($stored_taxonomies[$taxonomy_key])) {
            return new WP_Error('taxonomy_not_found', 'Таксономия не найдена');
        }
        
        $post_id = $stored_taxonomies[$taxonomy_key]['acf_post_id'] ?? 0;
        
        if ($post_id) {
            $post = get_post($post_id);
            if (!$post) {
                return new WP_Error('post_not_found', 'ACF UI пост не найден');
            }
            
            // Читаем текущие данные из post_content
            $current_data = maybe_unserialize($post->post_content);
            if (!is_array($current_data)) {
                $current_data = array();
            }
            
            // Объединяем с новыми данными
            $updated_data = array_merge($current_data, array_filter($args, function($key) {
                return in_array($key, array(
                    'singular_label', 'plural_label', 'post_types', 'hierarchical',
                    'show_ui', 'show_in_menu', 'show_in_rest', 'show_admin_column',
                    'description', 'show_in_graphql', 'graphql_single_name', 'graphql_plural_name'
                ));
            }, ARRAY_FILTER_USE_KEY));
            
            // Обновляем WordPress post
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $args['singular_name'] ?? $post->post_title,
                'post_content' => maybe_serialize($updated_data)
            ));
        }
        
        // Обновляем в нашей системе
        $taxonomy_data = $this->prepare_acf_ui_taxonomy($args);
        $stored_taxonomies[$taxonomy_key]['args'] = array_merge($stored_taxonomies[$taxonomy_key]['args'], $taxonomy_data);
        $stored_taxonomies[$taxonomy_key]['updated_at'] = current_time('mysql');
        
        update_option('acf_mcp_manager_taxonomies', $stored_taxonomies);
        
        return array(
            'success' => true,
            'message' => sprintf('Таксономия "%s" обновлена', $taxonomy_key)
        );
    }
    
    /**
     * Удалить таксономию
     */
    public function delete_taxonomy($taxonomy_key, $permanent = false) {
        $stored_taxonomies = get_option('acf_mcp_manager_taxonomies', array());
        
        if (!isset($stored_taxonomies[$taxonomy_key])) {
            return new WP_Error('taxonomy_not_found', 'Таксономия не найдена');
        }
        
        if ($permanent) {
            // Полное удаление
            $post_id = $stored_taxonomies[$taxonomy_key]['acf_post_id'] ?? 0;
            
            if ($post_id && get_post($post_id)) {
                wp_delete_post($post_id, true);
            }
            
            unset($stored_taxonomies[$taxonomy_key]);
            update_option('acf_mcp_manager_taxonomies', $stored_taxonomies);
            
            return array(
                'success' => true,
                'message' => sprintf('Таксономия "%s" полностью удалена', $taxonomy_key)
            );
        } else {
            // Деактивация
            $stored_taxonomies[$taxonomy_key]['active'] = false;
            update_option('acf_mcp_manager_taxonomies', $stored_taxonomies);
            
            return array(
                'success' => true,
                'message' => sprintf('Таксономия "%s" деактивирована', $taxonomy_key)
            );
        }
    }
    
    /**
     * Активировать/деактивировать таксономию
     */
    public function toggle_taxonomy($taxonomy_key, $active = true) {
        $stored_taxonomies = get_option('acf_mcp_manager_taxonomies', array());
        
        if (!isset($stored_taxonomies[$taxonomy_key])) {
            return new WP_Error('taxonomy_not_found', 'Таксономия не найдена');
        }
        
        $stored_taxonomies[$taxonomy_key]['active'] = $active;
        update_option('acf_mcp_manager_taxonomies', $stored_taxonomies);
        
        $status = $active ? 'активирована' : 'деактивирована';
        return array(
            'success' => true,
            'message' => sprintf('Таксономия "%s" %s', $taxonomy_key, $status)
        );
    }
    
    /**
     * Получить список таксономий
     */
    public function get_taxonomies() {
        return get_option('acf_mcp_manager_taxonomies', array());
    }
    
    /**
     * Получить шаблоны таксономий
     */
    public function get_templates() {
        return array(
            'category' => array(
                'singular_name' => 'Категория',
                'plural_name' => 'Категории',
                'hierarchical' => true,
                'description' => 'Иерархическая таксономия для категоризации контента'
            ),
            'tag' => array(
                'singular_name' => 'Тег',
                'plural_name' => 'Теги',
                'hierarchical' => false,
                'description' => 'Не иерархическая таксономия для тегирования контента'
            ),
            'location' => array(
                'singular_name' => 'Локация',
                'plural_name' => 'Локации',
                'hierarchical' => true,
                'description' => 'Географические локации'
            ),
            'service_type' => array(
                'singular_name' => 'Тип услуги',
                'plural_name' => 'Типы услуг',
                'hierarchical' => true,
                'description' => 'Категории услуг'
            )
        );
    }

    private function log_debug(string $context, array $data = array()): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('ACF Taxonomy Creator [' . $context . ']: ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function extract_value($source, array $keys, $default = '') {
        foreach ($keys as $key) {
            if (is_array($source) && array_key_exists($key, $source)) {
                return $source[$key];
            }

            if (is_object($source) && isset($source->{$key})) {
                return $source->{$key};
            }
        }

        return $default;
    }
}

