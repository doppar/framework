<p align="center">
    <a href="https://doppar.com" target="_blank">
        <img src="https://raw.githubusercontent.com/doppar/doppar/7138fb0e72cd55256769be6947df3ac48c300700/public/logo.png" width="400">
    </a>
</p>

<p align="center">
<a href="https://github.com/doppar/framework/actions/workflows/tests.yml"><img src="https://github.com/doppar/framework/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/doppar/framework"><img src="https://img.shields.io/packagist/dt/doppar/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/doppar/framework"><img src="https://img.shields.io/packagist/v/doppar/framework" alt="Latest Stable Version"></a>
<a href="https://github.com/doppar/framework/blob/main/LICENSE"><img src="https://img.shields.io/github/license/doppar/framework" alt="License"></a>
</p>

## About Doppar
The high-performance, minimalist PHP framework for developers who need raw speed and are willing to build their own application layer

> **Note:** This repository contains the core code of the Doppar framework. If you want to build an application using Doppar, visit the main [Doppar repository](https://github.com/doppar/doppar).

We just put Doppar to the test under some serious load — and the results are in:

## Benchmark Setup
- **Load:** 50,000 requests
- **Concurrency:** 1,000
- **Endpoint:** `/tags` (DB-backed)
- **Tool:** ApacheBench

| Metric              | **Doppar**       | **Laravel**        | **Factor**        |
| ------------------- | ---------------- | ------------------ | ----------------- |
| **Total Requests**  | 50,000           | 50,000             | 1×                |
| **Failed Requests** | 0                | 0                  | 1×                |
| **Requests/sec**    | **318.5 req/s**  | **43.9 req/s**     | \~7.3×            |
| **Median Latency**  | \~2.7s (2703 ms) | \~22.2s (22180 ms) | \~8.2× faster     |
| **95th Percentile** | \~4.8s           | \~34.9s            | \~7.3× faster     |
| **Max Latency**     | \~7.9s           | \~40.2s            | \~5.1× faster     |
| **Response Size**   | 1083 bytes       | 4346 bytes         | \~0.25× (smaller) |

Doppar sustained `~7x higher` throughput than Laravel (318 vs 44 req/s). Doppar is `~8x faster` under 1000 concurrent requests. Doppar stayed under 3s median latency and delivered stable high throughput, making it far more suitable for high-concurrency, database-heavy workloads.

Doppar isn’t just a `new PHP framework` — it outperforms PHP's popular framework by nearly an order of magnitude in concurrency + DB tests.

### 1. Performance & Lightweight Architecture
- **Minimal overhead**: Core stripped of third-party dependencies → lightning-fast performance with minimal bloat.
- **JIT compilation for Blade templates**: Optimizations include:
  - Whitespace reduction
  - Echo consolidation
  - Loop simplification
  - Inline small views
  - Lazy-loading components

### 2. Modern, Modular Design
- Inspired by **Laravel’s syntax** but built on **Symfony’s solid foundation**.
- Encourages **feature-based development structure** → promotes organization and scalability.
- Includes robust features out of the box:
  - Routing
  - Middleware
  - Service container
  - Validation
  - ORM
  - Caching
  - API authentication
  - Rate limiting
  - CLI tooling

### 3. Security and API-readiness
- Built-in security features:
  - CSRF protection
  - Input validation
  - Encryption utilities
  - Header-based authentication
  - Throttling & middleware-driven rate limiting

- Strong **API-first focus**:
  - JSON-first controllers
  - Built-in rate limiting
  - API authentication with **Flarion**
  - Standardized JSON responses

### 4. Extensibility & Package Architecture
- Modular package system with:
  - Routes
  - Migrations
  - Views
  - Service providers
- Improves adaptability, reusability, and scalability.
- Service providers handle setup and bootstrapping → clean separation of concerns, ideal for large/complex applications.

### 5. Production Readiness
- Optimization tools for live environments:
  - Route caching
  - View caching
  - Config caching
- Middleware support for HTTP caching (e.g., **ETags**) → improves client-side performance and reduces server load.

Whether you're a seasoned PHP developer or just diving in, Doppar makes it easy to build powerful applications quickly and cleanly.

## Contributing

Thank you for considering contributing to the Doppar framework! The contribution guide can be found in the [Doppar documentation](https://doppar.com/versions/3.x/contributions.html).

## Code of Conduct

In order to ensure that the Doppar community is welcoming to all, please review and abide by the [Code of Conduct](https://doppar.com/versions/3.x/contributions.html#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/doppar/framework/security/policy) on how to report security vulnerabilities.

## License

The Doppar framework is open-sourced software licensed under the [MIT license](LICENSE.md).