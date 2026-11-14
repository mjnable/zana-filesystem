# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.21.0 — 2026-11-14

### Security

- `check` warns (`security.public_disk`) about a local disk inside the web root, `public/`, which the web server serves
  file by file, uploads included (FE-68, ADR-033). A disk with a `url` is served on purpose and is not reported. Always
  a warning, also with `security.strict`, since public images are a reason to do it.
- Requires mj-zana/core 2.21.

## 2.19.2 — 2026-11-07

### Security

- `UploadedFileStore` drops extensions a browser runs (`html`, `svg`, `js`, `xml`, …) as well as executable ones,
  and gives a dangerous extension way to the detected type's. `trustedExtensions` keeps some anyway.

## 2.4.0 — 2026-10-26

### Changed

- Factory registrations declare the services they resolve with `uses()`, so the container checks
  their lifetimes at startup (FE-40).
- Requires `mj-zana/core` ^2.4.

## 2.3.0 — 2026-10-26

### Changed

- Tests and tooling (`tests/`, `phpunit.xml.dist`, `phpstan.neon.dist`) are no longer in Composer
  archives, so they stay out of production `vendor/` folders (FE-17).

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
