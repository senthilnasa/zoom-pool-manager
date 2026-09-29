# API Keys & Token Scopes

Manage access tokens for external systems and microservices in **API Keys & Tokens** (`/app/api/keys`).

---

## 🔑 Key Structure & Security

- Generated keys use high-entropy cryptographic strings prefixed with `zpm_live_` or `zpm_test_`.
- The raw secret token is displayed **only once** upon creation. ZPM stores a SHA-256 hash in the database, ensuring keys cannot be retrieved if the database is inspected.

---

## 🎯 Granular Scopes

When creating an API key, administrators limit its authority to specific scopes:

| Scope | Allowed Operations |
| :--- | :--- |
| `meetings:read` | List, query, and inspect meetings and attendance records. |
| `meetings:write` | Schedule, update, and cancel meetings. |
| `pools:read` | Query pool availability, license capacities, and schedules. |
| `recordings:read` | Access cloud recording metadata and generate signed playback tokens. |
| `webhooks:manage` | Register and manage outbound webhook subscriptions. |

---

## ⏱️ Rate Limiting & Revocation

- Keys can be assigned custom rate limits (e.g. 100 requests per minute) to protect against DDoS or misconfigured polling scripts.
- Keys can be instantly disabled or revoked with immediate effect.
