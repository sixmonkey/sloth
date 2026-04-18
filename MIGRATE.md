# Migration Guide

This document details breaking changes and migration paths for Sloth upgrades.

---

## Unreleased

### $GLOBALS['sloth'] and $GLOBALS['sloth::plugin'] — deprecated

**Manual** — Rector cannot automate this safely.

The global `$GLOBALS['sloth']` and `$GLOBALS['sloth::plugin']` are deprecated. Theme code should use the `app()` helper instead.

**Before:**
```php
$GLOBALS['sloth']->container
$GLOBALS['sloth::plugin']->getContext()
$GLOBALS['sloth::plugin']->getPostTypeClass($postType)
$GLOBALS['sloth::plugin']->getAllModels()
$GLOBALS['sloth::plugin']->getAllTaxonomies()
```

**After:**
```php
app()
app('sloth.context')?->getContext()
app('sloth.models')[$postType] ?? null
app('sloth.models') ?? []
app('sloth.taxonomies') ?? []
```

**Deprecation notices:** Theme code using `$GLOBALS['sloth']` or `$GLOBALS['sloth::plugin']` will now trigger `E_USER_DEPRECATED` notices. These globals still work but will be removed in a future version.