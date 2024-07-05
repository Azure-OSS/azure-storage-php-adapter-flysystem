<?php

declare(strict_types=1);

namespace AzureOss\FlysystemAzureBlobStorage;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use League\Flysystem\ChecksumAlgoIsNotSupported;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
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
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

class AzureBlobStorageAdapter implements FilesystemAdapter, ChecksumProvider, TemporaryUrlGenerator
{
    private readonly MimeTypeDetector $mimeTypeDetector;

    public function __construct(
        private readonly BlobContainerClient $containerClient,
        ?MimeTypeDetector $mimeTypeDetector = null,
    ) {
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->containerClient
                ->getBlobClient($path)
                ->exists();
        } catch(\Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            foreach($this->listContents($path, false) as $ignored) {
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
                ->getBlobClient($path)
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
                ->getBlobClient($path)
                ->downloadStreaming();

            $resource = $result->content->detach();

            if($resource === null) {
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
                ->getBlobClient($path)
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
                        ->getBlobClient($item->path())
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
        throw UnableToSetVisibility::atLocation($path, "Azure does not support this operation.");
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
        $properties = $this->containerClient
            ->getBlobClient($path)
            ->getProperties();

        return $this->normalizeBlob($path, $properties);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $prefix = $path === "" ? null : ltrim($path, "/") . "/";

            if ($deep) {
                foreach ($this->containerClient->getBlobs($prefix) as $item) {
                    yield $this->normalizeBlob($item->name, $item->properties);
                }
            } else {
                foreach ($this->containerClient->getBlobsByHierarchy($prefix) as $item) {
                    if ($item instanceof Blob) {
                        yield $this->normalizeBlob($item->name, $item->properties);
                    } else {
                        yield new DirectoryAttributes($item->name);
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
            $this->delete($source);
        } catch (\Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $sourceBlobClient = $this->containerClient->getBlobClient($source);
            $targetBlobClient = $this->containerClient->getBlobClient($destination);

            $targetBlobClient->copyFromUri($sourceBlobClient->uri);
        } catch (\Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        $sasBuilder = BlobSasBuilder::new()
            ->setExpiresOn($expiresAt)
            ->setPermissions("r");

        $sas = $this->containerClient
            ->getBlobClient($path)
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
                ->getBlobClient($path)
                ->getProperties();

            return bin2hex(base64_decode($properties->contentMD5));
        } catch (\Throwable $e) {
            throw new UnableToProvideChecksum($e->getMessage(), $path, $e);
        }
    }
}
