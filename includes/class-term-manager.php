<?php
/**
 * Класс для управления термами таксономий через MCP/REST API
 * 
 * @package ACF_MCP_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACF_MCP_Term_Manager {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Создать терм в таксономии
     */
    public function create_term(array $args): array {
        $taxonomy = $args['taxonomy'] ?? '';
        $name = $args['name'] ?? '';
        $slug = $args['slug'] ?? '';
        $description = $args['description'] ?? '';
        $parent = $args['parent'] ?? 0;
        $acf_fields = $args['acf_fields'] ?? [];

        // Валидация
        if (empty($taxonomy)) {
            return ['success' => false, 'error' => 'Не указана таксономия'];
        }

        if (!taxonomy_exists($taxonomy)) {
            return ['success' => false, 'error' => "Таксономия '{$taxonomy}' не существует"];
        }

        if (empty($name)) {
            return ['success' => false, 'error' => 'Не указано название терма'];
        }

        // Проверка на дубликат
        $existing = term_exists($name, $taxonomy);
        if ($existing) {
            return [
                'success' => false, 
                'error' => "Терм '{$name}' уже существует в таксономии '{$taxonomy}'",
                'existing_term_id' => is_array($existing) ? $existing['term_id'] : $existing
            ];
        }

        // Создание терма
        $term_args = [
            'description' => $description,
            'parent' => (int) $parent
        ];

        if (!empty($slug)) {
            $term_args['slug'] = sanitize_title($slug);
        }

        $result = wp_insert_term($name, $taxonomy, $term_args);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message()
            ];
        }

        $term_id = $result['term_id'];

        // Обновляем ACF поля если переданы
        $acf_result = [];
        if (!empty($acf_fields) && function_exists('update_field')) {
            $acf_post_id = 'term_' . $term_id;
            foreach ($acf_fields as $field_name => $value) {
                $updated = update_field($field_name, $value, $acf_post_id);
                $acf_result[$field_name] = $updated !== false;
            }
        }

        $term = get_term($term_id, $taxonomy);

        return [
            'success' => true,
            'term_id' => $term_id,
            'term_taxonomy_id' => $result['term_taxonomy_id'],
            'name' => $term->name,
            'slug' => $term->slug,
            'taxonomy' => $taxonomy,
            'acf_fields_updated' => $acf_result,
            'message' => "Терм '{$name}' успешно создан"
        ];
    }

    /**
     * Обновить терм
     */
    public function update_term(array $args): array {
        $term_id = $args['term_id'] ?? 0;
        $taxonomy = $args['taxonomy'] ?? '';
        $acf_fields = $args['acf_fields'] ?? [];

        if (empty($term_id)) {
            return ['success' => false, 'error' => 'Не указан term_id'];
        }

        // Получаем терм
        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) {
            return ['success' => false, 'error' => "Терм с ID {$term_id} не найден"];
        }

        $taxonomy = $taxonomy ?: $term->taxonomy;

        // Подготавливаем аргументы для обновления
        $update_args = [];
        
        if (isset($args['name'])) {
            $update_args['name'] = $args['name'];
        }
        if (isset($args['slug'])) {
            $update_args['slug'] = sanitize_title($args['slug']);
        }
        if (isset($args['description'])) {
            $update_args['description'] = $args['description'];
        }
        if (isset($args['parent'])) {
            $update_args['parent'] = (int) $args['parent'];
        }

        // Обновляем терм если есть что обновлять
        if (!empty($update_args)) {
            $result = wp_update_term($term_id, $taxonomy, $update_args);
            
            if (is_wp_error($result)) {
                return [
                    'success' => false,
                    'error' => $result->get_error_message()
                ];
            }
        }

        // Обновляем ACF поля
        $acf_result = [];
        if (!empty($acf_fields) && function_exists('update_field')) {
            $acf_post_id = 'term_' . $term_id;
            foreach ($acf_fields as $field_name => $value) {
                $updated = update_field($field_name, $value, $acf_post_id);
                $acf_result[$field_name] = $updated !== false;
            }
        }

        $updated_term = get_term($term_id, $taxonomy);

        return [
            'success' => true,
            'term_id' => $term_id,
            'name' => $updated_term->name,
            'slug' => $updated_term->slug,
            'taxonomy' => $taxonomy,
            'acf_fields_updated' => $acf_result,
            'message' => "Терм '{$updated_term->name}' успешно обновлен"
        ];
    }

    /**
     * Удалить терм
     */
    public function delete_term(array $args): array {
        $term_id = $args['term_id'] ?? 0;
        $taxonomy = $args['taxonomy'] ?? '';

        if (empty($term_id)) {
            return ['success' => false, 'error' => 'Не указан term_id'];
        }

        // Получаем терм для определения таксономии
        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) {
            return ['success' => false, 'error' => "Терм с ID {$term_id} не найден"];
        }

        $taxonomy = $taxonomy ?: $term->taxonomy;
        $term_name = $term->name;

        $result = wp_delete_term($term_id, $taxonomy);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message()
            ];
        }

        if ($result === false) {
            return [
                'success' => false,
                'error' => 'Не удалось удалить терм'
            ];
        }

        return [
            'success' => true,
            'term_id' => $term_id,
            'taxonomy' => $taxonomy,
            'message' => "Терм '{$term_name}' успешно удален"
        ];
    }

    /**
     * Получить список термов таксономии
     */
    public function list_terms(array $args): array {
        $taxonomy = $args['taxonomy'] ?? '';
        $hide_empty = $args['hide_empty'] ?? false;
        $parent = $args['parent'] ?? null;
        $include_acf = $args['include_acf'] ?? true;
        $per_page = $args['per_page'] ?? 100;
        $page = $args['page'] ?? 1;

        if (empty($taxonomy)) {
            return ['success' => false, 'error' => 'Не указана таксономия'];
        }

        if (!taxonomy_exists($taxonomy)) {
            return ['success' => false, 'error' => "Таксономия '{$taxonomy}' не существует"];
        }

        $term_args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => $hide_empty,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page
        ];

        if ($parent !== null) {
            $term_args['parent'] = (int) $parent;
        }

        $terms = get_terms($term_args);

        if (is_wp_error($terms)) {
            return [
                'success' => false,
                'error' => $terms->get_error_message()
            ];
        }

        // Подсчет общего количества
        $count_args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => $hide_empty,
            'fields' => 'count'
        ];
        if ($parent !== null) {
            $count_args['parent'] = (int) $parent;
        }
        $total = get_terms($count_args);

        $result = [];
        foreach ($terms as $term) {
            $item = [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'parent' => $term->parent,
                'count' => $term->count
            ];

            // Добавляем ACF поля
            if ($include_acf && function_exists('get_fields')) {
                $acf_fields = get_fields('term_' . $term->term_id);
                $item['acf'] = $acf_fields ?: [];
            }

            $result[] = $item;
        }

        return [
            'success' => true,
            'taxonomy' => $taxonomy,
            'terms' => $result,
            'total' => is_numeric($total) ? $total : count($terms),
            'page' => $page,
            'per_page' => $per_page
        ];
    }

    /**
     * Привязать термы к записи
     */
    public function assign_terms(array $args): array {
        $post_id = $args['post_id'] ?? 0;
        $taxonomy = $args['taxonomy'] ?? '';
        $terms = $args['terms'] ?? [];
        $append = $args['append'] ?? false;

        if (empty($post_id)) {
            return ['success' => false, 'error' => 'Не указан post_id'];
        }

        if (empty($taxonomy)) {
            return ['success' => false, 'error' => 'Не указана таксономия'];
        }

        if (!taxonomy_exists($taxonomy)) {
            return ['success' => false, 'error' => "Таксономия '{$taxonomy}' не существует"];
        }

        $post = get_post($post_id);
        if (!$post) {
            return ['success' => false, 'error' => "Запись с ID {$post_id} не найдена"];
        }

        // Нормализуем термы (могут быть ID или slug)
        $term_ids = [];
        foreach ($terms as $term) {
            if (is_numeric($term)) {
                $term_ids[] = (int) $term;
            } else {
                $term_obj = get_term_by('slug', $term, $taxonomy);
                if ($term_obj) {
                    $term_ids[] = $term_obj->term_id;
                }
            }
        }

        $result = wp_set_object_terms($post_id, $term_ids, $taxonomy, $append);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message()
            ];
        }

        // Получаем текущие термы записи
        $current_terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'all']);
        $assigned = array_map(function($t) {
            return [
                'term_id' => $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug
            ];
        }, $current_terms);

        return [
            'success' => true,
            'post_id' => $post_id,
            'taxonomy' => $taxonomy,
            'assigned_terms' => $assigned,
            'message' => sprintf(
                'Привязано %d термов к записи #%d',
                count($assigned),
                $post_id
            )
        ];
    }

    /**
     * Получить термы записи
     */
    public function get_post_terms(array $args): array {
        $post_id = $args['post_id'] ?? 0;
        $taxonomy = $args['taxonomy'] ?? '';
        $include_acf = $args['include_acf'] ?? false;

        if (empty($post_id)) {
            return ['success' => false, 'error' => 'Не указан post_id'];
        }

        $post = get_post($post_id);
        if (!$post) {
            return ['success' => false, 'error' => "Запись с ID {$post_id} не найдена"];
        }

        // Если таксономия не указана - получаем все
        if (empty($taxonomy)) {
            $taxonomies = get_object_taxonomies($post->post_type);
        } else {
            $taxonomies = [$taxonomy];
        }

        $result = [];
        foreach ($taxonomies as $tax) {
            $terms = wp_get_object_terms($post_id, $tax);
            if (is_wp_error($terms)) {
                continue;
            }

            $result[$tax] = array_map(function($term) use ($include_acf) {
                $item = [
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description
                ];

                if ($include_acf && function_exists('get_fields')) {
                    $item['acf'] = get_fields('term_' . $term->term_id) ?: [];
                }

                return $item;
            }, $terms);
        }

        return [
            'success' => true,
            'post_id' => $post_id,
            'terms' => $result
        ];
    }
}

