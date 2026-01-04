<?php
/**
 * Класс для создания и управления ACF Field Groups через MCP/API
 * 
 * @package ACF_MCP_Manager
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACF_MCP_Field_Group_Creator {
    
    private static $instance = null;
    
    /**
     * Поддерживаемые типы полей с их параметрами
     */
    private $field_types = array();
    
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
        $this->init_field_types();
    }
    
    /**
     * Инициализация типов полей с их параметрами
     */
    private function init_field_types() {
        $this->field_types = array(
            // Basic Fields
            'text' => array(
                'category' => 'basic',
                'label' => 'Текст',
                'supports' => array('default_value', 'placeholder', 'prepend', 'append', 'maxlength')
            ),
            'textarea' => array(
                'category' => 'basic',
                'label' => 'Текстовая область',
                'supports' => array('default_value', 'placeholder', 'rows', 'maxlength', 'new_lines')
            ),
            'number' => array(
                'category' => 'basic',
                'label' => 'Число',
                'supports' => array('default_value', 'placeholder', 'prepend', 'append', 'min', 'max', 'step')
            ),
            'range' => array(
                'category' => 'basic',
                'label' => 'Диапазон',
                'supports' => array('default_value', 'min', 'max', 'step', 'prepend', 'append')
            ),
            'email' => array(
                'category' => 'basic',
                'label' => 'Email',
                'supports' => array('default_value', 'placeholder', 'prepend', 'append')
            ),
            'url' => array(
                'category' => 'basic',
                'label' => 'URL',
                'supports' => array('default_value', 'placeholder')
            ),
            'password' => array(
                'category' => 'basic',
                'label' => 'Пароль',
                'supports' => array('placeholder', 'prepend', 'append')
            ),
            
            // Content Fields
            'image' => array(
                'category' => 'content',
                'label' => 'Изображение',
                'supports' => array('return_format', 'preview_size', 'library', 'min_width', 'min_height', 'max_width', 'max_height', 'mime_types')
            ),
            'file' => array(
                'category' => 'content',
                'label' => 'Файл',
                'supports' => array('return_format', 'library', 'min_size', 'max_size', 'mime_types')
            ),
            'wysiwyg' => array(
                'category' => 'content',
                'label' => 'Визуальный редактор',
                'supports' => array('default_value', 'tabs', 'toolbar', 'media_upload', 'delay')
            ),
            'oembed' => array(
                'category' => 'content',
                'label' => 'oEmbed',
                'supports' => array('width', 'height')
            ),
            'gallery' => array(
                'category' => 'content',
                'label' => 'Галерея',
                'supports' => array('return_format', 'preview_size', 'library', 'min', 'max', 'min_width', 'min_height', 'max_width', 'max_height', 'mime_types', 'insert')
            ),
            
            // Choice Fields
            'select' => array(
                'category' => 'choice',
                'label' => 'Выпадающий список',
                'supports' => array('choices', 'default_value', 'allow_null', 'multiple', 'ui', 'ajax', 'return_format', 'placeholder')
            ),
            'checkbox' => array(
                'category' => 'choice',
                'label' => 'Флажки',
                'supports' => array('choices', 'default_value', 'layout', 'toggle', 'return_format', 'allow_custom', 'save_custom')
            ),
            'radio' => array(
                'category' => 'choice',
                'label' => 'Переключатели',
                'supports' => array('choices', 'default_value', 'layout', 'allow_null', 'other_choice', 'return_format')
            ),
            'button_group' => array(
                'category' => 'choice',
                'label' => 'Группа кнопок',
                'supports' => array('choices', 'default_value', 'layout', 'allow_null', 'return_format')
            ),
            'true_false' => array(
                'category' => 'choice',
                'label' => 'Да/Нет',
                'supports' => array('default_value', 'message', 'ui', 'ui_on_text', 'ui_off_text')
            ),
            
            // Relational Fields
            'link' => array(
                'category' => 'relational',
                'label' => 'Ссылка',
                'supports' => array('return_format')
            ),
            'post_object' => array(
                'category' => 'relational',
                'label' => 'Объект записи',
                'supports' => array('post_type', 'taxonomy', 'allow_null', 'multiple', 'return_format', 'ui')
            ),
            'page_link' => array(
                'category' => 'relational',
                'label' => 'Ссылка на страницу',
                'supports' => array('post_type', 'taxonomy', 'allow_null', 'allow_archives', 'multiple')
            ),
            'relationship' => array(
                'category' => 'relational',
                'label' => 'Связь',
                'supports' => array('post_type', 'taxonomy', 'filters', 'elements', 'min', 'max', 'return_format')
            ),
            'taxonomy' => array(
                'category' => 'relational',
                'label' => 'Таксономия',
                'supports' => array('taxonomy', 'field_type', 'allow_null', 'add_term', 'save_terms', 'load_terms', 'return_format', 'multiple')
            ),
            'user' => array(
                'category' => 'relational',
                'label' => 'Пользователь',
                'supports' => array('role', 'allow_null', 'multiple', 'return_format')
            ),
            
            // jQuery Fields
            'google_map' => array(
                'category' => 'jquery',
                'label' => 'Google Карта',
                'supports' => array('center_lat', 'center_lng', 'zoom', 'height')
            ),
            'date_picker' => array(
                'category' => 'jquery',
                'label' => 'Выбор даты',
                'supports' => array('display_format', 'return_format', 'first_day')
            ),
            'date_time_picker' => array(
                'category' => 'jquery',
                'label' => 'Выбор даты и времени',
                'supports' => array('display_format', 'return_format', 'first_day')
            ),
            'time_picker' => array(
                'category' => 'jquery',
                'label' => 'Выбор времени',
                'supports' => array('display_format', 'return_format')
            ),
            'color_picker' => array(
                'category' => 'jquery',
                'label' => 'Выбор цвета',
                'supports' => array('default_value', 'enable_opacity', 'return_format')
            ),
            
            // Layout Fields (PRO)
            'message' => array(
                'category' => 'layout',
                'label' => 'Сообщение',
                'supports' => array('message', 'new_lines', 'esc_html')
            ),
            'accordion' => array(
                'category' => 'layout',
                'label' => 'Аккордеон',
                'supports' => array('open', 'multi_expand', 'endpoint')
            ),
            'tab' => array(
                'category' => 'layout',
                'label' => 'Вкладка',
                'supports' => array('placement', 'endpoint')
            ),
            'group' => array(
                'category' => 'layout',
                'label' => 'Группа',
                'supports' => array('sub_fields', 'layout')
            ),
            'repeater' => array(
                'category' => 'layout',
                'label' => 'Повторитель',
                'supports' => array('sub_fields', 'min', 'max', 'layout', 'button_label', 'collapsed', 'rows_per_page')
            ),
            'flexible_content' => array(
                'category' => 'layout',
                'label' => 'Гибкий контент',
                'supports' => array('layouts', 'min', 'max', 'button_label')
            ),
            'clone' => array(
                'category' => 'layout',
                'label' => 'Клон',
                'supports' => array('clone', 'display', 'layout', 'prefix_label', 'prefix_name')
            ),
            
            // PRO Fields
            'icon_picker' => array(
                'category' => 'pro',
                'label' => 'Выбор иконки',
                'supports' => array('return_format', 'library')
            )
        );
    }
    
    /**
     * Создать новую группу полей
     * 
     * @param array $args Аргументы группы полей
     * @return array|WP_Error
     */
    public function create_field_group($args) {
        // Валидация обязательных параметров
        if (empty($args['title'])) {
            return new WP_Error('missing_title', 'Не указан заголовок группы полей');
        }
        
        if (empty($args['fields']) || !is_array($args['fields'])) {
            return new WP_Error('missing_fields', 'Не указаны поля группы');
        }
        
        // Генерируем уникальный ключ группы
        $group_key = $this->generate_group_key($args['title']);
        
        // Проверяем, не существует ли уже группа с таким ключом
        if ($this->field_group_exists($group_key)) {
            return new WP_Error('group_exists', 'Группа полей с таким ключом уже существует');
        }
        
        // Подготавливаем location rules
        $location = null;
        
        // Приоритет 1: Упрощённые параметры target_type и target_value
        if (!empty($args['target_type']) && !empty($args['target_value'])) {
            $location = $this->create_location_from_target($args['target_type'], $args['target_value']);
        }
        // Приоритет 2: Полный location массив
        elseif (!empty($args['location'])) {
            $location = $this->prepare_location_rules($args['location']);
            if (is_wp_error($location)) {
                return $location;
            }
        }
        
        // Дефолт: если location всё ещё не установлен, ставим post_type == post
        if (empty($location)) {
            $location = array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post'
                    )
                )
            );
        }
        
        // Подготавливаем поля с генерацией ключей
        $prepared_fields = $this->prepare_fields($args['fields'], $group_key);
        if (is_wp_error($prepared_fields)) {
            return $prepared_fields;
        }
        
        // Формируем данные группы полей
        $field_group = array(
            'key' => $group_key,
            'title' => sanitize_text_field($args['title']),
            'fields' => $prepared_fields,
            'location' => $location,
            'menu_order' => intval($args['menu_order'] ?? 0),
            'position' => $args['position'] ?? 'normal',
            'style' => $args['style'] ?? 'default',
            'label_placement' => $args['label_placement'] ?? 'top',
            'instruction_placement' => $args['instruction_placement'] ?? 'label',
            'hide_on_screen' => $args['hide_on_screen'] ?? array(),
            'active' => true,
            'description' => sanitize_textarea_field($args['description'] ?? ''),
        );
        
        // GraphQL поддержка
        if (!empty($args['show_in_graphql'])) {
            $field_group['show_in_graphql'] = 1;
            // Генерируем GraphQL имя из slug (латиница) или из переданного значения
            if (!empty($args['graphql_field_name'])) {
                $field_group['graphql_field_name'] = $args['graphql_field_name'];
            } else {
                // Создаём slug из заголовка (транслитерация)
                $slug = sanitize_title($args['title']);
                $field_group['graphql_field_name'] = $this->to_camel_case($slug);
            }
        }
        
        // Сохраняем через ACF API
        if (!function_exists('acf_update_field_group')) {
            return new WP_Error('acf_not_available', 'Функция acf_update_field_group недоступна');
        }
        
        $result = acf_update_field_group($field_group);
        
        if (!$result || is_wp_error($result)) {
            return new WP_Error('create_failed', 'Не удалось создать группу полей');
        }
        
        // Сохраняем поля
        $field_group_id = $result['ID'] ?? 0;
        foreach ($prepared_fields as $index => $field) {
            $field['parent'] = $field_group_id;
            $field['menu_order'] = $index;
            $this->save_field($field, $field_group_id);
        }
        
        // Сохраняем в нашу систему для отслеживания
        $this->store_field_group($group_key, $field_group, $field_group_id);
        
        // Экспортируем в JSON если ACF Local JSON активен
        $json_saved = $this->save_to_json($group_key);
        
        return array(
            'success' => true,
            'field_group_id' => $field_group_id,
            'field_group_key' => $group_key,
            'fields_count' => count($prepared_fields),
            'json_saved' => $json_saved,
            'message' => sprintf('Группа полей "%s" успешно создана с %d полями', $args['title'], count($prepared_fields))
        );
    }
    
    /**
     * Рекурсивное сохранение полей (включая вложенные)
     */
    private function save_field($field, $parent_id, $parent_layout = '') {
        $field['parent'] = $parent_id;
        
        // Если это поле внутри layout - добавляем parent_layout
        if (!empty($parent_layout)) {
            $field['parent_layout'] = $parent_layout;
        }
        
        // Временно убираем sub_fields и layouts перед сохранением основного поля
        $sub_fields = null;
        $layouts = null;
        
        if (!empty($field['sub_fields'])) {
            $sub_fields = $field['sub_fields'];
            unset($field['sub_fields']);
        }
        
        if (!empty($field['layouts'])) {
            $layouts = $field['layouts'];
            // Для flexible_content layouts сохраняем в поле, но без sub_fields
            $field['layouts'] = array_map(function($layout) {
                $clean_layout = $layout;
                unset($clean_layout['sub_fields']);
                return $clean_layout;
            }, $layouts);
        }
        
        // Сохраняем поле
        $saved_field = acf_update_field($field);
        $saved_field_id = $saved_field['ID'] ?? 0;
        
        // Если есть sub_fields (для group/repeater), сохраняем их рекурсивно
        if (!empty($sub_fields) && is_array($sub_fields)) {
            foreach ($sub_fields as $index => $sub_field) {
                $sub_field['menu_order'] = $index;
                $this->save_field($sub_field, $saved_field_id);
            }
        }
        
        // Если есть layouts (для flexible_content), сохраняем sub_fields каждого layout
        if (!empty($layouts) && is_array($layouts)) {
            foreach ($layouts as $layout_index => $layout) {
                $layout_key = $layout['key'] ?? '';
                
                if (!empty($layout['sub_fields']) && !empty($layout_key)) {
                    foreach ($layout['sub_fields'] as $sub_index => $sub_field) {
                        $sub_field['menu_order'] = $sub_index;
                        $this->save_field($sub_field, $saved_field_id, $layout_key);
                    }
                }
            }
        }
        
        return $saved_field;
    }
    
    /**
     * Обновить группу полей
     */
    public function update_field_group($group_key, $args) {
        // Получаем существующую группу
        $field_group = acf_get_field_group($group_key);
        
        if (!$field_group) {
            return new WP_Error('group_not_found', 'Группа полей не найдена');
        }
        
        // Обновляем только переданные параметры
        if (isset($args['title'])) {
            $field_group['title'] = sanitize_text_field($args['title']);
        }
        
        // Обработка location: приоритет target_type/target_value над location
        if (!empty($args['target_type']) && !empty($args['target_value'])) {
            $field_group['location'] = $this->create_location_from_target($args['target_type'], $args['target_value']);
        } elseif (isset($args['location'])) {
            $location = $this->prepare_location_rules($args['location']);
            if (is_wp_error($location)) {
                return $location;
            }
            if (!empty($location)) {
                $field_group['location'] = $location;
            }
        }
        
        if (isset($args['menu_order'])) {
            $field_group['menu_order'] = intval($args['menu_order']);
        }
        
        if (isset($args['position'])) {
            $field_group['position'] = $args['position'];
        }
        
        if (isset($args['style'])) {
            $field_group['style'] = $args['style'];
        }
        
        if (isset($args['label_placement'])) {
            $field_group['label_placement'] = $args['label_placement'];
        }
        
        if (isset($args['instruction_placement'])) {
            $field_group['instruction_placement'] = $args['instruction_placement'];
        }
        
        if (isset($args['hide_on_screen'])) {
            $field_group['hide_on_screen'] = $args['hide_on_screen'];
        }
        
        if (isset($args['description'])) {
            $field_group['description'] = sanitize_textarea_field($args['description']);
        }
        
        if (isset($args['show_in_graphql'])) {
            $field_group['show_in_graphql'] = $args['show_in_graphql'] ? 1 : 0;
        }
        
        if (isset($args['graphql_field_name'])) {
            $field_group['graphql_field_name'] = $args['graphql_field_name'];
        }
        
        // Обновляем поля если переданы
        if (isset($args['fields']) && is_array($args['fields'])) {
            $prepared_fields = $this->prepare_fields($args['fields'], $group_key);
            if (is_wp_error($prepared_fields)) {
                return $prepared_fields;
            }
            
            // Удаляем старые поля
            $old_fields = acf_get_fields($field_group);
            foreach ($old_fields as $old_field) {
                acf_delete_field($old_field['ID']);
            }
            
            // Сохраняем новые поля
            foreach ($prepared_fields as $index => $field) {
                $field['parent'] = $field_group['ID'];
                $field['menu_order'] = $index;
                $this->save_field($field, $field_group['ID']);
            }
        }
        
        // Сохраняем группу
        $result = acf_update_field_group($field_group);
        
        if (!$result) {
            return new WP_Error('update_failed', 'Не удалось обновить группу полей');
        }
        
        // Экспортируем в JSON если ACF Local JSON активен
        $json_saved = $this->save_to_json($group_key);
        
        return array(
            'success' => true,
            'field_group_key' => $group_key,
            'json_saved' => $json_saved,
            'message' => 'Группа полей успешно обновлена'
        );
    }
    
    /**
     * Удалить группу полей
     */
    public function delete_field_group($group_key, $permanent = false) {
        $field_group = acf_get_field_group($group_key);
        
        if (!$field_group) {
            return new WP_Error('group_not_found', 'Группа полей не найдена');
        }
        
        if ($permanent) {
            // Полное удаление
            acf_delete_field_group($field_group['ID']);
            $this->remove_stored_field_group($group_key);
            
            // Удаляем JSON файл
            $json_deleted = $this->delete_json_file($group_key);
            
            return array(
                'success' => true,
                'json_deleted' => $json_deleted,
                'message' => 'Группа полей полностью удалена'
            );
        } else {
            // Деактивация
            acf_update_field_group_active_status($field_group['ID'], false);
            
            return array(
                'success' => true,
                'message' => 'Группа полей деактивирована'
            );
        }
    }
    
    /**
     * Активировать/деактивировать группу полей
     */
    public function toggle_field_group($group_key, $active = true) {
        $field_group = acf_get_field_group($group_key);
        
        if (!$field_group) {
            return new WP_Error('group_not_found', 'Группа полей не найдена');
        }
        
        acf_update_field_group_active_status($field_group['ID'], $active);
        
        $status = $active ? 'активирована' : 'деактивирована';
        return array(
            'success' => true,
            'message' => sprintf('Группа полей %s', $status)
        );
    }
    
    /**
     * Получить список групп полей
     */
    public function get_field_groups() {
        $field_groups = acf_get_field_groups();
        $result = array();
        
        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group);
            $result[] = array(
                'key' => $group['key'],
                'title' => $group['title'],
                'active' => $group['active'],
                'fields_count' => count($fields),
                'location' => $group['location'],
                'menu_order' => $group['menu_order'],
                'description' => $group['description'] ?? '',
                'show_in_graphql' => !empty($group['show_in_graphql'])
            );
        }
        
        return $result;
    }
    
    /**
     * Получить группу полей по ключу
     */
    public function get_field_group($group_key) {
        $field_group = acf_get_field_group($group_key);
        
        if (!$field_group) {
            return new WP_Error('group_not_found', 'Группа полей не найдена');
        }
        
        $fields = acf_get_fields($field_group);
        $field_group['fields'] = $this->format_fields_for_output($fields);
        
        return $field_group;
    }
    
    /**
     * Добавить поле в существующую группу
     */
    public function add_field_to_group($group_key, $field_data) {
        $field_group = acf_get_field_group($group_key);
        
        if (!$field_group) {
            return new WP_Error('group_not_found', 'Группа полей не найдена');
        }
        
        // Подготавливаем поле
        $prepared_field = $this->prepare_single_field($field_data, $group_key);
        if (is_wp_error($prepared_field)) {
            return $prepared_field;
        }
        
        // Получаем текущее количество полей для menu_order
        $existing_fields = acf_get_fields($field_group);
        $prepared_field['parent'] = $field_group['ID'];
        $prepared_field['menu_order'] = count($existing_fields);
        
        // Сохраняем поле
        $saved_field = $this->save_field($prepared_field, $field_group['ID']);
        
        return array(
            'success' => true,
            'field_key' => $prepared_field['key'],
            'message' => sprintf('Поле "%s" добавлено в группу', $field_data['label'] ?? $field_data['name'])
        );
    }
    
    /**
     * Удалить поле из группы
     */
    public function remove_field_from_group($group_key, $field_key) {
        $field = acf_get_field($field_key);
        
        if (!$field) {
            return new WP_Error('field_not_found', 'Поле не найдено');
        }
        
        acf_delete_field($field['ID']);
        
        return array(
            'success' => true,
            'message' => 'Поле удалено из группы'
        );
    }
    
    /**
     * Получить список поддерживаемых типов полей
     */
    public function get_field_types() {
        return $this->field_types;
    }
    
    /**
     * Подготовка полей с генерацией ключей и валидацией
     */
    private function prepare_fields($fields, $group_key, $parent_key = null) {
        $prepared = array();
        
        foreach ($fields as $index => $field) {
            $prepared_field = $this->prepare_single_field($field, $group_key, $parent_key, $index);
            if (is_wp_error($prepared_field)) {
                return $prepared_field;
            }
            $prepared[] = $prepared_field;
        }
        
        return $prepared;
    }
    
    /**
     * Подготовка одного поля
     */
    private function prepare_single_field($field, $group_key, $parent_key = null, $index = 0) {
        // Валидация типа поля
        $type = $field['type'] ?? 'text';
        if (!isset($this->field_types[$type])) {
            return new WP_Error('invalid_field_type', sprintf('Неподдерживаемый тип поля: %s', $type));
        }
        
        // Валидация обязательных параметров
        if (empty($field['name'])) {
            return new WP_Error('missing_field_name', 'Не указано имя поля (name)');
        }
        
        // Генерируем уникальный ключ поля
        $field_key = $this->generate_field_key($field['name'], $group_key, $parent_key);
        
        // Базовые параметры поля
        $prepared = array(
            'key' => $field_key,
            'label' => sanitize_text_field($field['label'] ?? ucfirst($field['name'])),
            'name' => sanitize_key($field['name']),
            'type' => $type,
            'instructions' => sanitize_textarea_field($field['instructions'] ?? ''),
            'required' => !empty($field['required']),
            'wrapper' => array(
                'width' => $field['width'] ?? '',
                'class' => $field['class'] ?? '',
                'id' => $field['id'] ?? ''
            )
        );
        
        // Добавляем специфичные для типа параметры
        $type_config = $this->field_types[$type];
        foreach ($type_config['supports'] as $param) {
            if (isset($field[$param])) {
                $prepared[$param] = $field[$param];
            }
        }
        
        // Обработка conditional_logic
        if (!empty($field['conditional_logic'])) {
            $prepared['conditional_logic'] = $this->prepare_conditional_logic($field['conditional_logic'], $group_key);
        }
        
        // Рекурсивная обработка sub_fields для group/repeater
        if (in_array($type, array('group', 'repeater')) && !empty($field['sub_fields'])) {
            $sub_fields = $this->prepare_fields($field['sub_fields'], $group_key, $field_key);
            if (is_wp_error($sub_fields)) {
                return $sub_fields;
            }
            $prepared['sub_fields'] = $sub_fields;
        }
        
        // Обработка layouts для flexible_content
        if ($type === 'flexible_content' && !empty($field['layouts'])) {
            $prepared['layouts'] = $this->prepare_layouts($field['layouts'], $group_key, $field_key);
        }
        
        return $prepared;
    }
    
    /**
     * Подготовка layouts для flexible_content
     */
    private function prepare_layouts($layouts, $group_key, $parent_key) {
        $prepared_layouts = array();
        
        foreach ($layouts as $index => $layout) {
            $layout_key = $this->generate_layout_key($layout['name'] ?? 'layout_' . $index, $parent_key);
            
            $prepared_layout = array(
                'key' => $layout_key,
                'name' => sanitize_key($layout['name'] ?? 'layout_' . $index),
                'label' => sanitize_text_field($layout['label'] ?? ucfirst($layout['name'] ?? 'Layout ' . ($index + 1))),
                'display' => $layout['display'] ?? 'block',
                'min' => $layout['min'] ?? '',
                'max' => $layout['max'] ?? ''
            );
            
            if (!empty($layout['sub_fields'])) {
                $sub_fields = $this->prepare_fields($layout['sub_fields'], $group_key, $layout_key);
                if (is_wp_error($sub_fields)) {
                    return $sub_fields;
                }
                $prepared_layout['sub_fields'] = $sub_fields;
            }
            
            $prepared_layouts[] = $prepared_layout;
        }
        
        return $prepared_layouts;
    }
    
    /**
     * Подготовка conditional_logic
     */
    private function prepare_conditional_logic($logic, $group_key) {
        if (empty($logic) || !is_array($logic)) {
            return array();
        }
        
        $prepared = array();
        
        foreach ($logic as $group) {
            if (!is_array($group)) {
                continue;
            }
            
            $prepared_group = array();
            foreach ($group as $rule) {
                if (!isset($rule['field']) || !isset($rule['operator'])) {
                    continue;
                }
                
                $prepared_rule = array(
                    'field' => $this->resolve_field_key($rule['field'], $group_key),
                    'operator' => $rule['operator'],
                    'value' => $rule['value'] ?? ''
                );
                
                $prepared_group[] = $prepared_rule;
            }
            
            if (!empty($prepared_group)) {
                $prepared[] = $prepared_group;
            }
        }
        
        return $prepared;
    }
    
    /**
     * Разрешение ключа поля для conditional_logic
     */
    private function resolve_field_key($field_ref, $group_key) {
        // Если это уже полный ключ поля
        if (strpos($field_ref, 'field_') === 0) {
            return $field_ref;
        }
        
        // Генерируем ключ на основе имени поля
        return $this->generate_field_key($field_ref, $group_key);
    }
    
    /**
     * Подготовка location rules
     * 
     * Поддерживает несколько форматов:
     * 1. Полный формат: [[{param, operator, value}]]
     * 2. Упрощённый формат группы: [{param, operator, value}] - будет обёрнут в массив
     * 3. Упрощённый формат одного правила: {param, operator, value} - будет обёрнут в двойной массив
     * 4. Параметры target_type/target_value в args (обрабатываются в create_field_group)
     */
    private function prepare_location_rules($location) {
        $valid_params = array(
            'post_type', 'post_template', 'post_status', 'post_format', 'post_category', 'post_taxonomy', 'post',
            'page_template', 'page_type', 'page_parent', 'page',
            'current_user', 'current_user_role', 'user_form', 'user_role',
            'taxonomy', 'attachment', 'comment', 'widget', 'nav_menu', 'nav_menu_item',
            'block', 'options_page'
        );
        
        $valid_operators = array('==', '!=');
        
        // Если пусто - возвращаем null, чтобы вызывающий код мог установить дефолт
        if (empty($location)) {
            return null;
        }
        
        // Определяем формат и нормализуем к [[{rule}]]
        $normalized = $this->normalize_location_format($location);
        
        if (empty($normalized)) {
            return null;
        }
        
        $prepared = array();
        
        foreach ($normalized as $group) {
            if (!is_array($group)) {
                continue;
            }
            
            $prepared_group = array();
            foreach ($group as $rule) {
                if (!is_array($rule) || !isset($rule['param'])) {
                    continue;
                }
                
                // Валидация параметра
                if (!in_array($rule['param'], $valid_params)) {
                    return new WP_Error('invalid_location_param', sprintf('Недопустимый параметр location: %s. Доступные: %s', $rule['param'], implode(', ', $valid_params)));
                }
                
                $prepared_group[] = array(
                    'param' => $rule['param'],
                    'operator' => in_array($rule['operator'] ?? '==', $valid_operators) ? ($rule['operator'] ?? '==') : '==',
                    'value' => $rule['value'] ?? ''
                );
            }
            
            if (!empty($prepared_group)) {
                $prepared[] = $prepared_group;
            }
        }
        
        return !empty($prepared) ? $prepared : null;
    }
    
    /**
     * Нормализация формата location к [[{rule}]]
     */
    private function normalize_location_format($location) {
        if (!is_array($location)) {
            return null;
        }
        
        // Проверяем, это одно правило {param, value}?
        if (isset($location['param'])) {
            // Формат: {param, operator, value} → [[{rule}]]
            return array(array($location));
        }
        
        // Проверяем первый элемент
        $first = reset($location);
        
        if (!is_array($first)) {
            return null;
        }
        
        // Это [{param, value}] - массив правил (одна группа)?
        if (isset($first['param'])) {
            // Формат: [{rule1}, {rule2}] → [[{rule1}, {rule2}]]
            return array($location);
        }
        
        // Это [[{rule}]] - уже правильный формат?
        $first_of_first = reset($first);
        if (is_array($first_of_first) && isset($first_of_first['param'])) {
            // Уже правильный формат [[{rule}]]
            return $location;
        }
        
        return null;
    }
    
    /**
     * Создать location rules из упрощённых параметров target_type и target_value
     * 
     * @param string $target_type Тип цели: post_type, taxonomy, options_page, page_template и др.
     * @param string $target_value Значение: имя post_type, таксономии, slug страницы опций
     * @return array Location rules в формате [[{param, operator, value}]]
     */
    public function create_location_from_target($target_type, $target_value) {
        return array(
            array(
                array(
                    'param' => $target_type,
                    'operator' => '==',
                    'value' => $target_value
                )
            )
        );
    }
    
    /**
     * Генерация уникального ключа группы
     */
    private function generate_group_key($title) {
        $slug = sanitize_title($title);
        return 'group_' . substr(md5($slug . microtime()), 0, 13);
    }
    
    /**
     * Генерация уникального ключа поля
     */
    private function generate_field_key($name, $group_key, $parent_key = null) {
        $base = $parent_key ? $parent_key : $group_key;
        return 'field_' . substr(md5($base . '_' . $name . microtime()), 0, 13);
    }
    
    /**
     * Генерация ключа layout
     */
    private function generate_layout_key($name, $parent_key) {
        return 'layout_' . substr(md5($parent_key . '_' . $name . microtime()), 0, 13);
    }
    
    /**
     * Проверка существования группы полей
     */
    private function field_group_exists($key) {
        return acf_get_field_group($key) !== false;
    }
    
    /**
     * Сохранение группы в нашу систему отслеживания
     */
    private function store_field_group($key, $data, $post_id) {
        $stored = get_option('acf_cpt_manager_field_groups', array());
        $stored[$key] = array(
            'post_id' => $post_id,
            'title' => $data['title'],
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'method' => 'mcp'
        );
        update_option('acf_cpt_manager_field_groups', $stored);
    }
    
    /**
     * Удаление группы из системы отслеживания
     */
    private function remove_stored_field_group($key) {
        $stored = get_option('acf_cpt_manager_field_groups', array());
        unset($stored[$key]);
        update_option('acf_cpt_manager_field_groups', $stored);
    }
    
    /**
     * Сохранение группы полей в JSON (ACF Local JSON)
     * 
     * @param string $group_key Ключ группы полей
     * @return array Результат сохранения
     */
    private function save_to_json($group_key) {
        // Проверяем, включен ли ACF Local JSON
        if (!function_exists('acf_get_setting') || !function_exists('acf_get_field_group')) {
            return array('saved' => false, 'reason' => 'ACF functions not available');
        }
        
        // Получаем путь для сохранения JSON
        $save_path = acf_get_setting('save_json');
        
        if (empty($save_path)) {
            return array('saved' => false, 'reason' => 'ACF Local JSON save path not configured');
        }
        
        // Проверяем, существует ли и доступна ли папка для записи
        if (!is_dir($save_path)) {
            // Пробуем создать папку
            if (!wp_mkdir_p($save_path)) {
                return array('saved' => false, 'reason' => 'Cannot create JSON directory: ' . $save_path);
            }
        }
        
        if (!is_writable($save_path)) {
            return array('saved' => false, 'reason' => 'JSON directory not writable: ' . $save_path);
        }
        
        // Получаем полную группу полей с полями
        $field_group = acf_get_field_group($group_key);
        
        if (!$field_group) {
            return array('saved' => false, 'reason' => 'Field group not found');
        }
        
        // Получаем все поля группы
        $fields = acf_get_fields($field_group);
        $field_group['fields'] = $fields ?: array();
        
        // Удаляем ненужные для JSON поля
        unset($field_group['ID']);
        unset($field_group['id']);
        
        // Генерируем имя файла
        $file_name = $field_group['key'] . '.json';
        $file_path = trailingslashit($save_path) . $file_name;
        
        // Форматируем JSON с отступами для читаемости
        $json_content = json_encode($field_group, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json_content === false) {
            return array('saved' => false, 'reason' => 'JSON encode error');
        }
        
        // Записываем файл
        $result = file_put_contents($file_path, $json_content);
        
        if ($result === false) {
            return array('saved' => false, 'reason' => 'Failed to write file: ' . $file_path);
        }
        
        return array(
            'saved' => true,
            'path' => $file_path,
            'size' => $result
        );
    }
    
    /**
     * Удаление JSON файла группы полей
     * 
     * @param string $group_key Ключ группы полей
     * @return bool
     */
    private function delete_json_file($group_key) {
        $save_path = acf_get_setting('save_json');
        
        if (empty($save_path)) {
            return false;
        }
        
        $file_path = trailingslashit($save_path) . $group_key . '.json';
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        
        return true;
    }
    
    /**
     * Форматирование полей для вывода
     */
    private function format_fields_for_output($fields) {
        $result = array();
        
        foreach ($fields as $field) {
            $formatted = array(
                'key' => $field['key'],
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => $field['required'],
                'instructions' => $field['instructions'] ?? ''
            );
            
            // Добавляем sub_fields если есть
            if (!empty($field['sub_fields'])) {
                $formatted['sub_fields'] = $this->format_fields_for_output($field['sub_fields']);
            }
            
            // Добавляем layouts для flexible_content
            if (!empty($field['layouts'])) {
                $formatted['layouts'] = $field['layouts'];
            }
            
            // Добавляем conditional_logic если есть
            if (!empty($field['conditional_logic'])) {
                $formatted['conditional_logic'] = $field['conditional_logic'];
            }
            
            $result[] = $formatted;
        }
        
        return $result;
    }
    
    /**
     * Преобразование строки в camelCase
     */
    private function to_camel_case($string) {
        $string = str_replace(array('-', '_'), ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        return lcfirst($string);
    }
    
    /**
     * Экспорт группы полей в JSON (публичный метод)
     * 
     * @param string $group_key Ключ группы полей
     * @return array Результат экспорта
     */
    public function export_to_json($group_key) {
        return $this->save_to_json($group_key);
    }
    
    /**
     * Экспорт всех групп полей в JSON
     * 
     * @return array Результаты экспорта
     */
    public function export_all_to_json() {
        $field_groups = acf_get_field_groups();
        $results = array();
        $success_count = 0;
        $fail_count = 0;
        
        foreach ($field_groups as $group) {
            $result = $this->save_to_json($group['key']);
            $results[$group['key']] = array(
                'title' => $group['title'],
                'result' => $result
            );
            
            if (!empty($result['saved'])) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }
        
        return array(
            'success' => true,
            'total' => count($field_groups),
            'exported' => $success_count,
            'failed' => $fail_count,
            'details' => $results,
            'message' => sprintf('Экспортировано %d из %d групп полей', $success_count, count($field_groups))
        );
    }
    
    /**
     * Получить шаблоны групп полей
     */
    public function get_templates() {
        return array(
            'product_info' => array(
                'name' => 'Информация о продукте',
                'description' => 'Базовые поля для товара: цена, артикул, галерея',
                'fields' => array(
                    array('name' => 'price', 'label' => 'Цена', 'type' => 'number', 'required' => true, 'min' => 0),
                    array('name' => 'sale_price', 'label' => 'Цена со скидкой', 'type' => 'number', 'min' => 0),
                    array('name' => 'sku', 'label' => 'Артикул', 'type' => 'text'),
                    array('name' => 'gallery', 'label' => 'Галерея', 'type' => 'gallery')
                )
            ),
            'seo_fields' => array(
                'name' => 'SEO поля',
                'description' => 'Мета-теги для SEO оптимизации',
                'fields' => array(
                    array('name' => 'seo_title', 'label' => 'SEO Заголовок', 'type' => 'text', 'maxlength' => 60),
                    array('name' => 'seo_description', 'label' => 'SEO Описание', 'type' => 'textarea', 'rows' => 3, 'maxlength' => 160),
                    array('name' => 'seo_keywords', 'label' => 'Ключевые слова', 'type' => 'text')
                )
            ),
            'contact_info' => array(
                'name' => 'Контактная информация',
                'description' => 'Поля для контактных данных',
                'fields' => array(
                    array('name' => 'phone', 'label' => 'Телефон', 'type' => 'text'),
                    array('name' => 'email', 'label' => 'Email', 'type' => 'email'),
                    array('name' => 'address', 'label' => 'Адрес', 'type' => 'textarea', 'rows' => 2),
                    array('name' => 'map', 'label' => 'Карта', 'type' => 'google_map')
                )
            ),
            'person_profile' => array(
                'name' => 'Профиль человека',
                'description' => 'Поля для профиля сотрудника/автора',
                'fields' => array(
                    array('name' => 'photo', 'label' => 'Фото', 'type' => 'image'),
                    array('name' => 'position', 'label' => 'Должность', 'type' => 'text'),
                    array('name' => 'bio', 'label' => 'Биография', 'type' => 'wysiwyg'),
                    array('name' => 'social_links', 'label' => 'Социальные сети', 'type' => 'repeater', 'button_label' => 'Добавить ссылку', 'sub_fields' => array(
                        array('name' => 'network', 'label' => 'Сеть', 'type' => 'select', 'choices' => array('facebook' => 'Facebook', 'twitter' => 'Twitter', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram')),
                        array('name' => 'url', 'label' => 'URL', 'type' => 'url')
                    ))
                )
            ),
            'event_details' => array(
                'name' => 'Детали мероприятия',
                'description' => 'Поля для событий и мероприятий',
                'fields' => array(
                    array('name' => 'event_date', 'label' => 'Дата', 'type' => 'date_picker', 'required' => true),
                    array('name' => 'event_time', 'label' => 'Время', 'type' => 'time_picker'),
                    array('name' => 'location', 'label' => 'Место проведения', 'type' => 'text'),
                    array('name' => 'location_map', 'label' => 'Карта', 'type' => 'google_map'),
                    array('name' => 'registration_link', 'label' => 'Ссылка на регистрацию', 'type' => 'url')
                )
            )
        );
    }
}

