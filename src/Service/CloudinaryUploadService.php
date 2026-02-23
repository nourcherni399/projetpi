<?php

namespace App\Service;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryUploadService
{
    public function __construct(
        #[Autowire('%env(CLOUDINARY_URL)%')]
        private string $cloudinaryUrl,
    ) {
        Configuration::instance($cloudinaryUrl);
    }

    public function upload(UploadedFile $file, string $folder = 'ressources'): string
    {
        $uploadApi = new UploadApi();
        $mime = $file->getMimeType();
        $resourceType = str_starts_with($mime ?? '', 'video/') ? 'video' : 'audio';

        $result = $uploadApi->upload($file->getRealPath(), [
            'resource_type' => $resourceType,
            'folder' => $folder,
        ]);

        return $result['secure_url'];
    }

    public function uploadFromPath(string $localPath, string $folder = 'ressources'): string
    {
        $uploadApi = new UploadApi();
        $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        $resourceType = in_array($extension, ['mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg', 'm4a'])
            ? (in_array($extension, ['mp4', 'webm', 'mov']) ? 'video' : 'audio')
            : 'auto';

        $result = $uploadApi->upload($localPath, [
            'resource_type' => $resourceType,
            'folder' => $folder,
        ]);

        return $result['secure_url'];
    }

    public function uploadFromUrl(string $fileUrl, string $folder = 'Telechargement'): string
    {
        $uploadApi = new UploadApi();
        $extension = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $resourceType = in_array($extension, ['mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg', 'm4a'])
            ? (in_array($extension, ['mp4', 'webm', 'mov']) ? 'video' : 'audio')
            : 'auto';

        $result = $uploadApi->upload($fileUrl, [
            'resource_type' => $resourceType,
            'folder' => $folder,
        ]);

        return $result['secure_url'];
    }
}
