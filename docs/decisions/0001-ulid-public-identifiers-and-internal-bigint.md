# 0001 — Dual Identifier Strategy: Internal BIGINT and Public ULID

- Status: accepted
- Date: 2026-09-22
- Milestone: M0.5

## Context
External URLs, API routes, and webhook payloads require identifiers that do not leak sequential business metrics (such as daily meeting volumes or user counts) and prevent ID enumeration attacks. However, pure UUIDv4 identifiers degrade B-tree indexing and MySQL join performance due to index fragmentation.

## Decision
Adopt a dual identifier architecture for all core entities:
1. `id` BIGINT UNSIGNED AUTO_INCREMENT as the primary key internally for fast foreign keys, index clustering, and table joins.
2. `public_id` CHAR(26) storing ULIDs (Universally Unique Lexicographically Sortable Identifiers) exposed in all external URLs, JSON responses, route-model bindings, and API keys.

## Alternatives Considered
- **Pure Auto-Increment IDs:** Leaks volume data and susceptible to enumeration vulnerabilities. Rejected.
- **Pure UUIDv4 as Primary Key:** Degrades InnoDB insert and join throughput on large datasets. Rejected.
- **UUIDv7:** Viable, but ULID provides a clean, URL-safe 26-character base32 encoding with millisecond sorting.

## Consequences
- Eloquent models must use ULID for route-model binding (`getRouteKeyName() => 'public_id'`).
- Internal database relationships remain compact and fast 8-byte integers.
