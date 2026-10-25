# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.0.0 — 2026-10-26

The final 2.0 release. No changes since 2.0.0-rc.1.

## 2.0.0-rc.1 — 2026-10-25

### Security

- `UploadedFileStore` drops extensions a web server could execute (`php`, `phtml`, `phar`, `cgi`,
  `.htaccess`, …) instead of keeping them on the stored file.
- `UploadedFileStore` checks `$maxBytes` against the bytes received, not only the size the upload
  reports.

## 2.0.0-alpha.3 — 2026-10-24

First release of the 2.0 line. Storage disks with one API, safe paths, and storage of uploaded files. Requires PHP 8.5 or later.

Documentation: [the filesystem guide](https://github.com/mjnable/zana-docs/blob/main/guides/filesystem.md).
