<?php
/**
 * MCP интеграция для управления типами записей
 *
 * @package ACF_MCP_Manager
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WordpressMcp\Core\RegisterMcpTool;

/**
 * Класс ACF_MCP_Integration
 */
class ACF_MCP_Integration {
    
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
        // Конструктор пустой, регистрация происходит через register_tools()
    }
    
    /**
     * Регистрация MCP tools
     */
    public function register_tools() {
        // Проверяем что класс RegisterMcpTool существует
        if (!class_exists('Automattic\WordpressMcp\Core\RegisterMcpTool')) {
            error_log('ACF MCP Manager: RegisterMcpTool class not found');
            return;
        }
        
        error_log('ACF MCP Manager: Registering MCP tools...');
        
        // Tool для создания типа записи
        new RegisterMcpTool(array(
            'name' => 'create_custom_post_type',
            'description' => 'Создать новый пользовательский тип записи в WordPress. Поддерживает все стандартные параметры и автоматически добавляет поддержку GraphQL.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'key' => array(
                        'type' => 'string',
                        'description' => 'Уникальный ключ типа записи (латинские буквы, цифры, подчеркивания)'
                    ),
                    'label' => array(
                        'type' => 'string',
                        'description' => 'Название типа записи во множественном числе'
                    ),
                    'singular_label' => array(
                        'type' => 'string',
                        'description' => 'Название типа записи в единственном числе'
                    ),
                    'description' => array(
                        'type' => 'string',
                        'description' => 'Описание типа записи'
                    ),
                    'menu_icon' => array(
                        'type' => 'string',
                        'description' => 'Иконка для меню (dashicons класс или URL изображения)'
                    ),
                    'template' => array(
                        'type' => 'string',
                        'description' => 'Использовать готовый шаблон (blog_post, portfolio, testimonials, team, events)'
                    )
                ),
                'required' => array('key', 'label')
            ),
            'callback' => array($this, 'handle_create_post_type'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения списка типов записей
        new RegisterMcpTool(array(
            'name' => 'list_custom_post_types',
            'description' => 'Получить список всех созданных через плагин пользовательских типов записей с их статусом и количеством записей.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_list_post_types'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для управления состоянием типа записи
        new RegisterMcpTool(array(
            'name' => 'toggle_custom_post_type',
            'description' => 'Активировать или деактивировать пользовательский тип записи.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_type' => array(
                        'type' => 'string',
                        'description' => 'Ключ типа записи'
                    ),
                    'active' => array(
                        'type' => 'boolean',
                        'description' => 'true для активации, false для деактивации'
                    )
                ),
                'required' => array('post_type', 'active')
            ),
            'callback' => array($this, 'handle_toggle_post_type'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения шаблонов
        new RegisterMcpTool(array(
            'name' => 'get_post_type_templates',
            'description' => 'Получить список доступных шаблонов для создания типов записей с готовыми настройками.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_get_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === ACF OPTIONS PAGES ===
        
        // Tool для создания страницы опций
        new RegisterMcpTool(array(
            'name' => 'create_options_page',
            'description' => 'Создать новую страницу опций ACF для настроек сайта.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'page_title' => array(
                        'type' => 'string',
                        'description' => 'Заголовок страницы (отображается в браузере)'
                    ),
                    'menu_title' => array(
                        'type' => 'string',
                        'description' => 'Название пункта меню (если не указано, используется page_title)'
                    ),
                    'menu_slug' => array(
                        'type' => 'string',
                        'description' => 'Уникальный slug для страницы'
                    ),
                    'capability' => array(
                        'type' => 'string',
                        'description' => 'Права доступа (по умолчанию: manage_options)'
                    ),
                    'parent_slug' => array(
                        'type' => 'string',
                        'description' => 'Slug родительской страницы для создания подстраницы'
                    ),
                    'icon_url' => array(
                        'type' => 'string',
                        'description' => 'Иконка для меню (dashicons класс или URL)'
                    ),
                    'position' => array(
                        'type' => 'string',
                        'description' => 'Позиция в меню'
                    ),
                    'sub_pages' => array(
                        'type' => 'array',
                        'description' => 'Массив подстраниц для создания'
                    ),
                    'description' => array(
                        'type' => 'string',
                        'description' => 'Описание страницы опций'
                    ),
                    'show_in_graphql' => array(
                        'type' => 'boolean',
                        'description' => 'Показывать страницу опций в GraphQL (true/false)'
                    ),
                    'graphql_single_name' => array(
                        'type' => 'string',
                        'description' => 'Название типа в GraphQL (автоматически генерируется из menu_slug в PascalCase если не указано)'
                    ),
                    'template' => array(
                        'type' => 'string',
                        'description' => 'Использовать готовый шаблон (general_settings, theme_settings, contact_info, seo_settings, header_footer)'
                    )
                ),
                'required' => array('page_title', 'menu_slug')
            ),
            'callback' => array($this, 'handle_create_options_page'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения списка страниц опций
        new RegisterMcpTool(array(
            'name' => 'list_options_pages',
            'description' => 'Получить список всех созданных страниц опций ACF.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_list_options_pages'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для управления состоянием страницы опций
        new RegisterMcpTool(array(
            'name' => 'toggle_options_page',
            'description' => 'Активировать или деактивировать страницу опций.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'menu_slug' => array(
                        'type' => 'string',
                        'description' => 'Slug страницы опций'
                    ),
                    'active' => array(
                        'type' => 'boolean',
                        'description' => 'true для активации, false для деактивации'
                    )
                ),
                'required' => array('menu_slug', 'active')
            ),
            'callback' => array($this, 'handle_toggle_options_page'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для удаления страницы опций
        new RegisterMcpTool(array(
            'name' => 'delete_options_page',
            'description' => 'Удалить страницу опций ACF. По умолчанию - деактивация, с permanent=true - полное удаление из БД.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'menu_slug' => array(
                        'type' => 'string',
                        'description' => 'Slug страницы опций для удаления'
                    ),
                    'permanent' => array(
                        'type' => 'boolean',
                        'description' => 'Полное удаление из БД (true) или только деактивация (false). По умолчанию: false'
                    )
                ),
                'required' => array('menu_slug')
            ),
            'callback' => array($this, 'handle_delete_options_page'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения шаблонов страниц опций
        new RegisterMcpTool(array(
            'name' => 'get_options_page_templates',
            'description' => 'Получить список доступных шаблонов страниц опций ACF.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_get_options_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === TAXONOMY TOOLS ===
        
        // Tool для создания таксономии
        new RegisterMcpTool(array(
            'name' => 'create_taxonomy',
            'description' => 'Создать новую таксономию ACF для классификации контента.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'taxonomy' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии (machine name), только латиница, цифры и подчеркивания'
                    ),
                    'singular_name' => array(
                        'type' => 'string',
                        'description' => 'Название в единственном числе'
                    ),
                    'plural_name' => array(
                        'type' => 'string',
                        'description' => 'Название во множественном числе'
                    ),
                    'post_types' => array(
                        'type' => 'array',
                        'description' => 'Массив типов записей для привязки таксономии'
                    ),
                    'hierarchical' => array(
                        'type' => 'boolean',
                        'description' => 'Иерархическая структура (как категории) или плоская (как теги)'
                    ),
                    'description' => array(
                        'type' => 'string',
                        'description' => 'Описание таксономии'
                    ),
                    'show_in_graphql' => array(
                        'type' => 'boolean',
                        'description' => 'Показывать таксономию в GraphQL'
                    ),
                    'graphql_single_name' => array(
                        'type' => 'string',
                        'description' => 'Название типа в GraphQL (единственное число, PascalCase)'
                    ),
                    'graphql_plural_name' => array(
                        'type' => 'string',
                        'description' => 'Название типа в GraphQL (множественное число, PascalCase)'
                    )
                ),
                'required' => array('taxonomy', 'singular_name')
            ),
            'callback' => array($this, 'handle_create_taxonomy'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения списка таксономий
        new RegisterMcpTool(array(
            'name' => 'list_taxonomies',
            'description' => 'Получить список всех созданных таксономий ACF.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_list_taxonomies'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для управления состоянием таксономии
        new RegisterMcpTool(array(
            'name' => 'toggle_taxonomy',
            'description' => 'Активировать или деактивировать таксономию.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'taxonomy_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии для переключения'
                    ),
                    'active' => array(
                        'type' => 'boolean',
                        'description' => 'true для активации, false для деактивации'
                    )
                ),
                'required' => array('taxonomy_key', 'active')
            ),
            'callback' => array($this, 'handle_toggle_taxonomy'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для удаления таксономии
        new RegisterMcpTool(array(
            'name' => 'delete_taxonomy',
            'description' => 'Удалить таксономию ACF. По умолчанию - деактивация, с permanent=true - полное удаление из БД.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'taxonomy_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии для удаления'
                    ),
                    'permanent' => array(
                        'type' => 'boolean',
                        'description' => 'Полное удаление из БД (true) или только деактивация (false). По умолчанию: false'
                    )
                ),
                'required' => array('taxonomy_key')
            ),
            'callback' => array($this, 'handle_delete_taxonomy'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения шаблонов таксономий
        new RegisterMcpTool(array(
            'name' => 'get_taxonomy_templates',
            'description' => 'Получить список доступных шаблонов таксономий ACF.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_get_taxonomy_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === FIELD GROUP TOOLS ===
        
        // Tool для создания группы полей
        new RegisterMcpTool(array(
            'name' => 'create_field_group',
            'description' => 'Создать новую группу произвольных полей ACF. Поддерживает все типы полей ACF Pro включая Repeater, Flexible Content, Group и условную логику показа. Для указания места отображения используйте target_type + target_value (рекомендуется) или location.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'title' => array(
                        'type' => 'string',
                        'description' => 'Заголовок группы полей'
                    ),
                    'fields' => array(
                        'type' => 'array',
                        'description' => 'Массив полей. Каждое поле: {name, label, type, required, instructions, ...специфичные параметры типа}'
                    ),
                    'target_type' => array(
                        'type' => 'string',
                        'description' => 'РЕКОМЕНДУЕТСЯ: Тип цели для отображения группы. Доступные значения: post_type (для типов записей), taxonomy (для категорий/тегов/таксономий), options_page (для страниц опций), page_template, user_form, nav_menu, block. Используйте вместе с target_value.'
                    ),
                    'target_value' => array(
                        'type' => 'string',
                        'description' => 'РЕКОМЕНДУЕТСЯ: Значение цели. Примеры: для target_type=post_type укажите "post", "page", "doctor", "service"; для target_type=taxonomy укажите "category", "service_category", "doctor_specialization"; для target_type=options_page укажите slug страницы.'
                    ),
                    'location' => array(
                        'type' => 'array',
                        'description' => 'Альтернатива target_type/target_value. Полный формат: [[{param, operator, value}]]. Упрощённые форматы: [{param, value}] или {param, value}. Параметры: post_type, taxonomy, options_page, page_template и др.'
                    ),
                    'menu_order' => array(
                        'type' => 'integer',
                        'description' => 'Порядок отображения группы'
                    ),
                    'position' => array(
                        'type' => 'string',
                        'description' => 'Позиция на экране редактирования: normal, side, acf_after_title'
                    ),
                    'style' => array(
                        'type' => 'string',
                        'description' => 'Стиль отображения: default, seamless'
                    ),
                    'label_placement' => array(
                        'type' => 'string',
                        'description' => 'Размещение меток: top, left'
                    ),
                    'instruction_placement' => array(
                        'type' => 'string',
                        'description' => 'Размещение инструкций: label, field'
                    ),
                    'description' => array(
                        'type' => 'string',
                        'description' => 'Описание группы полей'
                    ),
                    'show_in_graphql' => array(
                        'type' => 'boolean',
                        'description' => 'Показывать в GraphQL API'
                    ),
                    'graphql_field_name' => array(
                        'type' => 'string',
                        'description' => 'Имя поля в GraphQL (camelCase)'
                    ),
                    'template' => array(
                        'type' => 'string',
                        'description' => 'Использовать готовый шаблон: product_info, seo_fields, contact_info, person_profile, event_details'
                    )
                ),
                'required' => array('title', 'fields')
            ),
            'callback' => array($this, 'handle_create_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения списка групп полей
        new RegisterMcpTool(array(
            'name' => 'list_field_groups',
            'description' => 'Получить список всех групп произвольных полей ACF с информацией о количестве полей и статусе.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_list_field_groups'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения одной группы полей
        new RegisterMcpTool(array(
            'name' => 'get_field_group',
            'description' => 'Получить детальную информацию о группе полей включая все поля и их настройки.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'group_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ группы полей (group_xxx)'
                    )
                ),
                'required' => array('group_key')
            ),
            'callback' => array($this, 'handle_get_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для обновления группы полей
        new RegisterMcpTool(array(
            'name' => 'update_field_group',
            'description' => 'Обновить существующую группу полей ACF. Можно обновить заголовок, поля, правила показа и другие настройки. Для смены места отображения используйте target_type + target_value.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'group_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ группы полей для обновления'
                    ),
                    'title' => array(
                        'type' => 'string',
                        'description' => 'Новый заголовок группы'
                    ),
                    'fields' => array(
                        'type' => 'array',
                        'description' => 'Новый массив полей (заменит существующие)'
                    ),
                    'target_type' => array(
                        'type' => 'string',
                        'description' => 'Тип цели: post_type, taxonomy, options_page, page_template, user_form, nav_menu, block'
                    ),
                    'target_value' => array(
                        'type' => 'string',
                        'description' => 'Значение цели (имя типа записи, таксономии, slug страницы опций и т.д.)'
                    ),
                    'location' => array(
                        'type' => 'array',
                        'description' => 'Альтернатива target_type/target_value. Полный или упрощённый формат правил показа.'
                    ),
                    'description' => array(
                        'type' => 'string',
                        'description' => 'Новое описание'
                    ),
                    'show_in_graphql' => array(
                        'type' => 'boolean',
                        'description' => 'Показывать в GraphQL'
                    )
                ),
                'required' => array('group_key')
            ),
            'callback' => array($this, 'handle_update_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для управления состоянием группы полей
        new RegisterMcpTool(array(
            'name' => 'toggle_field_group',
            'description' => 'Активировать или деактивировать группу полей ACF.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'group_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ группы полей'
                    ),
                    'active' => array(
                        'type' => 'boolean',
                        'description' => 'true для активации, false для деактивации'
                    )
                ),
                'required' => array('group_key', 'active')
            ),
            'callback' => array($this, 'handle_toggle_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для удаления группы полей
        new RegisterMcpTool(array(
            'name' => 'delete_field_group',
            'description' => 'Удалить группу полей ACF. По умолчанию - деактивация, с permanent=true - полное удаление.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'group_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ группы полей для удаления'
                    ),
                    'permanent' => array(
                        'type' => 'boolean',
                        'description' => 'Полное удаление (true) или только деактивация (false)'
                    )
                ),
                'required' => array('group_key')
            ),
            'callback' => array($this, 'handle_delete_field_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для добавления поля в группу
        new RegisterMcpTool(array(
            'name' => 'add_field_to_group',
            'description' => 'Добавить новое поле в существующую группу полей ACF.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'group_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ группы полей'
                    ),
                    'name' => array(
                        'type' => 'string',
                        'description' => 'Имя поля (machine name)'
                    ),
                    'label' => array(
                        'type' => 'string',
                        'description' => 'Метка поля'
                    ),
                    'type' => array(
                        'type' => 'string',
                        'description' => 'Тип поля: text, textarea, number, image, file, wysiwyg, select, checkbox, radio, true_false, repeater, group, flexible_content и др.'
                    ),
                    'required' => array(
                        'type' => 'boolean',
                        'description' => 'Обязательное поле'
                    ),
                    'instructions' => array(
                        'type' => 'string',
                        'description' => 'Инструкции для поля'
                    ),
                    'conditional_logic' => array(
                        'type' => 'array',
                        'description' => 'Условия показа поля'
                    ),
                    'sub_fields' => array(
                        'type' => 'array',
                        'description' => 'Вложенные поля для типов group/repeater'
                    ),
                    'layouts' => array(
                        'type' => 'array',
                        'description' => 'Макеты для flexible_content'
                    )
                ),
                'required' => array('group_key', 'name', 'type')
            ),
            'callback' => array($this, 'handle_add_field_to_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для удаления поля из группы
        new RegisterMcpTool(array(
            'name' => 'remove_field_from_group',
            'description' => 'Удалить поле из группы полей ACF.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'group_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ группы полей'
                    ),
                    'field_key' => array(
                        'type' => 'string',
                        'description' => 'Ключ поля для удаления'
                    )
                ),
                'required' => array('group_key', 'field_key')
            ),
            'callback' => array($this, 'handle_remove_field_from_group'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения типов полей
        new RegisterMcpTool(array(
            'name' => 'get_field_types',
            'description' => 'Получить список всех поддерживаемых типов полей ACF с их параметрами.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_get_field_types'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения шаблонов групп полей
        new RegisterMcpTool(array(
            'name' => 'get_field_group_templates',
            'description' => 'Получить список готовых шаблонов групп полей ACF.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array()
            ),
            'callback' => array($this, 'handle_get_field_group_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === ACF VALUES TOOLS ===
        
        // Tool для обновления ACF полей
        new RegisterMcpTool(array(
            'name' => 'update_acf_fields',
            'description' => 'Обновить значения ACF полей для записи, терма, пользователя или страницы опций. Поддерживает все типы полей включая Repeater и Group.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array(
                        'type' => 'string',
                        'description' => 'ID объекта: число для записи, "term_XX" для терма, "user_XX" для пользователя, "option" для страницы опций'
                    ),
                    'fields' => array(
                        'type' => 'object',
                        'description' => 'Объект с полями для обновления: {field_name: value, ...}. Для Repeater передайте массив объектов.'
                    )
                ),
                'required' => array('post_id', 'fields')
            ),
            'callback' => array($this, 'handle_update_acf_fields'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения ACF полей
        new RegisterMcpTool(array(
            'name' => 'get_acf_fields',
            'description' => 'Получить значения ACF полей объекта. Возвращает все поля или указанные.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array(
                        'type' => 'string',
                        'description' => 'ID объекта: число для записи, "term_XX" для терма, "user_XX" для пользователя, "option" для опций'
                    ),
                    'fields' => array(
                        'type' => 'array',
                        'description' => 'Массив имен полей для получения. Если не указан - возвращаются все поля.'
                    )
                ),
                'required' => array('post_id')
            ),
            'callback' => array($this, 'handle_get_acf_fields'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для удаления значения ACF поля
        new RegisterMcpTool(array(
            'name' => 'delete_acf_field',
            'description' => 'Очистить значение ACF поля объекта.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array(
                        'type' => 'string',
                        'description' => 'ID объекта'
                    ),
                    'field_name' => array(
                        'type' => 'string',
                        'description' => 'Имя поля для очистки'
                    )
                ),
                'required' => array('post_id', 'field_name')
            ),
            'callback' => array($this, 'handle_delete_acf_field'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === TERMS MANAGEMENT TOOLS ===
        
        // Tool для создания терма
        new RegisterMcpTool(array(
            'name' => 'create_term',
            'description' => 'Создать новый терм в таксономии. Поддерживает ACF поля для терма.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'taxonomy' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии (например: doctor_specialization, category)'
                    ),
                    'name' => array(
                        'type' => 'string',
                        'description' => 'Название терма'
                    ),
                    'slug' => array(
                        'type' => 'string',
                        'description' => 'URL-slug терма (если не указан, генерируется из названия)'
                    ),
                    'description' => array(
                        'type' => 'string',
                        'description' => 'Описание терма'
                    ),
                    'parent' => array(
                        'type' => 'integer',
                        'description' => 'ID родительского терма (для иерархических таксономий)'
                    ),
                    'acf_fields' => array(
                        'type' => 'object',
                        'description' => 'ACF поля терма: {field_name: value}'
                    )
                ),
                'required' => array('taxonomy', 'name')
            ),
            'callback' => array($this, 'handle_create_term'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для обновления терма
        new RegisterMcpTool(array(
            'name' => 'update_term',
            'description' => 'Обновить существующий терм и его ACF поля.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'term_id' => array(
                        'type' => 'integer',
                        'description' => 'ID терма для обновления'
                    ),
                    'taxonomy' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии (опционально, определяется автоматически)'
                    ),
                    'name' => array(
                        'type' => 'string',
                        'description' => 'Новое название'
                    ),
                    'slug' => array(
                        'type' => 'string',
                        'description' => 'Новый slug'
                    ),
                    'description' => array(
                        'type' => 'string',
                        'description' => 'Новое описание'
                    ),
                    'parent' => array(
                        'type' => 'integer',
                        'description' => 'ID нового родителя'
                    ),
                    'acf_fields' => array(
                        'type' => 'object',
                        'description' => 'ACF поля для обновления'
                    )
                ),
                'required' => array('term_id')
            ),
            'callback' => array($this, 'handle_update_term'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для удаления терма
        new RegisterMcpTool(array(
            'name' => 'delete_term',
            'description' => 'Удалить терм из таксономии.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'term_id' => array(
                        'type' => 'integer',
                        'description' => 'ID терма для удаления'
                    ),
                    'taxonomy' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии (опционально)'
                    )
                ),
                'required' => array('term_id')
            ),
            'callback' => array($this, 'handle_delete_term'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения списка термов
        new RegisterMcpTool(array(
            'name' => 'list_terms',
            'description' => 'Получить список термов таксономии с их ACF полями.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'taxonomy' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии'
                    ),
                    'hide_empty' => array(
                        'type' => 'boolean',
                        'description' => 'Скрыть пустые термы (без записей). По умолчанию: false'
                    ),
                    'parent' => array(
                        'type' => 'integer',
                        'description' => 'ID родительского терма (для фильтрации)'
                    ),
                    'include_acf' => array(
                        'type' => 'boolean',
                        'description' => 'Включить ACF поля термов. По умолчанию: true'
                    ),
                    'per_page' => array(
                        'type' => 'integer',
                        'description' => 'Количество термов на странице. По умолчанию: 100'
                    ),
                    'page' => array(
                        'type' => 'integer',
                        'description' => 'Номер страницы. По умолчанию: 1'
                    )
                ),
                'required' => array('taxonomy')
            ),
            'callback' => array($this, 'handle_list_terms'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для привязки термов к записи
        new RegisterMcpTool(array(
            'name' => 'assign_terms_to_post',
            'description' => 'Привязать термы к записи. Можно заменить существующие или добавить новые.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array(
                        'type' => 'integer',
                        'description' => 'ID записи'
                    ),
                    'taxonomy' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии'
                    ),
                    'terms' => array(
                        'type' => 'array',
                        'description' => 'Массив ID термов или их slug для привязки'
                    ),
                    'append' => array(
                        'type' => 'boolean',
                        'description' => 'Добавить к существующим (true) или заменить (false). По умолчанию: false'
                    )
                ),
                'required' => array('post_id', 'taxonomy', 'terms')
            ),
            'callback' => array($this, 'handle_assign_terms'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения термов записи
        new RegisterMcpTool(array(
            'name' => 'get_post_terms',
            'description' => 'Получить термы привязанные к записи.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array(
                        'type' => 'integer',
                        'description' => 'ID записи'
                    ),
                    'taxonomy' => array(
                        'type' => 'string',
                        'description' => 'Ключ таксономии (если не указан - возвращаются все)'
                    ),
                    'include_acf' => array(
                        'type' => 'boolean',
                        'description' => 'Включить ACF поля термов'
                    )
                ),
                'required' => array('post_id')
            ),
            'callback' => array($this, 'handle_get_post_terms'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === OPTIONS VALUES TOOLS ===
        
        // Tool для обновления значений страницы опций
        new RegisterMcpTool(array(
            'name' => 'update_options_fields',
            'description' => 'Обновить значения ACF полей на странице опций.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'fields' => array(
                        'type' => 'object',
                        'description' => 'Объект с полями для обновления: {field_name: value}'
                    )
                ),
                'required' => array('fields')
            ),
            'callback' => array($this, 'handle_update_options_fields'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения значений страницы опций
        new RegisterMcpTool(array(
            'name' => 'get_options_fields',
            'description' => 'Получить значения ACF полей со страницы опций.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'fields' => array(
                        'type' => 'array',
                        'description' => 'Массив имен полей. Если не указан - возвращаются все.'
                    )
                ),
                'required' => array()
            ),
            'callback' => array($this, 'handle_get_options_fields'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // === MEDIA TOOLS ===
        
        // Tool для загрузки изображения по URL
        new RegisterMcpTool(array(
            'name' => 'upload_image_from_url',
            'description' => 'Загрузить изображение по URL в медиатеку WordPress. Может автоматически установить в ACF поле записи.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'url' => array(
                        'type' => 'string',
                        'description' => 'URL изображения для загрузки'
                    ),
                    'title' => array(
                        'type' => 'string',
                        'description' => 'Заголовок изображения в медиатеке'
                    ),
                    'alt' => array(
                        'type' => 'string',
                        'description' => 'Alt текст изображения (для SEO и доступности)'
                    ),
                    'caption' => array(
                        'type' => 'string',
                        'description' => 'Подпись к изображению'
                    ),
                    'post_id' => array(
                        'type' => 'integer',
                        'description' => 'ID записи для привязки изображения (опционально)'
                    ),
                    'acf_field' => array(
                        'type' => 'string',
                        'description' => 'Имя ACF поля для автоматической установки изображения (требует post_id)'
                    )
                ),
                'required' => array('url')
            ),
            'callback' => array($this, 'handle_upload_image_from_url'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для загрузки нескольких изображений
        new RegisterMcpTool(array(
            'name' => 'upload_images_from_urls',
            'description' => 'Загрузить несколько изображений по URL. Можно установить в ACF Gallery поле.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'urls' => array(
                        'type' => 'array',
                        'description' => 'Массив URL изображений или массив объектов {url, title, alt}'
                    ),
                    'post_id' => array(
                        'type' => 'integer',
                        'description' => 'ID записи для привязки'
                    ),
                    'acf_field' => array(
                        'type' => 'string',
                        'description' => 'Имя ACF Gallery поля для установки всех изображений'
                    ),
                    'default_alt' => array(
                        'type' => 'string',
                        'description' => 'Alt текст по умолчанию для всех изображений'
                    )
                ),
                'required' => array('urls')
            ),
            'callback' => array($this, 'handle_upload_images_from_urls'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для установки featured image
        new RegisterMcpTool(array(
            'name' => 'set_featured_image',
            'description' => 'Установить изображение записи (миниатюру). Можно указать ID существующего изображения или загрузить по URL.',
            'type' => 'action',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array(
                        'type' => 'integer',
                        'description' => 'ID записи'
                    ),
                    'attachment_id' => array(
                        'type' => 'integer',
                        'description' => 'ID существующего изображения из медиатеки'
                    ),
                    'url' => array(
                        'type' => 'string',
                        'description' => 'URL изображения для загрузки и установки'
                    ),
                    'title' => array(
                        'type' => 'string',
                        'description' => 'Заголовок изображения (при загрузке по URL)'
                    ),
                    'alt' => array(
                        'type' => 'string',
                        'description' => 'Alt текст (при загрузке по URL)'
                    )
                ),
                'required' => array('post_id')
            ),
            'callback' => array($this, 'handle_set_featured_image'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Tool для получения списка медиа
        new RegisterMcpTool(array(
            'name' => 'list_media',
            'description' => 'Получить список медиафайлов из библиотеки WordPress.',
            'type' => 'read',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'per_page' => array(
                        'type' => 'integer',
                        'description' => 'Количество на страницу (по умолчанию 20)'
                    ),
                    'page' => array(
                        'type' => 'integer',
                        'description' => 'Номер страницы'
                    ),
                    'mime_type' => array(
                        'type' => 'string',
                        'description' => 'Фильтр по MIME типу: image, video, audio, application'
                    ),
                    'search' => array(
                        'type' => 'string',
                        'description' => 'Поиск по названию'
                    )
                ),
                'required' => array()
            ),
            'callback' => array($this, 'handle_list_media'),
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
     * Обработчик создания типа записи
     */
    public function handle_create_post_type($params) {
        $result = ACF_MCP_CPT_Creator::get_instance()->create_post_type($params);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик получения списка типов записей
     */
    public function handle_list_post_types($params) {
        $stored_post_types = ACF_MCP_CPT_Creator::get_instance()->get_post_types();
        
        // Добавляем дополнительную информацию
        foreach ($stored_post_types as $key => &$post_type) {
            $post_type['is_registered'] = post_type_exists($key);
            if (post_type_exists($key)) {
                $post_counts = wp_count_posts($key);
                $post_type['posts_count'] = $post_counts->publish ?? 0;
                $post_type['drafts_count'] = $post_counts->draft ?? 0;
            } else {
                $post_type['posts_count'] = 0;
                $post_type['drafts_count'] = 0;
            }
        }
        
        return array(
            'success' => true,
            'post_types' => $stored_post_types
        );
    }
    
    /**
     * Обработчик переключения состояния типа записи
     */
    public function handle_toggle_post_type($params) {
        if (empty($params['post_type'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ типа записи'
            );
        }
        
        $result = ACF_MCP_CPT_Creator::get_instance()->toggle_post_type(
            $params['post_type'],
            $params['active'] ?? true
        );
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик получения шаблонов
     */
    public function handle_get_templates($params) {
        // Получаем шаблоны из REST API класса
        $rest_api = ACF_MCP_REST_API::get_instance();
        $fake_request = new WP_REST_Request('GET', '/acf-cpt-manager/v1/post-types/templates');
        
        $response = $rest_api->get_templates($fake_request);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        return $response->get_data();
    }
    
    /**
     * Обработчик создания страницы опций
     */
    public function handle_create_options_page($params) {
        // Если указан шаблон, применяем его
        if (!empty($params['template']) && class_exists('ACF_MCP_Options_Page_Creator')) {
            $templates = ACF_MCP_Options_Page_Creator::get_instance()->get_templates();
            if (isset($templates[$params['template']])) {
                $template_args = $templates[$params['template']]['args'];
                $params = array_merge($template_args, $params);
                unset($params['template']);
            }
        }
        
        $result = ACF_MCP_Options_Page_Creator::get_instance()->create_options_page($params);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик получения списка страниц опций
     */
    public function handle_list_options_pages($params) {
        $pages = ACF_MCP_Options_Page_Creator::get_instance()->get_options_pages();
        
        return array(
            'success' => true,
            'options_pages' => $pages
        );
    }
    
    /**
     * Обработчик переключения состояния страницы опций
     */
    public function handle_toggle_options_page($params) {
        if (empty($params['menu_slug'])) {
            return array(
                'success' => false,
                'error' => 'Не указан slug страницы опций'
            );
        }
        
        $result = ACF_MCP_Options_Page_Creator::get_instance()->toggle_options_page(
            $params['menu_slug'],
            $params['active'] ?? true
        );
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик удаления страницы опций
     */
    public function handle_delete_options_page($params) {
        if (empty($params['menu_slug'])) {
            return array(
                'success' => false,
                'error' => 'Не указан slug страницы опций'
            );
        }
        
        $permanent = $params['permanent'] ?? false;
        
        $result = ACF_MCP_Options_Page_Creator::get_instance()->delete_options_page(
            $params['menu_slug'],
            $permanent
        );
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик получения шаблонов страниц опций
     */
    public function handle_get_options_templates($params) {
        $templates = ACF_MCP_Options_Page_Creator::get_instance()->get_templates();
        
        return array(
            'success' => true,
            'templates' => $templates
        );
    }
    
    /**
     * Обработчик создания таксономии
     */
    public function handle_create_taxonomy($params) {
        $result = ACF_MCP_Taxonomy_Creator::get_instance()->create_taxonomy($params);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик получения списка таксономий
     */
    public function handle_list_taxonomies($params) {
        $taxonomies = ACF_MCP_Taxonomy_Creator::get_instance()->get_taxonomies();
        
        return array(
            'success' => true,
            'taxonomies' => $taxonomies
        );
    }
    
    /**
     * Обработчик переключения состояния таксономии
     */
    public function handle_toggle_taxonomy($params) {
        if (empty($params['taxonomy_key'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ таксономии'
            );
        }
        
        $result = ACF_MCP_Taxonomy_Creator::get_instance()->toggle_taxonomy(
            $params['taxonomy_key'],
            $params['active'] ?? true
        );
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик удаления таксономии
     */
    public function handle_delete_taxonomy($params) {
        if (empty($params['taxonomy_key'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ таксономии'
            );
        }
        
        $permanent = $params['permanent'] ?? false;
        
        $result = ACF_MCP_Taxonomy_Creator::get_instance()->delete_taxonomy(
            $params['taxonomy_key'],
            $permanent
        );
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик получения шаблонов таксономий
     */
    public function handle_get_taxonomy_templates($params) {
        $templates = ACF_MCP_Taxonomy_Creator::get_instance()->get_templates();
        
        return array(
            'success' => true,
            'templates' => $templates
        );
    }
    
    // === FIELD GROUP HANDLERS ===
    
    /**
     * Обработчик создания группы полей
     */
    public function handle_create_field_group($params) {
        // Если указан шаблон, применяем его
        if (!empty($params['template']) && class_exists('ACF_MCP_Field_Group_Creator')) {
            $templates = ACF_MCP_Field_Group_Creator::get_instance()->get_templates();
            if (isset($templates[$params['template']])) {
                $template = $templates[$params['template']];
                // Объединяем поля шаблона с переданными
                if (!empty($template['fields'])) {
                    $params['fields'] = array_merge($template['fields'], $params['fields'] ?? array());
                }
                unset($params['template']);
            }
        }
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->create_field_group($params);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик получения списка групп полей
     */
    public function handle_list_field_groups($params) {
        $field_groups = ACF_MCP_Field_Group_Creator::get_instance()->get_field_groups();
        
        return array(
            'success' => true,
            'field_groups' => $field_groups
        );
    }
    
    /**
     * Обработчик получения одной группы полей
     */
    public function handle_get_field_group($params) {
        if (empty($params['group_key'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ группы полей'
            );
        }
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->get_field_group($params['group_key']);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'field_group' => $result
        );
    }
    
    /**
     * Обработчик обновления группы полей
     */
    public function handle_update_field_group($params) {
        if (empty($params['group_key'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ группы полей'
            );
        }
        
        $group_key = $params['group_key'];
        unset($params['group_key']);
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->update_field_group($group_key, $params);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик переключения состояния группы полей
     */
    public function handle_toggle_field_group($params) {
        if (empty($params['group_key'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ группы полей'
            );
        }
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->toggle_field_group(
            $params['group_key'],
            $params['active'] ?? true
        );
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик удаления группы полей
     */
    public function handle_delete_field_group($params) {
        if (empty($params['group_key'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ группы полей'
            );
        }
        
        $permanent = $params['permanent'] ?? false;
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->delete_field_group(
            $params['group_key'],
            $permanent
        );
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик добавления поля в группу
     */
    public function handle_add_field_to_group($params) {
        if (empty($params['group_key'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ группы полей'
            );
        }
        
        $group_key = $params['group_key'];
        unset($params['group_key']);
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->add_field_to_group($group_key, $params);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик удаления поля из группы
     */
    public function handle_remove_field_from_group($params) {
        if (empty($params['group_key']) || empty($params['field_key'])) {
            return array(
                'success' => false,
                'error' => 'Не указан ключ группы или поля'
            );
        }
        
        $result = ACF_MCP_Field_Group_Creator::get_instance()->remove_field_from_group(
            $params['group_key'],
            $params['field_key']
        );
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return $result;
    }
    
    /**
     * Обработчик получения типов полей
     */
    public function handle_get_field_types($params) {
        $field_types = ACF_MCP_Field_Group_Creator::get_instance()->get_field_types();
        
        return array(
            'success' => true,
            'field_types' => $field_types
        );
    }
    
    /**
     * Обработчик получения шаблонов групп полей
     */
    public function handle_get_field_group_templates($params) {
        $templates = ACF_MCP_Field_Group_Creator::get_instance()->get_templates();
        
        return array(
            'success' => true,
            'templates' => $templates
        );
    }
    
    // === ACF VALUES HANDLERS ===
    
    /**
     * Обработчик обновления ACF полей
     */
    public function handle_update_acf_fields($params) {
        if (empty($params['post_id'])) {
            return array(
                'success' => false,
                'error' => 'Не указан post_id'
            );
        }
        
        if (empty($params['fields'])) {
            return array(
                'success' => false,
                'error' => 'Не указаны поля для обновления'
            );
        }
        
        return ACF_MCP_Values_Manager::get_instance()->update_fields($params);
    }
    
    /**
     * Обработчик получения ACF полей
     */
    public function handle_get_acf_fields($params) {
        if (empty($params['post_id'])) {
            return array(
                'success' => false,
                'error' => 'Не указан post_id'
            );
        }
        
        return ACF_MCP_Values_Manager::get_instance()->get_fields($params);
    }
    
    /**
     * Обработчик удаления значения ACF поля
     */
    public function handle_delete_acf_field($params) {
        if (empty($params['post_id']) || empty($params['field_name'])) {
            return array(
                'success' => false,
                'error' => 'Не указаны post_id или field_name'
            );
        }
        
        return ACF_MCP_Values_Manager::get_instance()->delete_field($params);
    }
    
    // === TERMS HANDLERS ===
    
    /**
     * Обработчик создания терма
     */
    public function handle_create_term($params) {
        if (empty($params['taxonomy'])) {
            return array(
                'success' => false,
                'error' => 'Не указана таксономия'
            );
        }
        
        if (empty($params['name'])) {
            return array(
                'success' => false,
                'error' => 'Не указано название терма'
            );
        }
        
        return ACF_MCP_Term_Manager::get_instance()->create_term($params);
    }
    
    /**
     * Обработчик обновления терма
     */
    public function handle_update_term($params) {
        if (empty($params['term_id'])) {
            return array(
                'success' => false,
                'error' => 'Не указан term_id'
            );
        }
        
        return ACF_MCP_Term_Manager::get_instance()->update_term($params);
    }
    
    /**
     * Обработчик удаления терма
     */
    public function handle_delete_term($params) {
        if (empty($params['term_id'])) {
            return array(
                'success' => false,
                'error' => 'Не указан term_id'
            );
        }
        
        return ACF_MCP_Term_Manager::get_instance()->delete_term($params);
    }
    
    /**
     * Обработчик получения списка термов
     */
    public function handle_list_terms($params) {
        if (empty($params['taxonomy'])) {
            return array(
                'success' => false,
                'error' => 'Не указана таксономия'
            );
        }
        
        return ACF_MCP_Term_Manager::get_instance()->list_terms($params);
    }
    
    /**
     * Обработчик привязки термов к записи
     */
    public function handle_assign_terms($params) {
        if (empty($params['post_id'])) {
            return array(
                'success' => false,
                'error' => 'Не указан post_id'
            );
        }
        
        if (empty($params['taxonomy'])) {
            return array(
                'success' => false,
                'error' => 'Не указана таксономия'
            );
        }
        
        if (empty($params['terms'])) {
            return array(
                'success' => false,
                'error' => 'Не указаны термы'
            );
        }
        
        return ACF_MCP_Term_Manager::get_instance()->assign_terms($params);
    }
    
    /**
     * Обработчик получения термов записи
     */
    public function handle_get_post_terms($params) {
        if (empty($params['post_id'])) {
            return array(
                'success' => false,
                'error' => 'Не указан post_id'
            );
        }
        
        return ACF_MCP_Term_Manager::get_instance()->get_post_terms($params);
    }
    
    // === OPTIONS VALUES HANDLERS ===
    
    /**
     * Обработчик обновления значений страницы опций
     */
    public function handle_update_options_fields($params) {
        if (empty($params['fields'])) {
            return array(
                'success' => false,
                'error' => 'Не указаны поля для обновления'
            );
        }
        
        return ACF_MCP_Values_Manager::get_instance()->update_fields(array(
            'post_id' => 'option',
            'fields' => $params['fields']
        ));
    }
    
    /**
     * Обработчик получения значений страницы опций
     */
    public function handle_get_options_fields($params) {
        return ACF_MCP_Values_Manager::get_instance()->get_fields(array(
            'post_id' => 'option',
            'fields' => $params['fields'] ?? null
        ));
    }
    
    // === MEDIA HANDLERS ===
    
    /**
     * Обработчик загрузки изображения по URL
     */
    public function handle_upload_image_from_url($params) {
        if (empty($params['url'])) {
            return array(
                'success' => false,
                'error' => 'Не указан URL изображения'
            );
        }
        
        return ACF_MCP_Media_Manager::get_instance()->upload_from_url($params);
    }
    
    /**
     * Обработчик загрузки нескольких изображений
     */
    public function handle_upload_images_from_urls($params) {
        if (empty($params['urls'])) {
            return array(
                'success' => false,
                'error' => 'Не указан массив URL'
            );
        }
        
        return ACF_MCP_Media_Manager::get_instance()->upload_multiple_from_urls($params);
    }
    
    /**
     * Обработчик установки featured image
     */
    public function handle_set_featured_image($params) {
        if (empty($params['post_id'])) {
            return array(
                'success' => false,
                'error' => 'Не указан post_id'
            );
        }
        
        if (empty($params['attachment_id']) && empty($params['url'])) {
            return array(
                'success' => false,
                'error' => 'Укажите attachment_id или url изображения'
            );
        }
        
        return ACF_MCP_Media_Manager::get_instance()->set_featured_image($params);
    }
    
    /**
     * Обработчик получения списка медиа
     */
    public function handle_list_media($params) {
        return ACF_MCP_Media_Manager::get_instance()->list_media($params);
    }
}
