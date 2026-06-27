# Upgrade Guide

## Upgrading from 1.x to 2.x

Version 2.0 drops support for PHP 8.1. Applications must run PHP 8.2 or newer before upgrading.

Update your Composer constraint:

```shell
composer require azure-oss/storage-blob-flysystem:^2.0
```

This release also requires `azure-oss/storage-blob:^2.0`. Composer will update that dependency automatically unless your application requires `azure-oss/storage-blob` directly, in which case update that constraint too.

### Removed class aliases

The backwards-compatible Flysystem adapter alias has been removed. Import `AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter` directly instead of `AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter`.
