# Архитектура плагина ACF Backend Manager

## Обзор

Плагин построен по модульному принципу для легкого расширения функционала.

## Структура файлов

```
acf-cpt-manager/
├── acf-cpt-manager.php                 # Главный файл плагина
├── includes/                            # Модули плагина
│   ├── class-cpt-creator.php           # Custom Post Types
│   ├── class-options-page-creator.php  # Options Pages
│   ├── class-rest-api.php              # REST API endpoints
│   └── class-mcp-integration.php       # MCP Tools регистрация
├── README.md                            # Основная документация
├── ПРИМЕРЫ.md                           # Примеры использования
└── ARCHITECTURE.md                      # Этот файл
```

## Компоненты плагина

### 1. ACF_CPT_Manager (главный класс)

**Файл:** `acf-cpt-manager.php`

**Отвественность:**
- Инициализация плагина
- Загрузка зависимостей
- Регистрация хуков WordPress
- Проверка наличия ACF Pro

**Хуки:**
- `init` - инициализация компонентов
- `rest_api_init` - регистрация REST API
- `wordpress_mcp_init` - регистрация MCP tools (если установлен WordPress MCP)

### 2. ACF_CPT_Creator

**Файл:** `includes/class-cpt-creator.php`

**Отвественность:**
- Создание Custom Post Types
- Регистрация типов записей в WordPress
- Управление состоянием (активация/деактивация)
- Хранение конфигурации в `wp_options`

**Основные методы:**
- `create_post_type($args)` - создание нового типа
- `register_stored_post_types()` - регистрация сохраненных типов
- `get_post_types()` - получение списка
- `toggle_post_type($key, $active)` - управление состоянием
- `delete_post_type($key)` - удаление типа

**Хранилище данных:**
Option: `acf_cpt_manager_post_types`

```php
array(
    'post_type_key' => array(
        'acf_id' => 123,              // ID в ACF системе
        'args' => array(...),          // Аргументы регистрации
        'active' => true,              // Состояние
        'created_at' => '2025-09-30',  // Дата создания
        'created_by' => 1,             // ID пользователя
        'method' => 'acf_pro'          // Метод создания
    )
)
```

### 3. ACF_Options_Page_Creator (NEW v2.0)

**Файл:** `includes/class-options-page-creator.php`

**Отвественность:**
- Создание Options Pages ACF
- Регистрация страниц опций
- Управление подстраницами
- Шаблоны страниц

**Основные методы:**
- `create_options_page($args)` - создание страницы
- `register_stored_options_pages()` - регистрация сохраненных страниц
- `get_options_pages()` - получение списка
- `update_options_page($slug, $args)` - обновление
- `toggle_options_page($slug, $active)` - управление состоянием
- `delete_options_page($slug)` - удаление
- `get_templates()` - получение готовых шаблонов

**Хранилище данных:**
Option: `acf_cpt_manager_options_pages`

```php
array(
    'menu_slug' => array(
        'args' => array(
            'page_title' => 'Название',
            'menu_slug' => 'slug',
            'capability' => 'manage_options',
            'icon_url' => 'dashicons-admin-settings',
            ...
        ),
        'sub_pages' => array(...),     // Подстраницы
        'active' => true,              // Состояние
        'created_at' => '2025-09-30',  // Дата создания
        'created_by' => 1              // ID пользователя
    )
)
```

**Хуки:**
- `acf/init` - регистрация страниц опций (необходимо для ACF)

### 4. ACF_CPT_REST_API

**Файл:** `includes/class-rest-api.php`

**Отвественность:**
- Регистрация REST API endpoints
- Обработка HTTP запросов
- Валидация данных
- Проверка прав доступа

**Namespace:** `acf-cpt-manager/v1`

**Endpoints Custom Post Types:**
```
GET    /post-types              - Список типов
POST   /post-types              - Создать тип
PUT    /post-types/{key}        - Обновить тип
POST   /post-types/{key}/toggle - Переключить состояние
DELETE /post-types/{key}        - Удалить тип
GET    /post-types/templates    - Получить шаблоны
```

**Endpoints Options Pages:**
```
GET    /options-pages                - Список страниц
POST   /options-pages                - Создать страницу
PUT    /options-pages/{slug}         - Обновить страницу
POST   /options-pages/{slug}/toggle  - Переключить состояние
DELETE /options-pages/{slug}         - Удалить страницу
GET    /options-pages/templates      - Получить шаблоны
```

**Проверка прав:**
Все endpoints требуют `manage_options` capability.

### 5. ACF_CPT_MCP_Integration

**Файл:** `includes/class-mcp-integration.php`

**Отвественность:**
- Регистрация MCP Tools
- Связь между MCP протоколом и функционалом плагина
- Обработка запросов от AI

**MCP Tools Custom Post Types:**
- `create_custom_post_type` - создание типа записи
- `list_custom_post_types` - список типов
- `toggle_custom_post_type` - управление состоянием
- `get_post_type_templates` - получение шаблонов

