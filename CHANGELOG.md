# Changelog

Все важные изменения в проекте документируются в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/),
и проект придерживается [Semantic Versioning](https://semver.org/lang/ru/).

## [4.0.0] - 2025-11-26

### Изменено
- 🔄 **Переименование плагина**: `acf-cpt-manager` → `acf-mcp-manager`
- 🔄 **REST API namespace**: `acf-cpt-manager/v1` → `acf-mcp-manager/v1`
- 🔄 **Обновлены все имена классов**:
  - `ACF_CPT_Manager` → `ACF_MCP_Manager`
  - `ACF_CPT_Creator` → `ACF_MCP_CPT_Creator`
  - `ACF_CPT_REST_API` → `ACF_MCP_REST_API`
  - `ACF_CPT_MCP_Integration` → `ACF_MCP_Integration`
  - `ACF_Options_Page_Creator` → `ACF_MCP_Options_Page_Creator`
  - `ACF_Taxonomy_Creator` → `ACF_MCP_Taxonomy_Creator`
  - `ACF_Field_Group_Creator` → `ACF_MCP_Field_Group_Creator`
  - `ACF_Values_Manager` → `ACF_MCP_Values_Manager`
  - `ACF_Term_Manager` → `ACF_MCP_Term_Manager`
  - `ACF_Media_Manager` → `ACF_MCP_Media_Manager`
- 🔄 **Обновлены wp_options ключи**:
  - `acf_cpt_manager_*` → `acf_mcp_manager_*`
- 🔄 GitHub URL: `acf-cpt-manager` → `acf-mcp-manager`

### Добавлено
- ✨ Автоматическая миграция настроек со старого плагина при активации

---

## [3.2.0] - 2025-11-26

### Добавлено
- ✨ **Media Manager** - загрузка медиафайлов через MCP/API
  - `upload_image_from_url` - загрузка по URL с автоустановкой в ACF поле
  - `upload_images_from_urls` - пакетная загрузка для Gallery полей
  - `set_featured_image` - установка миниатюры записи
  - `list_media` - просмотр медиатеки с фильтрами
- ✨ REST API endpoints для Media:
  - `POST /media/upload-from-url`
  - `POST /media/upload-multiple`
  - `POST /media/set-featured`
  - `GET /media`

---

## [3.1.0] - 2025-11-26

### Добавлено
- ✨ **ACF Values Manager** - управление значениями полей через MCP/API
  - `update_acf_fields` - обновление полей записей, термов, опций
  - `get_acf_fields` - получение значений полей
  - `delete_acf_field` - очистка значения поля
- ✨ **Term Manager** - управление термами таксономий
  - `create_term` - создание термов с ACF полями
  - `update_term` - обновление термов
  - `delete_term` - удаление термов
  - `list_terms` - список термов с ACF данными
  - `assign_terms_to_post` - привязка термов к записям
  - `get_post_terms` - получение термов записи
- ✨ REST API endpoints для ACF Values и Terms

---

## [3.0.0] - 2025-11-26

### Добавлено
- ✨ **ACF Field Groups модуль** - полная поддержка создания групп полей через MCP/API
- ✨ Поддержка **всех типов полей ACF Pro**:
  - Basic: text, textarea, number, range, email, url, password
  - Content: image, file, wysiwyg, oembed, gallery
  - Choice: select, checkbox, radio, button_group, true_false
  - Relational: link, post_object, page_link, relationship, taxonomy, user
  - jQuery: google_map, date_picker, date_time_picker, time_picker, color_picker
  - Layout (PRO): message, accordion, tab, group, repeater, flexible_content, clone
- ✨ **Conditional Logic** - условная логика показа полей:
  - Операторы: ==, !=, ==empty, !=empty, ==contains
  - Группировка условий (AND/OR)
  - Ссылки на поля по имени или ключу
- ✨ **Location Rules** - правила показа групп:
  - post_type, post_template, post_status, post_format
  - page_template, page_type, page_parent
  - taxonomy, options_page, user_form, nav_menu
  - block, attachment, comment, widget
- ✨ **Вложенные поля** - полная поддержка:
  - Repeater с sub_fields
  - Group с sub_fields
  - Flexible Content с layouts и sub_fields
- ✨ MCP Tools для Field Groups:
  - `create_field_group` - создание групп полей
  - `list_field_groups` - просмотр всех групп
  - `get_field_group` - получение детальной информации
  - `update_field_group` - обновление группы
  - `toggle_field_group` - активация/деактивация
  - `delete_field_group` - удаление с поддержкой permanent
  - `add_field_to_group` - добавление поля в группу
  - `remove_field_from_group` - удаление поля из группы
  - `get_field_types` - список всех типов полей с параметрами
  - `get_field_group_templates` - готовые шаблоны
- ✨ REST API endpoints для Field Groups:
  - `GET/POST /field-groups`
  - `GET/PUT/DELETE /field-groups/{key}`
  - `POST /field-groups/{key}/toggle`
  - `POST /field-groups/{key}/fields`
  - `DELETE /field-groups/{key}/fields/{field_key}`
  - `GET /field-types`
  - `GET /field-groups/templates`
- ✨ 5 готовых шаблонов групп полей:
  - `product_info` - Информация о продукте
  - `seo_fields` - SEO поля
  - `contact_info` - Контактная информация
  - `person_profile` - Профиль человека
  - `event_details` - Детали мероприятия
- ✨ Поддержка GraphQL для групп полей:
  - `show_in_graphql` - включение в GraphQL схему
  - `graphql_field_name` - имя поля в GraphQL (camelCase)
- ✨ Класс `ACF_Field_Group_Creator` для управления группами полей
- 📝 Обновлён `ПРИМЕРЫ.md` с примерами создания групп полей

### Изменено
- 🔄 Версия плагина с 2.1.0 на 3.0.0
- 🔄 Расширена документация с примерами всех типов полей

---

## [2.1.0] - 2025-09-30

### Добавлено
- ✨ **Taxonomies модуль** - полная поддержка создания и управления таксономий ACF
- ✨ MCP Tools для Taxonomies:
  - `create_taxonomy` - создание таксономий
  - `list_taxonomies` - просмотр всех таксономий
  - `toggle_taxonomy` - активация/деактивация
  - `delete_taxonomy` - удаление с поддержкой permanent
  - `get_taxonomy_templates` - получение шаблонов
- ✨ REST API endpoints для Taxonomies:
  - `GET/POST /taxonomies`
  - `PUT/DELETE /taxonomies/{key}`
  - `POST /taxonomies/{key}/toggle`
  - `GET /taxonomies/templates`
- ✨ 4 готовых шаблона таксономий:
  - `category` - Иерархические категории
  - `tag` - Плоские теги
  - `location` - Географические локации
  - `service_type` - Типы услуг
- ✨ Поддержка GraphQL для таксономий:
  - `show_in_graphql` - включение в GraphQL схему
  - `graphql_single_name` - название в единственном числе (PascalCase)
  - `graphql_plural_name` - название во множественном числе (PascalCase)
- ✨ Класс `ACF_Taxonomy_Creator` для управления таксономиями
- ✨ Полное удаление (permanent delete) для Options Pages:
  - Параметр `permanent=true` для REST API и MCP
  - MCP tool `delete_options_page` с поддержкой permanent
  - Очистка ACF UI постов и данных из БД

### Изменено
- 🔄 Options Pages: добавлена поддержка полей `description` и GraphQL
- 🔄 Улучшена структура сохранения данных - используется `post_content` с сериализацией (формат ACF)
- 🔄 Обновлены обработчики удаления с поддержкой деактивации и permanent delete

### Исправлено
- 🐛 Исправлено сохранение данных Options Pages в формате ACF (post_content)
- 🐛 Исправлена генерация GraphQL имен (PascalCase вместо kebab-case)
- 🐛 Корректная регистрация Options Pages через `acf/init` hook

---

## [2.0.0] - 2025-09-30

### Добавлено
- ✨ **Options Pages модуль** - полная поддержка создания и управления страницами опций ACF
- ✨ MCP Tools для Options Pages:
  - `create_options_page` - создание страниц опций
  - `list_options_pages` - просмотр всех страниц
  - `toggle_options_page` - активация/деактивация
  - `get_options_page_templates` - получение шаблонов
- ✨ REST API endpoints для Options Pages:
  - `GET/POST /options-pages`
  - `PUT/DELETE /options-pages/{slug}`
  - `POST /options-pages/{slug}/toggle`
  - `GET /options-pages/templates`
- ✨ 5 готовых шаблонов страниц опций:
  - `general_settings` - Общие настройки
  - `theme_settings` - Настройки темы
  - `contact_info` - Контактная информация
  - `seo_settings` - SEO настройки
  - `header_footer` - Шапка и подвал
- ✨ Поддержка подстраниц (sub-pages) для Options Pages
- ✨ Класс `ACF_Options_Page_Creator` для управления Options Pages
- 📝 Файл `ПРИМЕРЫ.md` с примерами использования на русском
- 📝 Файл `ARCHITECTURE.md` с технической документацией
- 📝 Файл `CHANGELOG.md` для истории изменений

### Изменено
- 🔄 Название плагина с "ACF Custom Post Type Manager" на "ACF Backend Manager"
- 🔄 Описание плагина - теперь полноценный менеджер WordPress структур
- 🔄 Версия с 1.0.0 на 2.0.0
- 📝 Полностью переписан README.md с документацией по Options Pages

### Исправлено
- 🐛 Удалены проблемные вызовы `file_put_contents('/tmp/')` которые вызывали ошибки доступа
- 🐛 Оптимизировано логирование через `error_log()` вместо файлов

### Удалено
- ❌ Отладочная запись в `/tmp/acf-cpt-debug.log`

---

## [1.0.0] - 2025-09-25

### Добавлено
- ✨ Создание Custom Post Types через MCP API
- ✨ Интеграция с WordPress MCP для AI
- ✨ REST API для управления типами записей
- ✨ MCP Tools:
  - `create_custom_post_type`
  - `list_custom_post_types`
  - `toggle_custom_post_type`
  - `get_post_type_templates`
- ✨ 5 готовых шаблонов типов записей:
  - `blog_post` - Блог
  - `portfolio` - Портфолио
  - `testimonials` - Отзывы
  - `team` - Команда
  - `events` - События
- ✨ Активация/деактивация типов записей
- ✨ Поддержка ACF Pro для расширенных возможностей
- ✨ Автоматическая регистрация в WPGraphQL
- ✨ REST API endpoints:
  - `GET/POST /post-types`
  - `PUT/DELETE /post-types/{key}`
  - `POST /post-types/{key}/toggle`
  - `GET /post-types/templates`
- 📝 Базовый README.md
- 🔧 Singleton паттерн для всех классов
- 🔧 Хранение конфигурации в `wp_options`

### Технические детали
- PHP 8.0+ совместимость
- WordPress 6.0+ совместимость
- Модульная архитектура
- PSR совместимые классы
- Использование WordPress Coding Standards

---

## [Unreleased]

### Планируется
- [ ] Шаблоны для отраслей (медицина, e-commerce, недвижимость)
- [ ] CLI команды (WP-CLI интеграция)
- [ ] Webhooks для изменений структуры
- [ ] Миграции структур между сайтами
- [ ] Тестовые данные (fixtures)
- [ ] Roles & Capabilities управление
- [ ] Custom Admin Columns

---

## Типы изменений
- ✨ `Добавлено` - для новых функций
- 🔄 `Изменено` - для изменений существующего функционала
- 🗑️ `Устарело` - для функций, которые скоро будут удалены
- ❌ `Удалено` - для удаленных функций
- 🐛 `Исправлено` - для исправления багов
- 🔒 `Безопасность` - для исправлений уязвимостей

---

## Ссылки
- [GitHub Repository](https://github.com/koystrubvs/acf-mcp-manager)
- [WordPress MCP](https://github.com/Automattic/wordpress-mcp)
- [ACF Pro](https://www.advancedcustomfields.com/)

---

## Команда
- **Автор:** koystrubvs
- **Лицензия:** GPL v2 or later
