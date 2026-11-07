# Laravel Stow

Eloquent and browser baskets for saved membership, integer quantities and options. Native database and browser storage backends are independent; the host owns ownership, concurrency, pricing and checkout.

**[Interactive workshop](https://matthewnewport.com/packages/laravel-stow/lab)** — Try guided examples in your browser.

```sh
composer require mrnewport/laravel-stow:^2.1
```

If your Composer registry has not indexed the GitHub tag, add its VCS source and repeat the requirement:

```sh
composer config repositories.laravel-stow vcs https://github.com/MrNewport/laravel-stow
composer require mrnewport/laravel-stow:^2.1
```

Set `stow.morph_key_type` before the first migration when subjects use UUID/ULID keys, then run `php artisan migrate`. The browser module needs no database. See the handbook for both complete quickstarts.

Start with the [handbook](docs/HANDBOOK.md) for fresh Laravel 12/13 installation and the complete feature/operational guide. The [feature index](docs/FEATURES.md) links every public API, return contract, failure mode and executable reference. The [recipes](docs/RECIPES.md) show serious standalone and optional cross-package application use.

Laravel's own PHP runtime requirements apply. Package dependency constraints and current test coverage are described in the handbook. Run `composer test` in a development checkout; optional browser/renderer checks are documented with their requirements. Installing this package does not automatically integrate another package or authorize application records.
