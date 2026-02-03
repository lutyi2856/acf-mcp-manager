<?php
/**
 * Класс для создания и управления страницами опций ACF
 *
 * @package ACF_MCP_Manager
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс ACF_MCP_Options_Page_Creator
 */
class ACF_MCP_Options_Page_Creator {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;

    /**
     * Ключи хранения данных (новый/legacy)
     */
    private $option_key = 'acf_mcp_manager_options_pages';
    private $legacy_option_key = 'acf_cpt_manager_options_pages';
    
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
        // Регистрируем сохраненные страницы опций
        // Если ACF уже загружен, регистрируем сразу
        if (function_exists('acf_add_options_page')) {
            $this->register_stored_options_pages();
        } else {
            // Если ACF еще не загружен, ждем хука
            add_action('acf/init', array($this, 'register_stored_options_pages'));
        }
    }

    /**
     * Получить сохранённые страницы опций с миграцией legacy → new
     */
    private function get_stored_pages(): array {
        $stored = get_option($this->option_key);
        if (!is_array($stored)) {
            $stored = array();
        }

        $legacy = get_option($this->legacy_option_key);
        if (is_array($legacy) && !empty($legacy)) {
            $changed = false;
            foreach ($legacy as $slug => $data) {
                if (!isset($stored[$slug])) {
                    $stored[$slug] = $data;
                    $changed = true;
                }
            }
            if ($changed) {
                update_option($this->option_key, $stored);
            }
        }

        return $stored;
    }

    private function save_stored_pages(array $pages): void {
        update_option($this->option_key, $pages);
    }

    private function log_debug(string $message): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        error_log('ACF MCP Manager (Options Page): ' . $message);
    }
    
    /**
     * Регистрация сохраненных страниц опций
     */
    public function register_stored_options_pages() {
        if (!function_exists('acf_add_options_page')) {
            $this->log_debug('acf_add_options_page не доступна');
            return;
        }
        
        $stored_pages = $this->get_stored_pages();
        $this->log_debug('Найдено ' . count($stored_pages) . ' страниц опций');
        
        foreach ($stored_pages as $page_slug => $page_data) {
            if (isset($page_data['active']) && $page_data['active']) {
                $this->log_debug("Регистрация страницы опций {$page_slug}");
                
                acf_add_options_page($page_data['args']);
                
                // Регистрируем подстраницы если есть
                if (!empty($page_data['sub_pages'])) {
                    foreach ($page_data['sub_pages'] as $sub_page) {
                        acf_add_options_sub_page($sub_page);
                    }
                }
            }
        }
    }
    
    /**
     * Создать новую страницу опций
     */
    public function create_options_page($args) {
        // Валидация обязательных параметров
        if (empty($args['page_title']) || empty($args['menu_slug'])) {
            return new WP_Error('missing_params', 'Требуются параметры page_title и menu_slug');
        }
        
        // Проверяем что страница не существует в ACF UI
        $existing = get_posts(array(
            'post_type' => 'acf-ui-options-page',
            'name' => $args['menu_slug'],
            'posts_per_page' => 1,
            'post_status' => 'any'
        ));
        
        if (!empty($existing)) {
            return new WP_Error('page_exists', 'Страница опций с таким slug уже существует');
        }
        
        // Подготавливаем аргументы для ACF UI Options Page
        $page_data = $this->prepare_acf_ui_options_page($args);
        
        // Подготавливаем данные для сериализации (формат ACF)
        // ACF хранит ВСЕ данные в post_content как сериализованный массив!
        $acf_data = array(
            'page_title' => $page_data['page_title'],
            'menu_title' => $page_data['menu_title'],
            'menu_slug' => $page_data['menu_slug'],
            'parent_slug' => $page_data['parent_slug'],
            'capability' => $page_data['capability'],
            'redirect' => $page_data['redirect'] ? 1 : 0,
            'position' => $page_data['position'],
            'icon_url' => $page_data['icon_url'],
            'description' => $page_data['description'] ?? '',
            'show_in_graphql' => $page_data['show_in_graphql'] ?? 0,
            'graphql_single_name' => $page_data['graphql_single_name'] ?? ''
        );
        
        // Создаем Options Page через ACF UI систему (как WordPress post)
        $post_id = wp_insert_post(array(
            'post_title' => $page_data['page_title'],
            'post_name' => $page_data['menu_slug'],
            'post_excerpt' => sanitize_title($page_data['page_title']),
            'post_content' => maybe_serialize($acf_data), // Все данные в post_content!
            'post_type' => 'acf-ui-options-page',
            'post_status' => 'publish'
        ));
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Сохраняем подстраницы если есть
        if (!empty($args['sub_pages'])) {
            foreach ($args['sub_pages'] as $index => $sub_page) {
                $this->create_options_page($sub_page);
            }
        }
        
        // Также сохраняем в нашей системе для совместимости
        $stored_pages = $this->get_stored_pages();
        $stored_pages[$args['menu_slug']] = array(
            'acf_post_id' => $post_id,
            'args' => $page_data,
            'sub_pages' => $args['sub_pages'] ?? array(),
            'active' => true,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'method' => 'acf_ui'
        );
        $this->save_stored_pages($stored_pages);
        
        // Регистрируем страницу сразу
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page($page_data);
        }
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'menu_slug' => $args['menu_slug'],
            'message' => sprintf('Страница опций "%s" успешно создана', $args['page_title'])
        );
    }
    
    /**
     * Подготовка данных для ACF UI Options Page
     */
    private function prepare_acf_ui_options_page($args) {
        $defaults = array(
            'page_title' => '',
            'menu_title' => '',
            'menu_slug' => '',
            'capability' => 'manage_options',
            'redirect' => true,
            'position' => '',
            'icon_url' => 'dashicons-admin-generic',
            'parent_slug' => '',
            'description' => '',
            'show_in_graphql' => 0,
            'graphql_single_name' => ''
        );
        
        $page_data = wp_parse_args($args, $defaults);
        
        // Если menu_title не указан, используем page_title
        if (empty($page_data['menu_title'])) {
            $page_data['menu_title'] = $page_data['page_title'];
        }
        
        // Автоматически генерируем graphql_single_name если show_in_graphql включен
        if (!empty($page_data['show_in_graphql']) && empty($page_data['graphql_single_name'])) {
            // Преобразуем menu_slug в camelCase для GraphQL
            $slug = str_replace(array('-', '_'), ' ', $page_data['menu_slug']);
            $slug = ucwords($slug);
            $page_data['graphql_single_name'] = str_replace(' ', '', $slug);
        }
        
        return $page_data;
    }
    
    
    /**
     * Получить список созданных страниц опций
     */
    public function get_options_pages() {
        $stored_pages = $this->get_stored_pages();
        
        // Добавляем дополнительную информацию
        foreach ($stored_pages as $slug => &$page) {
            $page['menu_slug'] = $slug;
            $page['sub_pages_count'] = count($page['sub_pages'] ?? array());
        }
        
        return $stored_pages;
    }
    
    /**
     * Обновить страницу опций
     */
    public function update_options_page($menu_slug, $args) {
        $stored_pages = $this->get_stored_pages();
        
        if (!isset($stored_pages[$menu_slug])) {
            return new WP_Error('page_not_found', 'Страница опций не найдена');
        }
        
        $post_id = $stored_pages[$menu_slug]['acf_post_id'] ?? 0;
        
        if ($post_id) {
            // Получаем текущий пост
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
                    'page_title', 'menu_title', 'menu_slug', 'capability', 
                    'icon_url', 'position', 'parent_slug', 'redirect',
                    'description', 'show_in_graphql', 'graphql_single_name'
                ));
            }, ARRAY_FILTER_USE_KEY));
            
            // Обновляем WordPress post с новыми данными в post_content
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $args['page_title'] ?? $post->post_title,
                'post_content' => maybe_serialize($updated_data) // ACF формат!
            ));
        }
        
        // Обновляем в нашей системе
        $page_data = $this->prepare_acf_ui_options_page($args);
        $stored_pages[$menu_slug]['args'] = array_merge($stored_pages[$menu_slug]['args'], $page_data);
        $stored_pages[$menu_slug]['updated_at'] = current_time('mysql');
        
        if (isset($args['sub_pages'])) {
            $stored_pages[$menu_slug]['sub_pages'] = $args['sub_pages'];
        }
        
        $this->save_stored_pages($stored_pages);
        
        return array(
            'success' => true,
            'message' => sprintf('Страница опций "%s" обновлена', $menu_slug)
        );
    }
    
    /**
     * Удалить страницу опций
     * 
     * @param string $menu_slug Slug страницы
     * @param bool $permanent Полное удаление (true) или деактивация (false)
     */
    public function delete_options_page($menu_slug, $permanent = false) {
        $stored_pages = $this->get_stored_pages();
        
        if (!isset($stored_pages[$menu_slug])) {
            return new WP_Error('page_not_found', 'Страница опций не найдена');
        }
        
        if ($permanent) {
            // Полное удаление
            $post_id = $stored_pages[$menu_slug]['acf_post_id'] ?? 0;
            
            // Удаляем ACF UI пост если существует
            if ($post_id) {
                // Предпочтительно — через ACF internal API (чистит кэши)
                if (function_exists('acf_delete_internal_post_type')) {
                    acf_delete_internal_post_type($post_id, 'acf-ui-options-page');
                } elseif (get_post($post_id)) {
                    wp_delete_post($post_id, true); // true = обход корзины, полное удаление
                }
            }
            
            // Удаляем из нашей системы
            unset($stored_pages[$menu_slug]);
            $this->save_stored_pages($stored_pages);
            
            return array(
                'success' => true,
                'message' => sprintf('Страница опций "%s" полностью удалена', $menu_slug)
            );
        } else {
            // Деактивируем страницу
            $post_id = $stored_pages[$menu_slug]['acf_post_id'] ?? 0;
            if ($post_id && function_exists('acf_update_internal_post_type_active_status')) {
                acf_update_internal_post_type_active_status($post_id, false, 'acf-ui-options-page');
            }
            $stored_pages[$menu_slug]['active'] = false;
            $this->save_stored_pages($stored_pages);
            
            return array(
                'success' => true,
                'message' => sprintf('Страница опций "%s" деактивирована', $menu_slug)
            );
        }
    }
    
    /**
     * Активировать/деактивировать страницу опций
     */
    public function toggle_options_page($menu_slug, $active = true) {
        $stored_pages = $this->get_stored_pages();
        
        if (!isset($stored_pages[$menu_slug])) {
            return new WP_Error('page_not_found', 'Страница опций не найдена');
        }
        
        $post_id = $stored_pages[$menu_slug]['acf_post_id'] ?? 0;
        if ($post_id && function_exists('acf_update_internal_post_type_active_status')) {
            acf_update_internal_post_type_active_status($post_id, (bool) $active, 'acf-ui-options-page');
        }

        $stored_pages[$menu_slug]['active'] = (bool) $active;
        $this->save_stored_pages($stored_pages);
        
        $status = $active ? 'активирована' : 'деактивирована';
        return array(
            'success' => true,
            'message' => sprintf('Страница опций "%s" %s', $menu_slug, $status)
        );
    }
    
    /**
     * Получить шаблоны страниц опций
     */
    public function get_templates() {
        return array(
            'general_settings' => array(
                'name' => 'Общие настройки',
                'description' => 'Базовая страница настроек сайта',
                'args' => array(
                    'page_title' => 'Настройки сайта',
                    'menu_title' => 'Настройки сайта',
                    'menu_slug' => 'site-settings',
                    'icon_url' => 'dashicons-admin-settings'
                )
            ),
            'theme_settings' => array(
                'name' => 'Настройки темы',
                'description' => 'Настройки оформления и внешнего вида',
                'args' => array(
                    'page_title' => 'Настройки темы',
                    'menu_title' => 'Настройки темы',
                    'menu_slug' => 'theme-settings',
                    'icon_url' => 'dashicons-admin-appearance'
                )
            ),
            'contact_info' => array(
                'name' => 'Контактная информация',
                'description' => 'Контакты, социальные сети, адреса',
                'args' => array(
                    'page_title' => 'Контактная информация',
                    'menu_title' => 'Контакты',
                    'menu_slug' => 'contact-info',
                    'icon_url' => 'dashicons-email'
                )
            ),
            'seo_settings' => array(
                'name' => 'SEO настройки',
                'description' => 'Настройки поисковой оптимизации',
                'args' => array(
                    'page_title' => 'SEO настройки',
                    'menu_title' => 'SEO',
                    'menu_slug' => 'seo-settings',
                    'icon_url' => 'dashicons-search'
                )
            ),
            'header_footer' => array(
                'name' => 'Шапка и подвал',
                'description' => 'Настройки header и footer',
                'args' => array(
                    'page_title' => 'Шапка и подвал',
                    'menu_title' => 'Шапка/Подвал',
                    'menu_slug' => 'header-footer',
                    'icon_url' => 'dashicons-layout'
                )
            )
        );
    }
}
