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

    /**
     * Options d'upload incluant folder + asset_folder pour compatibilité
     * mode fixe (legacy) et mode dynamique Cloudinary.
     */
    private function getFolderOptions(string $folder): array
    {
        return [
            'folder' => $folder,
            'asset_folder' => $folder,
        ];
    }

    public function uploadFromPath(string $localPath, string $folder = 'ressources'): string
    {
        $uploadApi = new UploadApi();
        $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        $resourceType = in_array($extension, ['mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg', 'm4a'])
            ? (in_array($extension, ['mp4', 'webm', 'mov']) ? 'video' : 'audio')
            : 'auto';

        $options = array_merge([
            'resource_type' => $resourceType,
        ], $this->getFolderOptions($folder));

        $result = $uploadApi->upload($localPath, $options);

        return $result['secure_url'];
    }

    public function uploadFromUrl(string $fileUrl, string $folder = 'Telechargement'): string
    {
        $uploadApi = new UploadApi();
        $path = parse_url($fileUrl, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path ?? '', PATHINFO_EXTENSION));
        $resourceType = in_array($extension, ['mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg', 'm4a'])
            ? (in_array($extension, ['mp4', 'webm', 'mov']) ? 'video' : 'audio')
            : 'auto';

        $options = array_merge([
            'resource_type' => $resourceType,
        ], $this->getFolderOptions($folder));

        $result = $uploadApi->upload($fileUrl, $options);

        return $result['secure_url'];
    }
}