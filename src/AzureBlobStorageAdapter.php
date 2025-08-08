<?php

declare(strict_types=1);

namespace AzureOss\FlysystemAzureBlobStorage;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use League\Flysystem\ChecksumAlgoIsNotSupported;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

final class AzureBlobStorageAdapter implements FilesystemAdapter, ChecksumProvider, TemporaryUrlGenerator, PublicUrlGenerator
{
    public const ON_VISIBILITY_THROW_ERROR = 'throw';
    public const ON_VISIBILITY_IGNORE = 'ignore';

    private readonly MimeTypeDetector $mimeTypeDetector;
    private readonly PathPrefixer $prefixer;

    public function __construct(
        private readonly BlobContainerClient $containerClient,
        string $prefix = "",
        ?MimeTypeDetector $mimeTypeDetector = null,
        private readonly string $visibilityHandling = self::ON_VISIBILITY_THROW_ERROR,
        private readonly bool $useDirectPublicUrl = false,
    ) {
        $this->prefixer = new PathPrefixer($prefix);
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->containerClient
                ->getBlobClient($this->prefixer->prefixPath($path))
                ->exists();
        } catch (\Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $options = new GetBlobsOptions(pageSize: 1);

            foreach (
                $this->containerClient->getBlobs(
                    $this->prefixer->prefixDirectoryPath($path),
                    $options,
                ) as $ignored
            ) {
                return true;
            };

            return false;
        } catch (\Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }

    /**
     * @param string|resource $contents
     */
    private function upload(string $path, $contents): void
    {
        try {
            $path = $this->prefixer->prefixPath($path);
            $mimetype = $this->mimeTypeDetector->detectMimetype($path, $contents);

            $options = new UploadBlobOptions(
                contentType: $mimetype,
            );

            $this->containerClient
                ->getBlobClient($path)
                ->upload($contents, $options);
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, previous: $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $result = $this->containerClient
                ->getBlobClient($this->prefixer->prefixPath($path))
                ->downloadStreaming();

            return $result->content->getContents();
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, previous: $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $result = $this->containerClient
                ->getBlobClient($this->prefixer->prefixPath($path))
                ->downloadStreaming();

            $resource = $result->content->detach();

            if ($resource === null) {
                throw new \Exception("Should not happen");
            }

            return $resource;
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, previous: $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->containerClient
                ->getBlobClient($this->prefixer->prefixPath($path))
                ->deleteIfExists();
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, previous: $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            foreach ($this->listContents($path, true) as $item) {
                if ($item instanceof FileAttributes) {
                    $this->containerClient
                        ->getBlobClient($this->prefixer->prefixPath($item->path()))
                        ->delete();
                }
            }
        } catch (\Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, previous: $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Azure does not support this operation.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if ($this->visibilityHandling === self::ON_VISIBILITY_THROW_ERROR) {
            throw UnableToSetVisibility::atLocation($path, 'Azure does not support this operation.');
        }
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, "Azure does not support this operation.");
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            return $this->fetchMetadata($path);
        } catch (\Throwable $e) {
            throw UnableToRetrieveMetadata::mimeType($path, previous: $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            return $this->fetchMetadata($path);
        } catch (\Throwable $e) {
            throw UnableToRetrieveMetadata::lastModified($path, previous: $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            return $this->fetchMetadata($path);
        } catch (\Throwable $e) {
            throw UnableToRetrieveMetadata::lastModified($path, previous: $e);
        }
    }

    private function fetchMetadata(string $path): FileAttributes
    {
        $path = $this->prefixer->prefixPath($path);

        $properties = $this->containerClient
            ->getBlobClient($path)
            ->getProperties();

        return $this->normalizeBlob($path, $properties);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $prefix = $this->prefixer->prefixDirectoryPath($path);

            if ($deep) {
                foreach ($this->containerClient->getBlobs($prefix) as $item) {
                    yield $this->normalizeBlob($this->prefixer->stripPrefix($item->name), $item->properties);
                }
            } else {
                foreach ($this->containerClient->getBlobsByHierarchy($prefix) as $item) {
                    if ($item instanceof Blob) {
                        yield $this->normalizeBlob($this->prefixer->stripPrefix($item->name), $item->properties);
                    } else {
                        yield new DirectoryAttributes($this->prefixer->stripPrefix($item->name));
                    }
                }
            }
        } catch (\Throwable $e) {
            throw UnableToListContents::atLocation($path, $deep, $e);
        }
    }

    private function normalizeBlob(string $name, BlobProperties $properties): FileAttributes
    {
        return new FileAttributes(
            $name,
            fileSize: $properties->contentLength,
            lastModified: $properties->lastModified->getTimestamp(),
            mimeType: $properties->contentType,
        );
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            if ($source !== $destination) {
                $this->delete($source);
            }
        } catch (\Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $sourceBlobClient = $this->containerClient->getBlobClient($this->prefixer->prefixPath($source));
            $targetBlobClient = $this->containerClient->getBlobClient($this->prefixer->prefixPath($destination));

            $targetBlobClient->copyFromUri($sourceBlobClient->uri);
        } catch (\Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * @description If useDirectPublicUrl is true, returns the direct public URL.
     * Otherwise, Azure doesn't support permanent URLs, so we create one that lasts 1000 years.
     */
    public function publicUrl(string $path, Config $config): string
    {
        if ($this->useDirectPublicUrl) {
            $blobClient = $this->containerClient->getBlobClient($this->prefixer->prefixPath($path));
            return (string) $blobClient->uri;
        }

        return $this->temporaryUrl($path, (new \DateTimeImmutable())->modify("+1000 years"), $config);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        $permissions = $config->get("permissions", "r");
        if (!is_string($permissions)) {
            throw new \InvalidArgumentException("permissions must be a string!");
        }

        $sasBuilder = BlobSasBuilder::new()
            ->setExpiresOn($expiresAt)
            ->setPermissions($permissions);

        $sas = $this->containerClient
            ->getBlobClient($this->prefixer->prefixPath($path))
            ->generateSasUri($sasBuilder);

        return (string) $sas;
    }

    public function checksum(string $path, Config $config): string
    {
        $algo = $config->get('checksum_algo', 'md5');

        if ($algo !== 'md5') {
            throw new ChecksumAlgoIsNotSupported();
        }

        try {
            $properties = $this->containerClient
                ->getBlobClient($this->prefixer->prefixPath($path))
                ->getProperties();
        } catch (\Throwable $e) {
            throw new UnableToProvideChecksum($e->getMessage(), $path, $e);
        }

        $md5 = $properties->contentMD5;
        if ($md5 === null) {
            throw new UnableToProvideChecksum(reason: 'File does not have a checksum set in Azure', path: $path);
        }
        return $md5;
    }
}
