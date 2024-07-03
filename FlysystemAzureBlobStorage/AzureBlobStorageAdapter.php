<?php

declare(strict_types=1);

namespace AzureOss\FlysystemAzureBlobStorage;

use AzureOss\Storage\Blob\ContainerClient;
use AzureOss\Storage\Blob\Options\ListBlobsOptions;
use AzureOss\Storage\Blob\Options\UploadBlockBlobOptions;
use AzureOss\Storage\Common\SAS\BlobSASPermission;
use AzureOss\Storage\Common\SAS\BlobSASSignatureValues;
use AzureOss\Storage\Common\SAS\SasIpRange;
use AzureOss\Storage\Common\SAS\SasProtocol;
use AzureOss\Storage\Common\SAS\SharedAccessSignatureHelper;
use DateTimeInterface;
use League\Flysystem\CalculateChecksumFromStream;
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
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

class AzureBlobStorageAdapter implements FilesystemAdapter, ChecksumProvider, PublicUrlGenerator, TemporaryUrlGenerator
{
    use CalculateChecksumFromStream;

    private readonly MimeTypeDetector $mimeTypeDetector;

    public function __construct(
        private readonly ContainerClient $containerClient,
        ?MimeTypeDetector                $mimeTypeDetector = null,
    ) {
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->containerClient->getBlobClient($path)->exists();
        } catch(\Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $options = new ListBlobsOptions(
                prefix: $this->getPrefix($path),
                maxResults: 1,
                delimiter: "/"
            );

            $response = $this->containerClient->listBlobs($options);

            return count($response->blobs) > 0;
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

            $options = new UploadBlockBlobOptions(
                contentType: $mimetype,
            );

            $this->containerClient->getBlockBlobClient($path)->upload($contents, $options);
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, previous: $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $response = $this->containerClient->getBlobClient($path)->get();

            return $response->content->getContents();
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, previous: $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $response = $this->containerClient->getBlobClient($path)->get();
            $resource = $response->content->detach();

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
            $this->containerClient->getBlobClient($path)->deleteIfExists();
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, previous: $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            foreach ($this->listContents($path, true) as $item) {
                if ($item instanceof FileAttributes) {
                    $this->containerClient->getBlobClient($item->path())->delete();
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
        $response = $this->containerClient->getBlobClient($path)->getProperties();

        return new FileAttributes(
            $path,
            fileSize: $response->contentLength,
            lastModified: $response->lastModified->getTimestamp(),
            mimeType: $response->contentType
        );
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            do {
                $nextMarker = "";

                $options = new ListBlobsOptions(
                    prefix: $this->getPrefix($path),
                    marker: $nextMarker,
                    delimiter: $deep ? null : "/",
                );

                $response = $this->containerClient->listBlobs($options);

                foreach($response->blobPrefixes as $blobPrefix) {
                    yield new DirectoryAttributes($blobPrefix->name);
                }

                foreach ($response->blobs as $blob) {
                    yield new FileAttributes(
                        $blob->name,
                        fileSize: $blob->properties->contentLength,
                        lastModified: $blob->properties->lastModified->getTimestamp(),
                        mimeType: $blob->properties->contentType,
                    );
                }

                $nextMarker = $response->nextMarker;
            } while ($nextMarker !== "");
        } catch (\Throwable $e) {
            throw UnableToListContents::atLocation($path, $deep, new \Exception());
        }
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
            $this->containerClient->getBlobClient($source)->copy($this->containerClient->containerName, $destination);
        } catch (\Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    private function getPrefix(string $path): ?string
    {
        return $path === "" ? null : rtrim($path, "/") . "/";
    }

    public function checksum(string $path, Config $config): string
    {
        return $this->calculateChecksumFromStream($path, $config);
    }

    public function publicUrl(string $path, Config $config): string
    {
        $url = $this->containerClient->getBlobClient($path)->getUrl();

        $sasValues = new BlobSASSignatureValues(
            $path,
            $config->get("cache_control"),
            $config->get("container_name"),
            $config->get("content_disposition"),
            $config->get("content_encoding"),
            $config->get("content_language"),
            $config->get("content_type"),
            $config->get("correlation_id"),
            $config->get("encryption_scope"),
            $config->get("expires_on"),
            $config->get("identifier"),
            $config->get("ip_range"),
            [BlobSASPermission::READ],
            $config->get("preauthorized_agent_object_id"),
            $config->get("protocol"),
            $config->get("snapshot_time"),
            $config->get("starts_on"),
            $config->get("version"),
            $config->get("version_id"),
        );

        $sas = SharedAccessSignatureHelper::generateBlobSASQueryParameters(
            $sasValues,
            $this->containerClient->sharedKeyCredentials,
        );

        return sprintf("%s?%s", $url, $sas);
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, Config $config): string
    {
        $config->withSetting("expires_on", $expiresAt);

        return $this->publicUrl($path, $config);
    }
}
