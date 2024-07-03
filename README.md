# Flysystem adapter for

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/flysystem-azure-blob-storage?style=flat-square)](https://packagist.org/packages/azure-oss/flysystem-azure-blob-storage)
[![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/flysystem-azure-blob-storage?style=flat-square)](https://packagist.org/packages/azure-oss/flysystem-azure-blob-storage)

## Installation

```bash
composer require azure-oss/flysystem-azure-blob-storage
```

## Notice

It’s important to know this adapter does not fully comply with the adapter contract. The difference(s) is/are:

* Visibility setting or retrieving is not supported.
* Mimetypes are always resolved, where others do not.
* Directory creation is not supported in any way.

## Usage

The connection string can be obtained from the azure portal.

```php
<?php

use AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter;
use AzureOss\Storage\Blob\BlobServiceClient;
use League\Flysystem\Filesystem;

include __DIR__.'/vendor/autoload.php';

$client = BlobServiceClient::fromConnectionString('connectionString')->getContainerClient('container-name')
$adapter = new AzureBlobStorageAdapter(
    $client,
    'container-name',
);
$filesystem = new Filesystem($adapter);
```

## Laravel usage