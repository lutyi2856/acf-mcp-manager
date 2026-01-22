<?php
/**
 * Класс для управления значениями ACF полей через MCP/REST API
 * Поддерживает записи, термы, пользователей и страницы опций
 * 
 * @package ACF_MCP_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACF_MCP_Values_Manager {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Обновить ACF поля для объекта
     * 
     * @param array $args {
     *     @type mixed  $post_id  ID записи, 'term_XX', 'user_XX' или 'option'/'options'
     *     @type array  $fields   Массив полей [field_name => value]
     * }
     * @return array Результат операции
     */
    public function update_fields(array $args): array {
        if (!function_exists('update_field')) {
            return [
                'success' => false,
                'error' => 'ACF Pro не активен'
            ];
        }

        $post_id = $args['post_id'] ?? null;
        $fields = $args['fields'] ?? [];

        if (empty($post_id)) {
            return [
                'success' => false,
                'error' => 'Не указан post_id'
            ];
        }

        if (empty($fields) || !is_array($fields)) {
            return [
                'success' => false,
                'error' => 'Не указаны поля для обновления'
            ];
        }

        // Нормализация post_id для ACF
        $acf_post_id = $this->normalize_post_id($post_id);
        
        // Проверка существования объекта
        $validation = $this->validate_target($acf_post_id);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        $updated = [];
        $errors = [];

        foreach ($fields as $field_name => $value) {
            // Получаем field object для определения типа и key
            $field_object = $this->get_field_object_by_name($field_name, $acf_post_id);
            
            // Для flexible_content и repeater используем field key
            $field_key_or_name = $field_name;
            if ($field_object && in_array($field_object['type'], ['flexible_content', 'repeater', 'group'])) {
                $field_key_or_name = $field_object['key'];
            }
            
            $result = update_field($field_key_or_name, $value, $acf_post_id);
            
            if ($result !== false) {
                $updated[] = $field_name;
            } else {
                // Попробуем через meta напрямую как fallback
                $meta_result = $this->update_meta_fallback($acf_post_id, $field_name, $value);
                if ($meta_result) {
                    $updated[] = $field_name;
                } else {
                    $errors[] = $field_name;
                }
            }
        }

        return [
            'success' => count($errors) === 0,
            'post_id' => $acf_post_id,
            'updated_fields' => $updated,
            'failed_fields' => $errors,
            'message' => sprintf(
                'Обновлено %d из %d полей',
                count($updated),
                count($fields)
            )
        ];
    }

    /**
     * Получить значения ACF полей объекта
     */
    public function get_fields(array $args): array {
        if (!function_exists('get_fields') || !function_exists('get_field')) {
            return [
                'success' => false,
                'error' => 'ACF Pro не активен'
            ];
        }

        $post_id = $args['post_id'] ?? null;
        $field_names = $args['fields'] ?? null; // null = все поля

        if (empty($post_id)) {
            return [
                'success' => false,
                'error' => 'Не указан post_id'
            ];
        }

        $acf_post_id = $this->normalize_post_id($post_id);
        
        $validation = $this->validate_target($acf_post_id);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Получаем все поля или конкретные
        if (empty($field_names)) {
            $fields = get_fields($acf_post_id);
            $fields = $fields ?: [];
        } else {
            $fields = [];
            $field_names = is_array($field_names) ? $field_names : [$field_names];
            foreach ($field_names as $name) {
                $fields[$name] = get_field($name, $acf_post_id);
            }
        }

        return [
            'success' => true,
            'post_id' => $acf_post_id,
            'fields' => $fields,
            'count' => count($fields)
        ];
    }

    /**
     * Удалить значение ACF поля
     */
    public function delete_field(array $args): array {
        if (!function_exists('delete_field')) {
            return [
                'success' => false,
                'error' => 'ACF Pro не активен'
            ];
        }

        $post_id = $args['post_id'] ?? null;
        $field_name = $args['field_name'] ?? null;

        if (empty($post_id) || empty($field_name)) {
            return [
                'success' => false,
                'error' => 'Не указаны post_id или field_name'
            ];
        }

        $acf_post_id = $this->normalize_post_id($post_id);
        
        $result = delete_field($field_name, $acf_post_id);

        return [
            'success' => $result !== false,
            'post_id' => $acf_post_id,
            'field_name' => $field_name,
            'message' => $result !== false 
                ? "Поле '{$field_name}' успешно очищено" 
                : "Не удалось очистить поле '{$field_name}'"
        ];
    }

    /**
     * Нормализация post_id для ACF
     * ACF принимает: int (post), 'term_XX', 'user_XX', 'option', 'options'
     */
    private function normalize_post_id($post_id) {
        // Если это число - возвращаем как int
        if (is_numeric($post_id)) {
            return (int) $post_id;
        }

        // Если строка 'option' или 'options'
        if (in_array($post_id, ['option', 'options'], true)) {
            return 'option';
        }

        // Если формат term_XX или user_XX - возвращаем как есть
        if (preg_match('/^(term|user)_\d+$/', $post_id)) {
            return $post_id;
        }

        // Если формат taxonomy:term_id
        if (preg_match('/^(\w+):(\d+)$/', $post_id, $matches)) {
            return 'term_' . $matches[2];
        }

        return $post_id;
    }

    /**
     * Проверка существования целевого объекта
     */
    private function validate_target($acf_post_id): array {
        // Опции всегда валидны
        if ($acf_post_id === 'option') {
            return ['valid' => true];
        }

        // Проверка терма
        if (is_string($acf_post_id) && strpos($acf_post_id, 'term_') === 0) {
            $term_id = (int) str_replace('term_', '', $acf_post_id);
            $term = get_term($term_id);
            if (!$term || is_wp_error($term)) {
                return [
                    'valid' => false,
                    'error' => "Терм с ID {$term_id} не найден"
                ];
            }
            return ['valid' => true, 'object' => $term];
        }

        // Проверка пользователя
        if (is_string($acf_post_id) && strpos($acf_post_id, 'user_') === 0) {
            $user_id = (int) str_replace('user_', '', $acf_post_id);
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return [
                    'valid' => false,
                    'error' => "Пользователь с ID {$user_id} не найден"
                ];
            }
            return ['valid' => true, 'object' => $user];
        }

        // Проверка записи
        if (is_numeric($acf_post_id)) {
            $post = get_post($acf_post_id);
            if (!$post) {
                return [
                    'valid' => false,
                    'error' => "Запись с ID {$acf_post_id} не найдена"
                ];
            }
            return ['valid' => true, 'object' => $post];
        }

        return [
            'valid' => false,
            'error' => "Неизвестный формат post_id: {$acf_post_id}"
        ];
    }

    /**
     * Получить field object по имени поля
     * Ищет поле в группах полей, привязанных к объекту
     */
    private function get_field_object_by_name($field_name, $post_id) {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return null;
        }

        // Кэш для field objects
        static $cache = [];
        $cache_key = $post_id . '_' . $field_name;
        
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        // Получаем группы полей для объекта
        $groups = $this->get_field_groups_for_post($post_id);
        
        foreach ($groups as $group) {
            $fields = acf_get_fields($group['key']);
            if (!$fields) continue;
            
            foreach ($fields as $field) {
                if ($field['name'] === $field_name) {
                    $cache[$cache_key] = $field;
                    return $field;
                }
            }
        }

        $cache[$cache_key] = null;
        return null;
    }

    /**
     * Получить группы полей для конкретного поста
     */
    private function get_field_groups_for_post($post_id) {
        if (!function_exists('acf_get_field_groups')) {
            return [];
        }

        $args = [];
        
        if ($post_id === 'option') {
            $args['options_page'] = true;
        } elseif (is_numeric($post_id)) {
            $post = get_post($post_id);
            if ($post) {
                $args['post_type'] = $post->post_type;
                $args['post_id'] = $post_id;
            }
        }

        return acf_get_field_groups($args);
    }

    /**
     * Fallback обновление через WordPress meta API
     */
    private function update_meta_fallback($post_id, $field_name, $value): bool {
        if ($post_id === 'option') {
            return update_option("options_{$field_name}", $value);
        }

        if (is_string($post_id) && strpos($post_id, 'term_') === 0) {
            $term_id = (int) str_replace('term_', '', $post_id);
            return update_term_meta($term_id, $field_name, $value) !== false;
        }

        if (is_string($post_id) && strpos($post_id, 'user_') === 0) {
            $user_id = (int) str_replace('user_', '', $post_id);
            return update_user_meta($user_id, $field_name, $value) !== false;
        }

        if (is_numeric($post_id)) {
            return update_post_meta($post_id, $field_name, $value) !== false;
        }

        return false;
    }

    /**
     * Получить информацию о группах полей для объекта
     */
    public function get_field_groups_for_object($post_id): array {
        if (!function_exists('acf_get_field_groups')) {
            return [];
        }

        $acf_post_id = $this->normalize_post_id($post_id);
        
        // Определяем параметры для получения групп
        $args = [];
        
        if ($acf_post_id === 'option') {
            $args['options_page'] = true;
        } elseif (is_numeric($acf_post_id)) {
            $post = get_post($acf_post_id);
            if ($post) {
                $args['post_type'] = $post->post_type;
                $args['post_id'] = $acf_post_id;
            }
        }

        $groups = acf_get_field_groups($args);
        
        $result = [];
        foreach ($groups as $group) {
            $fields = acf_get_fields($group['key']);
            $result[] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'fields' => array_map(function($f) {
                    return [
                        'key' => $f['key'],
                        'name' => $f['name'],
                        'label' => $f['label'],
                        'type' => $f['type'],
                        'required' => !empty($f['required'])
                    ];
                }, $fields ?: [])
            ];
        }

        return $result;
    }
}

