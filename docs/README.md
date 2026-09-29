# Welcome to Zoom Pool Manager (ZPM)

**Zoom Pool Manager (ZPM)** is an enterprise, high-availability platform engineered for higher education institutions, universities, and enterprise organizations. It automates Zoom license utilization, host assignment, approval workflows, cloud recordings, and compliance across pooled Zoom accounts.

```
       ┌────────────────────────────────────────────────────────┐
       │             Zoom Pool Manager (SPA Shell)              │
       │     Modern Vue 3 · Tailwind CSS · Pinia · Lucide       │
       └───────────────────────────┬────────────────────────────┘
                                   │
              ┌────────────────────┴────────────────────┐
              │                                         │
    ┌─────────▼──────────┐                   ┌──────────▼─────────┐
    │  Scheduling Engine │                   │  Governance & RBAC │
    │  • Buffer Enforcer │                   │  • Workflows       │
    │  • Multi-Host Pool │                   │  • Security Rules  │
    │  • Conflict Guard  │                   │  • Approvals & MFA │
    └─────────┬──────────┘                   └──────────┬─────────┘
              │                                         │
              └────────────────────┬────────────────────┘
                                   │
       ┌───────────────────────────▼────────────────────────────┐
       │                 Zoom Integration Layer                 │
       │    • S2S OAuth 2.0        • CRC Webhook Ingestion      │
       │    • Live Debugger        • Cloud Recordings Sync      │
       └────────────────────────────────────────────────────────┘
```

---

## 🌟 Key Capabilities

- **Pooled License Optimization**: Share expensive Zoom Business/Enterprise licenses across hundreds of faculty members and departments without purchasing 1:1 user licenses.
- **Conflict-Free Scheduling & Dynamic Buffers**: Automated conflict avoidance, configurable pre- and post-meeting buffer times, and automatic host rotation.
- **Server-to-Server OAuth 2.0**: Official Zoom API integration with zero user-level OAuth friction.
- **Real-Time Webhook Engine & Live Debugger**: High-throughput intake stream for meeting events with SHA-256 HMAC validation, 1-click CRC challenge tests, and event simulation.
- **Enterprise Governance**: Multi-tier approval workflows, department-level quotas, security profile enforcement (passcodes, waiting rooms, AI companion restrictions), and automated waitlisting.
- **Zero-CLI Deployment**: 100% browser-based graphical installer for shared hosting (Hostinger, cPanel) and Docker environments.
- **Akaunting-Style Safe Updates**: 1-click automated system updates with pre-update database snapshots, zero downtime polling during maintenance, and automatic rollback on failure.

---

## ⚡ Quick Navigation

| Resource | Description | Link |
| :--- | :--- | :--- |
| **Quick Start** | Get up and running in under 5 minutes | [Quick Start Guide](getting-started/quickstart.md) |
| **Shared Hosting** | Deploy on Hostinger, cPanel, or Plesk | [Shared Hosting Guide](installation/shared-hosting.md) |
| **Zoom Setup** | Configure Server-to-Server OAuth in Zoom Marketplace | [Zoom OAuth Setup](zoom/oauth-setup.md) |
| **Webhooks** | Set up intake URLs & test with the Live Debugger | [Webhooks & CRC](zoom/webhooks.md) |
| **API Docs** | REST API endpoints, keys, and OpenAPI spec | [API Overview](api/overview.md) |

---

> [!NOTE]
> Zoom Pool Manager is architected and built by **Senthil Nasa** for mission-critical institutional operations.
