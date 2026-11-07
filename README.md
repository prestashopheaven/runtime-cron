# prestashopheaven/runtime-cron

The cron URL of a PrestaHaven module: a JSON front controller that checks the module's token (never an empty one), runs the job for the shop of the URL and never shows error details.

## Use when

A module offers a cron URL (index.php?fc=module&module=<name>&controller=cron&cron_token=…) per shop for a periodic job, or needs to compare a secret token safely.

## Install

```
composer require prestashopheaven/runtime-cron
```

## Usage

<!-- Examples of the public API. -->

---

Read-only split of [`prestashopheaven/mono-runtime-integration`](https://github.com/prestashopheaven/mono-runtime-integration): changes,
issues and pull requests go there. MIT License.
