<?php

declare(strict_types=1);

use AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter;

if (! class_exists('AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter', false) && class_exists(AzureBlobStorageAdapter::class)) {
    class_alias(AzureBlobStorageAdapter::class, 'AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter');
}
