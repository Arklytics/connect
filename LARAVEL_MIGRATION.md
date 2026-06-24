# Laravel Migration Notes

This project now has Laravel-style routes, controllers, middleware, and Blade templates, but Laravel is not installed in `composer.json` yet.

## Route Groups

- Master admin: `/master`
- Business panel: `/business`

## Session Keys

- Master admin session key: `master_id`
- Business session key: `biz_id`

## Middleware Aliases

When Laravel is installed, register these middleware aliases:

```php
'master.auth' => \App\Http\Middleware\EnsureMasterAuthenticated::class,
'business.auth' => \App\Http\Middleware\EnsureBusinessAuthenticated::class,
```

In Laravel 11+, add them in `bootstrap/app.php`. In older Laravel versions, add them in `app/Http/Kernel.php`.

## Existing Plain PHP

The current working plain PHP pages remain in:

- `master/`
- `website/`

The new Blade views are in:

- `resources/views/admin`
- `resources/views/business`
