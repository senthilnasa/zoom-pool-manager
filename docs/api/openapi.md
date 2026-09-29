# OpenAPI Specification & Interactive Docs

Zoom Pool Manager generates full OpenAPI 3.1 specifications dynamically from the codebase using Dedoc Scramble.

---

## 📖 Accessing Interactive API Documentation

Authorized administrators and developers can access the interactive Swagger/Stoplight documentation portal:

```text
https://zoom.yourdomain.com/docs/api
```

- **Interactive Playground**: Test endpoints directly from the browser by supplying your Bearer API key.
- **Strict Schema Definitions**: Every request body, response structure, and validation constraint is documented with example JSON payloads.

---

## 📥 Raw OpenAPI JSON

You can download the raw OpenAPI specification for code generation (e.g. generating Python, TypeScript, or Go SDKs):

```text
GET https://zoom.yourdomain.com/docs/api.json
```
