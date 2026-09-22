# Contributing to Zoom Pool Manager (ZPM)

Thank you for considering contributing to Zoom Pool Manager!

## Project Attribution
- **Author & Maintainer:** Senthil Nasa ([github.com/senthilnasa](https://github.com/senthilnasa))
- **Official Repository:** [github.com/senthilnasa/zoom-pool-manager](https://github.com/senthilnasa/zoom-pool-manager)

## Code Standards & Guidelines

1. **Coding Standards:**
   - Adhere strictly to PSR-12 and Laravel standards.
   - Run Laravel Pint before committing:
     ```bash
     ./vendor/bin/pint
     ```

2. **Static Analysis:**
   - Maintain clean PHPStan Level 6 static typing:
     ```bash
     ./vendor/bin/phpstan analyse
     ```

3. **Testing:**
   - All contributions must pass 100% of Pest feature and unit tests with 0 failures:
     ```bash
     ./vendor/bin/pest
     ```

4. **Architectural Guardrails:**
   - **Strict Single-Organization:** Never add `tenant_id` or `organization_id` columns.
   - **Zero-Stub Policy:** Never commit `TODO` comments, empty dummy methods, or fake responses outside tests and demo mode.
   - **Deadlock Elimination:** Concurrency-safe resource locking must use `SELECT ... FOR UPDATE` ordered strictly by ID.
   - **Host Control Security:** JIT `start_url` and Zoom credentials must never be written to database tables, logs, or unauthenticated API endpoints.
