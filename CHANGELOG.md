# Changelog

## Unreleased

### Changed

- Blob listings now tolerate entries without a last-modified timestamp, as returned for uncommitted blobs.

## 2.1.0

### Added

- Added ETag- and lease-aware writes through the `conditions` write option.
