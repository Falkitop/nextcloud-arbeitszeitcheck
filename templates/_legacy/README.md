# Legacy templates (unused)

These PHP layout templates are **not loaded** by any controller or template in the app.
The app uses Nextcloud's standard `TemplateResponse` with `page-start.php` / `page-end.php`
and per-page templates instead.

Moved here for reference only. Safe to delete once confirmed no external tooling depends on them.

| File | Notes |
|------|-------|
| `layout.php` | Old base layout (ProjectCheck-style); superseded by NC app framework |
| `page-wrapper.php` | Duplicate navigation wrapper; never included |
| `header.php` | Orphan header fragment referenced only by `layout.php` |
