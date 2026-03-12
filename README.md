# Azure Storage Blob Flysystem Adapter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-blob-flysystem.svg)](https://packagist.org/packages/azure-oss/storage-blob-flysystem)
[![Packagist Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-flysystem)](https://packagist.org/packages/azure-oss/storage-blob-flysystem)

Community-driven PHP SDKs for Azure, because Microsoft won't.

In November 2023, Microsoft officially archived their [Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped maintaining PHP integrations for most Azure services. No migration path, no replacement — just a repository marked read-only.

We picked up where they left off.

<img src="https://azure-oss.github.io/img/logo.svg" width="150" alt="Screenshot">

## Install

```shell
composer require azure-oss/storage-blob-flysystem`.
```

## Documentation

You can read the documentation [here](https://azure-oss.github.io/category/storage-blob-flysystem).

## Quickstart

```php
<?php

use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter;
use League\Flysystem\Filesystem;

$service = BlobServiceClient::fromConnectionString(
    getenv('AZURE_STORAGE_CONNECTION_STRING')
);

$container = $service->getContainerClient(
    getenv('AZURE_STORAGE_CONTAINER')
);

$adapter = new AzureBlobStorageAdapter($container);
$filesystem = new Filesystem($adapter);

// Write
$filesystem->write('docs/hello.txt', 'Hello Azure Blob + Flysystem');

// Read
$contents = $filesystem->read('docs/hello.txt');

// Stream upload
$stream = fopen('/path/to/big-file.zip', 'r');
$filesystem->writeStream('archives/big-file.zip', $stream);
fclose($stream);

// List recursively
foreach ($filesystem->listContents('docs', true) as $item) {
    echo $item->path().PHP_EOL;
}

// Delete
$filesystem->delete('docs/hello.txt');
```

## License

This project is released under the MIT License. See [LICENSE](https://github.com/Azure-OSS/azure-storage-php-monorepo/blob/02759360186be8d2d04bd1e9b2aba3839b6d39dc/LICENSE) for details.