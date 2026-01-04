<?php
/**
 * Plugin Name: ACF MCP Manager
 * Plugin URI: https://github.com/koystrubvs/acf-mcp-manager
 * Description: Плагин для создания и управления структурой WordPress через MCP API. Custom Post Types, Options Pages, Taxonomies, Field Groups и другое. Интегрируется с ACF Pro.
 * Version: 4.0.1
 * Author: koystrubvs
 * License: GPL v2 or later
 * Text Domain: acf-mcp-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

// Определяем константы плагина
define('ACF_MCP_MANAGER_VERSION', '4.0.1');
define('ACF_MCP_MANAGER_PATH', plugin_dir_path(__FILE__));
define('ACF_MCP_MANAGER_URL', plugin_dir_url(__FILE__));
define('ACF_MCP_MANAGER_BASENAME', plugin_basename(__FILE__));

/**
 * Главный класс плагина
 */
class ACF_MCP_Manager {
    
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
        error_log('ACF MCP Manager: Plugin constructor called');
        $this->init_hooks();
        $this->load_dependencies();
        error_log('ACF MCP Manager: Plugin initialized');
    }
    
    /**
     * Инициализация хуков
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Интеграция с WordPress MCP если плагин активен
        if (class_exists('Automattic\WordpressMcp\Core\WpMcp')) {
            add_action('wordpress_mcp_init', array($this, 'register_mcp_tools'));
        }
        
        // Хуки активации/деактивации
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Загрузка зависимостей
     */
    private function load_dependencies() {
        // Проверяем что файлы существуют перед загрузкой
        $includes = array(
            'class-cpt-creator.php',
            'class-options-page-creator.php',
            'class-taxonomy-creator.php',
            'class-field-group-creator.php',
            'class-acf-values-manager.php',
            'class-term-manager.php',
            'class-media-manager.php',
            'class-rest-api.php', 
            'class-mcp-integration.php'
        );
        
        foreach ($includes as $file) {
            $path = ACF_MCP_MANAGER_PATH . 'includes/' . $file;
            if (file_exists($path)) {
                require_once $path;
            } else {
                error_log("ACF MCP Manager: Файл не найден - " . $path);
            }
        }
    }
    
    /**
     * Инициализация плагина
     */
    public function init() {
        error_log('ACF MCP Manager: init() method called');
        
        // Проверяем наличие ACF - используем правильное имя класса
        if (!function_exists('acf') && !class_exists('acf')) {
            error_log('ACF MCP Manager: ACF not found, showing notice');
            add_action('admin_notices', array($this, 'acf_missing_notice'));
            return;
        }
        
        error_log('ACF MCP Manager: ACF found, initializing components...');
        
        // Инициализируем компоненты только если классы загружены
        if (class_exists('ACF_MCP_CPT_Creator')) {
            error_log('ACF MCP Manager: Initializing ACF_MCP_CPT_Creator...');
            ACF_MCP_CPT_Creator::get_instance();
            error_log('ACF MCP Manager: ACF_MCP_CPT_Creator initialized');
        } else {
            error_log('ACF MCP Manager: ACF_MCP_CPT_Creator class not found');
        }
        
        if (class_exists('ACF_MCP_Options_Page_Creator')) {
            error_log('ACF MCP Manager: Initializing ACF_MCP_Options_Page_Creator...');
            ACF_MCP_Options_Page_Creator::get_instance();
            error_log('ACF MCP Manager: ACF_MCP_Options_Page_Creator initialized');
        } else {
            error_log('ACF MCP Manager: ACF_MCP_Options_Page_Creator class not found');
        }
        
        if (class_exists('ACF_MCP_Taxonomy_Creator')) {
            error_log('ACF MCP Manager: Initializing ACF_MCP_Taxonomy_Creator...');
            ACF_MCP_Taxonomy_Creator::get_instance();
            error_log('ACF MCP Manager: ACF_MCP_Taxonomy_Creator initialized');
        } else {
            error_log('ACF MCP Manager: ACF_MCP_Taxonomy_Creator class not found');
        }
        
        if (class_exists('ACF_MCP_Field_Group_Creator')) {
            error_log('ACF MCP Manager: Initializing ACF_MCP_Field_Group_Creator...');
            ACF_MCP_Field_Group_Creator::get_instance();
            error_log('ACF MCP Manager: ACF_MCP_Field_Group_Creator initialized');
        } else {
            error_log('ACF MCP Manager: ACF_MCP_Field_Group_Creator class not found');
        }
        
        if (class_exists('ACF_MCP_REST_API')) {
            ACF_MCP_REST_API::get_instance();
        } else {
            error_log('ACF MCP Manager: ACF_MCP_REST_API class not found');
        }
        
        // Загружаем переводы
        load_plugin_textdomain('acf-mcp-manager', false, dirname(ACF_MCP_MANAGER_BASENAME) . '/languages');
    }
    
    /**
     * Регистрация REST API маршрутов
     */
    public function register_rest_routes() {
        if (class_exists('ACF_MCP_REST_API')) {
            ACF_MCP_REST_API::get_instance()->register_routes();
        }
    }
    
    /**
     * Регистрация MCP tools
     */
    public function register_mcp_tools() {
        error_log('ACF MCP Manager: register_mcp_tools called');
        if (class_exists('ACF_MCP_Integration')) {
            ACF_MCP_Integration::get_instance()->register_tools();
            error_log('ACF MCP Manager: MCP tools registered successfully');
        } else {
            error_log('ACF MCP Manager: ACF_MCP_Integration class not found');
        }
    }
    
    /**
     * Активация плагина
     */
    public function activate() {
        // Миграция настроек со старого плагина
        $old_settings = get_option('acf_cpt_manager_settings');
        if ($old_settings) {
            update_option('acf_mcp_manager_settings', $old_settings);
        }
        
        // Создаем базовые настройки если их нет
        if (!get_option('acf_mcp_manager_settings')) {
            $default_settings = array(
                'version' => ACF_MCP_MANAGER_VERSION,
                'enable_mcp_integration' => true,
                'enable_rest_api' => true,
                'default_post_type_args' => array(
                    'public' => true,
                    'publicly_queryable' => true,
                    'show_ui' => true,
                    'show_in_menu' => true,
                    'show_in_rest' => true,
                    'has_archive' => true,
                    'supports' => array('title', 'editor', 'thumbnail')
                )
            );
            
            add_option('acf_mcp_manager_settings', $default_settings);
        }
        
        // Сбрасываем rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Деактивация плагина
     */
    public function deactivate() {
        // Сбрасываем rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Уведомление об отсутствии ACF
     */
    public function acf_missing_notice() {
        $message = sprintf(
            __('Плагин <strong>%s</strong> требует установки плагина <strong>Advanced Custom Fields</strong>.', 'acf-mcp-manager'),
            'ACF MCP Manager'
        );
        
        printf('<div class="notice notice-error"><p>%s</p></div>', $message);
    }
}

// Инициализация плагина
function ACF_MCP_Manager() {
    return ACF_MCP_Manager::get_instance();
}

// Запуск плагина  
error_log('ACF MCP Manager: Starting plugin initialization...');
ACF_MCP_Manager();
error_log('ACF MCP Manager: Plugin started successfully');

