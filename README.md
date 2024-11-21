# Azure Storage Blob PHP Adapter Flysystem

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-blob-flysystem.svg)](https://packagist.org/packages/azure-oss/storage-blob-flysystem)
[![Packagist Downloads](https://img.shields.io/packagist/dm/azure-oss/storage-blob-flysystem)](https://packagist.org/packages/azure-oss/storage-blob-flysystem)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/azure-oss/azure-storage-php-adapter-flysystem/tests.yml?branch=main)](https://github.com/azure-oss/azure-storage-php-adapter-flysystem/actions)

## Minimum Requirements

> [!WARNING]
> Data Lake Storage (Storage Account with hierarchical namespace enabled) is unsupported.

* PHP 8.1 or above
* Required PHP extensions
    * curl
    * json
    * xml

## Install

```shell
composer require azure-oss/storage-blob-flysystem
```

## Quickstart

```php
use AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter;
use AzureOss\Storage\Blob\BlobServiceClient;
use League\Flysystem\Filesystem;

$blobServiceClient = BlobServiceClient::fromConnectionString('<connection-string>');
$containerClient = $blobServiceClient->getContainerClient('quickstart');

$adapter = new AzureBlobStorageAdapter($containerClient, "optional/prefix");
$filesystem = new Filesystem($adapter);

$filesystem->write('hello', 'world!');
```

## Documentation

For more information visit the documentation at [azure-oss.github.io](https://azure-oss.github.io/storage/flysystem/).

## Support

Do you need help, do you want to talk to us, or is there anything else?

Join us at:

* [Github Discussions](https://github.com/Azure-OSS/azure-storage-php/discussions)
* [Slack](https://join.slack.com/t/azure-oss/shared_invite/zt-2lw5knpon-mqPM_LIuRZUoH02AY8uiYw)

## License

Azure-Storage-PHP-Adapter-Flysystem is released under the MIT License. See [LICENSE](./LICENSE) for details.

## PHP Version Support Policy

The maintainers of this package add support for a PHP version following its initial release and drop support for a PHP version once it has reached its end of security support.

## Backward compatibility promise

Azure-Storage-PHP is using Semver. This means that versions are tagged with MAJOR.MINOR.PATCH. Only a new major version will be allowed to break backward compatibility (BC).

Classes marked as @experimental or @internal are not included in our backward compatibility promise. You are also not guaranteed that the value returned from a method is always the same. You are guaranteed that the data type will not change.

PHP 8 introduced named arguments, which increased the cost and reduces flexibility for package maintainers. The names of the arguments for methods in the library are not included in our BC promise.
