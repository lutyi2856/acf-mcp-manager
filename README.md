# ACF MCP Manager

Плагин для создания и управления структурой WordPress через MCP API и REST API.

## Возможности

### Custom Post Types
- ✅ Создание типов записей через MCP API (AI интеграция)
- ✅ REST API для управления типами записей
- ✅ Готовые шаблоны типов записей
- ✅ Активация/деактивация типов записей
- ✅ Интеграция с ACF Pro
- ✅ Поддержка GraphQL через WPGraphQL

### Options Pages
- ✅ Создание страниц опций ACF через MCP API
- ✅ REST API для управления страницами опций
- ✅ Готовые шаблоны страниц опций
- ✅ Поддержка подстраниц
- ✅ Активация/деактивация страниц опций

### Taxonomies
- ✅ Создание таксономий через MCP API
- ✅ REST API для управления таксономиями
- ✅ Поддержка GraphQL

### Field Groups (v3.0+)
- ✅ Создание групп полей через MCP API
- ✅ Все типы полей ACF Pro
- ✅ Условная логика показа
- ✅ Вложенные поля (Repeater, Group, Flexible Content)
- ✅ Поддержка GraphQL
- ✅ **target_type/target_value** - упрощённое указание места отображения (v4.0.1)

### ACF Data Management (v4.0+)
- ✅ Обновление ACF полей записей/термов/опций через MCP
- ✅ Управление термами таксономий
- ✅ Загрузка медиа по URL
- ✅ Установка featured image

## Требования

- WordPress 6.0+
- PHP 8.0+
- Advanced Custom Fields Pro (обязательно)
- WordPress MCP (для AI интеграции)

## REST API

Namespace: `acf-mcp-manager/v1`

### Custom Post Types
- `GET /post-types` - Список типов записей
- `POST /post-types` - Создать тип записи
- `PUT /post-types/{key}` - Обновить тип записи
- `POST /post-types/{key}/toggle` - Активировать/деактивировать
- `DELETE /post-types/{key}` - Удалить тип записи

### Options Pages
- `GET /options-pages` - Список страниц опций
- `POST /options-pages` - Создать страницу опций
- `PUT /options-pages/{menu_slug}` - Обновить страницу
- `DELETE /options-pages/{menu_slug}` - Удалить страницу

### Taxonomies
- `GET /taxonomies` - Список таксономий
- `POST /taxonomies` - Создать таксономию
- `PUT /taxonomies/{key}` - Обновить таксономию
- `DELETE /taxonomies/{key}` - Удалить таксономию

### Field Groups
- `GET /field-groups` - Список групп полей
- `POST /field-groups` - Создать группу полей
- `GET /field-groups/{key}` - Получить группу полей
- `PUT /field-groups/{key}` - Обновить группу полей
- `DELETE /field-groups/{key}` - Удалить группу полей

### ACF Values
- `POST /acf-values` - Обновить ACF поля
- `GET /acf-values/{post_id}` - Получить ACF поля
- `DELETE /acf-values/{post_id}/{field_name}` - Очистить поле

### Terms
- `GET /terms/{taxonomy}` - Список термов
- `POST /terms/{taxonomy}` - Создать терм
- `PUT /terms/{taxonomy}/{term_id}` - Обновить терм
- `DELETE /terms/{taxonomy}/{term_id}` - Удалить терм
- `POST /terms/assign` - Привязать термы к записи

### Media
- `POST /media/upload-from-url` - Загрузить файл по URL
- `POST /media/upload-multiple` - Загрузить несколько файлов
- `POST /media/set-featured` - Установить featured image
- `GET /media` - Список медиафайлов

## MCP Tools

Полный список инструментов MCP:

### Structure
- `create_custom_post_type` - Создать тип записи
- `list_custom_post_types` - Список типов записей
- `create_options_page` - Создать страницу опций
- `create_taxonomy` - Создать таксономию
- `create_field_group` - Создать группу полей

### Data
- `update_acf_fields` - Обновить ACF поля
- `get_acf_fields` - Получить ACF поля
- `create_term` - Создать терм
- `assign_terms_to_post` - Привязать термы
- `upload_image_from_url` - Загрузить изображение
- `set_featured_image` - Установить миниатюру

## Структура плагина

```
acf-mcp-manager/
├── acf-mcp-manager.php           # Основной файл плагина
├── includes/
│   ├── class-cpt-creator.php           # Custom Post Types
│   ├── class-options-page-creator.php  # Options Pages
│   ├── class-taxonomy-creator.php      # Taxonomies
│   ├── class-field-group-creator.php   # Field Groups
│   ├── class-acf-values-manager.php    # ACF Values
│   ├── class-term-manager.php          # Terms
│   ├── class-media-manager.php         # Media
│   ├── class-rest-api.php              # REST API
│   └── class-mcp-integration.php       # MCP интеграция
└── README.md
```

## Changelog

### v4.0.1
- ✨ **target_type + target_value** - упрощённые параметры для указания места отображения Field Group
- ✨ Поддержка всех типов location: post_type, taxonomy, options_page, page_template, user_form, nav_menu, block
- ✨ Нормализация разных форматов location (полный, упрощённый, одно правило)
- 📝 Обновлена документация и примеры

### v4.0.0
- 🔄 Переименование плагина: acf-cpt-manager → acf-mcp-manager
- 🔄 REST API namespace: acf-mcp-manager/v1
- 🔄 Обновлены имена всех классов

### v3.2.0
- ✨ Загрузка медиа по URL
- ✨ Установка featured image через MCP/API

### v3.1.0
- ✨ Управление ACF полями через MCP
- ✨ Управление термами таксономий
- ✨ Значения страниц опций

### v3.0.0
- ✨ ACF Field Groups с поддержкой всех типов полей
- ✨ Flexible Content поддержка
- ✨ Условная логика показа

### v2.0.0
- ✨ Options Pages ACF
- ✨ Taxonomies

### v1.0.0
- ✨ Custom Post Types через MCP

## Лицензия

GPL v2 or later

## Автор

koystrubvs

## Поддержка

GitHub: https://github.com/koystrubvs/acf-mcp-manager
