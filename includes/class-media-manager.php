<?php
/**
 * Класс для управления медиафайлами через MCP/REST API
 * Загрузка изображений по URL и привязка к ACF полям
 * 
 * @package ACF_MCP_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACF_MCP_Media_Manager {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Загрузить медиафайл по URL
     * 
     * @param array $args {
     *     @type string $url           URL файла для загрузки
     *     @type string $title         Заголовок медиафайла (опционально)
     *     @type string $alt           Alt текст для изображения (опционально)
     *     @type string $caption       Подпись (опционально)
     *     @type string $description   Описание (опционально)
     *     @type int    $post_id       ID записи для привязки (опционально)
     *     @type string $acf_field     Имя ACF поля для автоматической установки (опционально)
     * }
     * @return array Результат операции
     */
    public function upload_from_url(array $args): array {
        $url = $args['url'] ?? '';
        $title = $args['title'] ?? '';
        $alt = $args['alt'] ?? '';
        $caption = $args['caption'] ?? '';
        $description = $args['description'] ?? '';
        $post_id = $args['post_id'] ?? 0;
        $acf_field = $args['acf_field'] ?? '';

        // Валидация URL
        if (empty($url)) {
            return [
                'success' => false,
                'error' => 'Не указан URL файла'
            ];
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'Некорректный URL'
            ];
        }

        // Проверяем что это изображение (по расширению)
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
        $parsed_url = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($parsed_url, PATHINFO_EXTENSION));
        
        // Убираем query string из расширения если есть
        if (strpos($extension, '?') !== false) {
            $extension = substr($extension, 0, strpos($extension, '?'));
        }

        // Требуется для работы с медиафайлами
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Загружаем файл во временную директорию
        $temp_file = download_url($url, 60);

        if (is_wp_error($temp_file)) {
            return [
                'success' => false,
                'error' => 'Ошибка загрузки файла: ' . $temp_file->get_error_message()
            ];
        }

        // Определяем имя файла
        $filename = basename($parsed_url);
        if (empty($filename) || strpos($filename, '.') === false) {
            // Генерируем имя если не удалось извлечь
            $filename = 'uploaded-' . time() . '.' . ($extension ?: 'jpg');
        }

        // Подготавливаем данные для sideload
        $file_array = [
            'name' => sanitize_file_name($filename),
            'tmp_name' => $temp_file
        ];

        // Загружаем в медиатеку
        $attachment_id = media_handle_sideload($file_array, $post_id ?: 0);

        // Удаляем временный файл если sideload не удалось
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return [
                'success' => false,
                'error' => 'Ошибка добавления в медиатеку: ' . $attachment_id->get_error_message()
            ];
        }

        // Обновляем метаданные
        $update_data = [];
        
        if (!empty($title)) {
            $update_data['post_title'] = $title;
        }
        
        if (!empty($caption)) {
            $update_data['post_excerpt'] = $caption;
        }
        
        if (!empty($description)) {
            $update_data['post_content'] = $description;
        }

        if (!empty($update_data)) {
            $update_data['ID'] = $attachment_id;
            wp_update_post($update_data);
        }

        // Устанавливаем alt текст
        if (!empty($alt)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }

        // Получаем информацию о загруженном файле
        $attachment = get_post($attachment_id);
        $attachment_url = wp_get_attachment_url($attachment_id);
        $attachment_metadata = wp_get_attachment_metadata($attachment_id);

        $result = [
            'success' => true,
            'attachment_id' => $attachment_id,
            'url' => $attachment_url,
            'title' => $attachment->post_title,
            'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'mime_type' => $attachment->post_mime_type,
            'width' => $attachment_metadata['width'] ?? null,
            'height' => $attachment_metadata['height'] ?? null,
            'filesize' => filesize(get_attached_file($attachment_id)),
            'message' => 'Файл успешно загружен'
        ];

        // Если указано ACF поле - устанавливаем значение
        if (!empty($acf_field) && !empty($post_id) && function_exists('update_field')) {
            $acf_result = update_field($acf_field, $attachment_id, $post_id);
            $result['acf_field_updated'] = $acf_result !== false;
            $result['acf_field'] = $acf_field;
            $result['target_post_id'] = $post_id;
            
            if ($acf_result !== false) {
                $result['message'] .= " и установлен в поле '{$acf_field}' записи #{$post_id}";
            }
        }

        return $result;
    }

    /**
     * Загрузить несколько файлов по URL
     */
    public function upload_multiple_from_urls(array $args): array {
        $urls = $args['urls'] ?? [];
        $post_id = $args['post_id'] ?? 0;
        $acf_field = $args['acf_field'] ?? ''; // Для gallery поля
        $default_alt = $args['default_alt'] ?? '';

        if (empty($urls) || !is_array($urls)) {
            return [
                'success' => false,
                'error' => 'Не указан массив URL файлов'
            ];
        }

        $uploaded = [];
        $failed = [];
        $attachment_ids = [];

        foreach ($urls as $index => $url_data) {
            // Поддержка простого массива URL или массива объектов
            if (is_string($url_data)) {
                $url_args = [
                    'url' => $url_data,
                    'alt' => $default_alt,
                    'post_id' => $post_id
                ];
            } else {
                $url_args = array_merge([
                    'alt' => $default_alt,
                    'post_id' => $post_id
                ], $url_data);
            }

            $result = $this->upload_from_url($url_args);

            if ($result['success']) {
                $uploaded[] = [
                    'index' => $index,
                    'attachment_id' => $result['attachment_id'],
                    'url' => $result['url']
                ];
                $attachment_ids[] = $result['attachment_id'];
            } else {
                $failed[] = [
                    'index' => $index,
                    'url' => is_string($url_data) ? $url_data : ($url_data['url'] ?? 'unknown'),
                    'error' => $result['error']
                ];
            }
        }

        $response = [
            'success' => count($failed) === 0,
            'uploaded_count' => count($uploaded),
            'failed_count' => count($failed),
            'uploaded' => $uploaded,
            'failed' => $failed,
            'attachment_ids' => $attachment_ids
        ];

        // Если указано ACF gallery поле - устанавливаем значения
        if (!empty($acf_field) && !empty($post_id) && !empty($attachment_ids) && function_exists('update_field')) {
            $acf_result = update_field($acf_field, $attachment_ids, $post_id);
            $response['acf_field_updated'] = $acf_result !== false;
            $response['acf_field'] = $acf_field;
        }

        return $response;
    }

    /**
     * Установить изображение записи (featured image)
     */
    public function set_featured_image(array $args): array {
        $post_id = $args['post_id'] ?? 0;
        $attachment_id = $args['attachment_id'] ?? 0;
        $url = $args['url'] ?? '';

        if (empty($post_id)) {
            return ['success' => false, 'error' => 'Не указан post_id'];
        }

        $post = get_post($post_id);
        if (!$post) {
            return ['success' => false, 'error' => "Запись #{$post_id} не найдена"];
        }

        // Если передан URL - сначала загружаем
        if (!empty($url) && empty($attachment_id)) {
            $upload_result = $this->upload_from_url([
                'url' => $url,
                'post_id' => $post_id,
                'title' => $args['title'] ?? '',
                'alt' => $args['alt'] ?? ''
            ]);

            if (!$upload_result['success']) {
                return $upload_result;
            }

            $attachment_id = $upload_result['attachment_id'];
        }

        if (empty($attachment_id)) {
            return ['success' => false, 'error' => 'Не указан attachment_id или url'];
        }

        // Устанавливаем featured image
        $result = set_post_thumbnail($post_id, $attachment_id);

        if (!$result) {
            return [
                'success' => false,
                'error' => 'Не удалось установить изображение записи'
            ];
        }

        return [
            'success' => true,
            'post_id' => $post_id,
            'attachment_id' => $attachment_id,
            'thumbnail_url' => get_the_post_thumbnail_url($post_id, 'full'),
            'message' => "Изображение записи #{$post_id} успешно установлено"
        ];
    }

    /**
     * Получить список медиафайлов
     */
    public function list_media(array $args): array {
        $per_page = $args['per_page'] ?? 20;
        $page = $args['page'] ?? 1;
        $mime_type = $args['mime_type'] ?? 'image';
        $search = $args['search'] ?? '';
        $post_parent = $args['post_parent'] ?? null;

        $query_args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        if (!empty($mime_type)) {
            $query_args['post_mime_type'] = $mime_type;
        }

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        if ($post_parent !== null) {
            $query_args['post_parent'] = (int) $post_parent;
        }

        $query = new WP_Query($query_args);
        
        $media = [];
        foreach ($query->posts as $attachment) {
            $metadata = wp_get_attachment_metadata($attachment->ID);
            $media[] = [
                'id' => $attachment->ID,
                'title' => $attachment->post_title,
                'url' => wp_get_attachment_url($attachment->ID),
                'alt' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                'mime_type' => $attachment->post_mime_type,
                'width' => $metadata['width'] ?? null,
                'height' => $metadata['height'] ?? null,
                'date' => $attachment->post_date,
                'sizes' => isset($metadata['sizes']) ? array_keys($metadata['sizes']) : []
            ];
        }

        return [
            'success' => true,
            'media' => $media,
            'total' => $query->found_posts,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $query->max_num_pages
        ];
    }
}

