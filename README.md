# Azure Storage Blob Flysystem Adapter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-blob-flysystem.svg)](https://packagist.org/packages/azure-oss/storage-blob-flysystem)
[![Packagist Downloads](https://img.shields.io/packagist/dm/azure-oss/storage-blob-flysystem)](https://packagist.org/packages/azure-oss/storage-blob-flysystem)

> [!IMPORTANT]
> **Issues must be reported in the [monorepo issue tracker](https://github.com/Azure-OSS/azure-storage-monorepo/issues).** Please do not create issues in individual package repositories.

## Minimum Requirements

* PHP 8.1 or above
* [Flysystem](https://flysystem.thephpleague.com/) ^3.28

## Install

Install the package using composer:

```shell
composer require azure-oss/storage-blob-flysystem
```

## Usage

This package provides a Flysystem adapter for Azure Blob Storage. It implements the Flysystem v3 adapter interface, allowing you to use Azure Blob Storage with any library that supports Flysystem.

```php
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter;
use League\Flysystem\Filesystem;

$containerClient = BlobContainerClient::fromConnectionString('<connection string>', 'container-name');
$adapter = new AzureBlobStorageAdapter($containerClient);
$filesystem = new Filesystem($adapter);

// Use the filesystem as you would with any Flysystem adapter
$filesystem->write('path/to/file.txt', 'contents');
$contents = $filesystem->read('path/to/file.txt');
```

## Documentation

For more information about using Flysystem, visit the [Flysystem documentation](https://flysystem.thephpleague.com/docs/).

## Support

Do you need help, do you want to talk to us, or is there anything else?

Join us at:

* [Github Discussions](https://github.com/Azure-OSS/azure-storage-monorepo/discussions)
* [Slack](https://join.slack.com/t/azure-oss/shared_invite/zt-2lw5knpon-mqPM_LIuRZUoH02AY8uiYw)

## License

Azure-Storage-PHP-Adapter-Flysystem is released under the MIT License. See [LICENSE](./LICENSE) for details.

## PHP Version Support Policy

The maintainers of this package add support for a PHP version following its initial release and drop support for a PHP version once it has reached its end of security support.