**MCP Tools Options Pages:**
- `create_options_page` - создание страницы опций
- `list_options_pages` - список страниц
- `toggle_options_page` - управление состоянием
- `get_options_page_templates` - получение шаблонов

**Зависимость:**
Требует установленный плагин WordPress MCP (Automattic\WordpressMcp)

## Поток данных

### Создание Options Page через MCP

```
AI запрос
    ↓
WordPress MCP Server
    ↓
ACF_CPT_MCP_Integration::handle_create_options_page()
    ↓
ACF_Options_Page_Creator::create_options_page()
    ↓
1. Валидация данных
2. Применение шаблона (если указан)
3. Сохранение в wp_options
4. Регистрация через acf_add_options_page()
    ↓
Ответ: success/error
```

### Создание через REST API

```
HTTP POST /wp-json/acf-cpt-manager/v1/options-pages
    ↓
ACF_CPT_REST_API::create_options_page()
    ↓
Проверка прав (manage_options)
    ↓
ACF_Options_Page_Creator::create_options_page()
    ↓
REST Response
```

## Расширение функционала

### Добавление нового модуля (например, Taxonomies)

1. Создайте файл `includes/class-taxonomy-creator.php`

```php
class ACF_Taxonomy_Creator {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_stored_taxonomies'));
    }
    
    public function create_taxonomy($args) {
        // Логика создания таксономии
    }
    
    public function get_taxonomies() {
        return get_option('acf_cpt_manager_taxonomies', array());
    }
}
```

2. Добавьте загрузку в `acf-cpt-manager.php`:

```php
private function load_dependencies() {
    $includes = array(
        'class-cpt-creator.php',
        'class-options-page-creator.php',
        'class-taxonomy-creator.php',  // NEW
        'class-rest-api.php',
        'class-mcp-integration.php'
    );
    // ...
}
```

3. Инициализируйте в методе `init()`:

```php
if (class_exists('ACF_Taxonomy_Creator')) {
    ACF_Taxonomy_Creator::get_instance();
}
```

4. Добавьте MCP Tools в `class-mcp-integration.php`:

```php
new RegisterMcpTool(array(
    'name' => 'create_taxonomy',
    'description' => 'Создать новую таксономию',
    'type' => 'action',
    'inputSchema' => array(/* ... */),
    'callback' => array($this, 'handle_create_taxonomy'),
    'permission_callback' => array($this, 'check_permissions')
));
```

5. Добавьте REST endpoints в `class-rest-api.php`:

```php
register_rest_route($this->namespace, '/taxonomies', array(
    'methods' => 'POST',
    'callback' => array($this, 'create_taxonomy'),
    'permission_callback' => array($this, 'check_permissions')
));
```

## Лучшие практики

### Singleton Pattern
Все основные классы используют Singleton для предотвращения множественных экземпляров.

### Хуки WordPress
Используем правильные хуки:
- `init` - для регистрации post types
- `acf/init` - для регистрации ACF опций
- `rest_api_init` - для REST API
- `wordpress_mcp_init` - для MCP tools

### Хранение данных
Все настройки хранятся в `wp_options` для простоты экспорта/импорта.

### Валидация
Всегда проверяем:
- Наличие обязательных параметров
- Уникальность ключей/slugs
- Права доступа пользователя
- Наличие зависимостей (ACF Pro)

### Обработка ошибок
Используем WP_Error для возврата ошибок с понятными сообщениями.

## Зависимости

### Обязательные
- WordPress 6.0+
- PHP 8.0+
- ACF Pro 6.0+

### Опциональные
- WordPress MCP - для AI интеграции
- WPGraphQL - для GraphQL поддержки

## Безопасность

### Проверка прав
Все операции требуют `manage_options` capability.

### Валидация входных данных
- Sanitize всех строковых данных
- Escape при выводе
- Проверка nonce для форм

### REST API
- Authentication через WordPress cookies или Application Passwords
- CORS headers настраиваются через WordPress

## Производительность

### Кэширование
Данные загружаются из `wp_options` один раз при инициализации.

### Lazy Loading
Компоненты инициализируются только когда ACF доступен.

### Оптимизация запросов
Минимизируем обращения к БД через группировку операций.

## Будущие улучшения

### Планируется в v3.0:
- [ ] Создание Taxonomies (таксономии)
- [ ] Создание ACF Field Groups через MCP
- [ ] Импорт/Экспорт конфигурации
- [ ] UI в админке WordPress
- [ ] Версионирование структур
- [ ] Синхронизация между окружениями

### Планируется в v4.0:
- [ ] Создание Custom Fields
- [ ] Шаблоны для отраслей (медицина, e-commerce, недвижимость)
- [ ] Визуальный конструктор структур
- [ ] CLI команды
- [ ] Webhooks для изменений структуры
