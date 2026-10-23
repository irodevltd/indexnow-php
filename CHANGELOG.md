# Changelog

## 1.0.0 – 2026-10-23

First release, extracted from the IndexNow integration that runs on [irodev.net](https://irodev.net).

- `Client`: sends URLs to the shared IndexNow endpoint, drops other hosts' URLs and duplicates, explains each answer.
- `Sync`: sends what changed in your sitemap since the last accepted notice (new, re-dated and removed URLs), one
  notice per URL per 10 minutes, an hour's wait after a refusal, a lock against double sends.
- `Sitemap`: reads `<urlset>` entries and `<sitemapindex>` children; a broken sitemap never looks like removed pages.
- `Deferred`: runs the sync after the response, at most once per interval, so it works without cron.
- `Key`, `JsonLog`, `CurlTransport`.
