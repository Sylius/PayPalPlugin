# CONFLICTS

This document explains why certain conflicts were added to `composer.json` and references related issues.

- `symfony/http-client:6.4.49 || 7.4.22 || 8.1.10`:

  These versions make `CurlResponse::__destruct()` abort the transfer. `TraceableResponse::__destruct()` calls it on
  the response it decorates, while the stream returned by `toStream()` still reads from that response. `Psr18Client`
  reads bodies through `toStream()`, so whenever the `http_client` service is traced (debug mode with the profiler,
  or a third-party decorator such as Sentry's), every PayPal API response arrives with the right status and headers
  but an empty body. Onboarding fails and no PayPal payment can be processed.
  The 8.0 line is not affected, as its last release (8.0.16) predates the regression.

  References: https://github.com/symfony/symfony/issues/67653, https://github.com/symfony/symfony/pull/67599, https://github.com/symfony/symfony/pull/67619
