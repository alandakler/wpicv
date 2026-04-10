<?php
if (!defined('ABSPATH')) { 
        exit;
    }

/*
 * Plugin Name: WordPress Image Converter - WPICV
 * Description: Автоматически конвертирует PNG/JPG изображения в WebP при загрузке (поддержка Imagick и GD).
 * Version: 1.0.1 beta
 * Author: Sergey Muzharovsky
 * URL: https://wpicv.muzharovsky.com
 */

/* Конвертация изображения в WebP */
function awc_convert_image_to_webp($src_path, $mime) {
    // Проверка поддержки WebP в GD
    if (!class_exists('Imagick') && !function_exists('imagewebp')) {
        return false;
    }

    $dst_path = preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $src_path);
    $dst_path = sanitize_file_name(basename($dst_path)); // Безопасное имя
    $dst_path = dirname($src_path) . '/' . $dst_path;

    // Попытка Imagick
    if (class_exists('Imagick')) {
        try {
            $image = new Imagick($src_path);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(85);
            $image->writeImage($dst_path);
            $image->clear();
            $image->destroy();
            return $dst_path;
        } catch (Exception $e) {
            // фолбэк на GD
        }
    }

    // Фолбэк на GD
    $img = false;
    if ($mime === 'image/png') {
        $img = @imagecreatefrompng($src_path);
    } elseif ($mime === 'image/jpeg') {
        $img = @imagecreatefromjpeg($src_path);
    }

    if ($img) {
        imagepalettetotruecolor($img);
        if (imagewebp($img, $dst_path, 85)) {
            imagedestroy($img);
            return $dst_path;
        }
        imagedestroy($img);
    }

    return false;
}

/* Конвертация основного файла */
function awc_handle_upload_to_webp($file) {
    $type = $file['type'];
    $path = $file['file'];

    if (!in_array($type, ['image/png', 'image/jpeg'])) {
        return $file;
    }

    $webp_path = awc_convert_image_to_webp($path, $type);
    if (!$webp_path) return $file;

    unlink($path); // удаляем оригинал

    $file['file'] = $webp_path;
    $file['url']  = preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $file['url']);
    $file['type'] = 'image/webp';

    return $file;
}
add_filter('wp_handle_upload', 'awc_handle_upload_to_webp');


/**
 * Конвертация миниатюр
 */
function awc_convert_thumbs_to_webp($metadata, $attachment_id) {
    $upload_dir = wp_upload_dir();

    if (empty($metadata['sizes'])) return $metadata;

    foreach ($metadata['sizes'] as $size => $data) {
        $file_path = $upload_dir['basedir'] . '/' . $data['file'];

        if (!file_exists($file_path)) continue;

        $info = getimagesize($file_path);
        if (!$info) continue;

        $mime = $info['mime'];

        if (!in_array($mime, ['image/png', 'image/jpeg'])) continue;

        $webp_path = awc_convert_image_to_webp($file_path, $mime);

        if ($webp_path) {
            unlink($file_path);

            $metadata['sizes'][$size]['file'] =
                preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $data['file']);
        }
    }

    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'awc_convert_thumbs_to_webp', 10, 2);
