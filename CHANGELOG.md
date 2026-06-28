# Changelog

## Unreleased

### Changed

- Blob listings now tolerate entries without a last-modified timestamp, as returned for uncommitted blobs.
- The package now requires `azure-oss/storage-blob:^2.2`.

## 2.1.0

### Added

- Added ETag- and lease-aware writes through the `conditions` write option.
