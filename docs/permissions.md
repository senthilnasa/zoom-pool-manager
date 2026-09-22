# Zoom Pool Manager (ZPM) — Roles & Permissions Matrix (Milestone M0.5)

> **Document Status:** Complete (M0.5 Deliverable)  
> **Source Documents:** [`CLAUDE.md`](../CLAUDE.md), [`docs/SPEC.md`](SPEC.md) Part H3

---

## 1. System Roles

ZPM defines 9 distinct roles managed through `spatie/laravel-permission`:

1. **Super Administrator (`super_admin`):** Full system authority. Can configure all settings, manage all departments, access break-glass commands, and review immutable audit logs. Mandatory TOTP required.
2. **IT Administrator (`it_admin`):** Manages Zoom connections, resource pools, infrastructure health, backups, and emergency host recovery.
3. **Meeting Administrator (`meeting_admin`):** Manages all organizational meetings, templates, security profiles, and allocation overrides across all departments.
4. **Department Administrator (`department_admin`):** Manages meetings, quotas, and users scoped strictly to their own assigned department.
5. **Approver (`approver`):** Authorized to review and approve/reject meeting requests assigned to them or their department. Cannot approve their own requests.
6. **Faculty (`faculty`):** Can book classes, meetings, and recurring series; view and manage their own bookings; start meetings as host.
7. **Staff (`staff`):** Can book meetings within department quota constraints; manage own bookings; start meetings as host.
8. **Viewer / Auditor (`auditor`):** Read-only access to meeting lists, resource utilization reports, and audit logs. No booking or administrative authority.
9. **API Client (`api_client`):** Machine-to-machine client accessing `/api/v1` endpoints with granular token scopes.

---

## 2. Granular Permissions & Role Matrix

| Permission Key | Description | Super Admin | IT Admin | Meeting Admin | Dept Admin | Approver | Faculty | Staff | Auditor | API Client |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `meeting.create` | Create a meeting request | ✓ | ✓ | ✓ | ✓ (Dept) | ✓ | ✓ | ✓ | | Scoped |
| `meeting.view` | View own/authorized meetings | ✓ | ✓ | ✓ | ✓ (Dept) | ✓ | ✓ | ✓ | ✓ | Scoped |
| `meeting.view_any` | View all organizational meetings | ✓ | ✓ | ✓ | | | | | ✓ | Scoped |
| `meeting.edit` | Edit meeting details | ✓ | ✓ | ✓ | ✓ (Dept) | | ✓ (Own) | ✓ (Own) | | Scoped |
| `meeting.cancel` | Cancel an upcoming meeting | ✓ | ✓ | ✓ | ✓ (Dept) | | ✓ (Own) | ✓ (Own) | | Scoped |
| `meeting.reschedule` | Reschedule an existing meeting | ✓ | ✓ | ✓ | ✓ (Dept) | | ✓ (Own) | ✓ (Own) | | Scoped |
| `meeting.approve` | Approve/reject a meeting request | ✓ | ✓ | ✓ | ✓ (Dept) | ✓ | | | | |
| `meeting.allocate` | Manually allocate or trigger allocation | ✓ | ✓ | ✓ | | | | | | |
| `meeting.override` | Override allocation, buffer, or policy | ✓ | ✓ | ✓ | | | | | | |
| `meeting.book_on_behalf` | Create meeting on behalf of another user | ✓ | ✓ | ✓ | ✓ (Dept) | | | | | Scoped |
| `meeting.start_as_host` | Retrieve fresh `start_url` at meeting start | ✓ | ✓ | ✓ | | | ✓ (Owner) | ✓ (Owner) | | |
| `recording.view` | View recordings owned or granted | ✓ | ✓ | ✓ | ✓ (Dept) | | ✓ (Own) | ✓ (Own) | ✓ | Scoped |
| `recording.view_any` | View all organizational recordings | ✓ | ✓ | ✓ | | | | | ✓ | Scoped |
| `recording.manage` | Update recording metadata or permissions | ✓ | ✓ | ✓ | | | | | | |
| `recording.download` | Download recording media (V1.1) | ✓ | ✓ | ✓ | | | ✓ (Own) | ✓ (Own) | | Scoped |
| `recording.share` | Generate share links for recordings | ✓ | ✓ | ✓ | ✓ (Dept) | | ✓ (Own) | ✓ (Own) | | Scoped |
| `zoom.view` | View Zoom connections and sync status | ✓ | ✓ | | | | | | ✓ | Scoped |
| `zoom.manage` | Manage credentials, sync, and scopes | ✓ | ✓ | | | | | | | |
| `resource.view` | View Zoom resources, pools, and status | ✓ | ✓ | ✓ | ✓ | ✓ | | | ✓ | Scoped |
| `resource.manage` | Add, edit, or disable Zoom resources | ✓ | ✓ | | | | | | | |
| `pool.manage` | Manage resource pools and membership | ✓ | ✓ | | | | | | | |
| `template.manage` | Create and edit meeting templates | ✓ | ✓ | ✓ | | | | | | |
| `security_profile.manage` | Create and edit security profiles | ✓ | ✓ | ✓ | | | | | | |
| `workflow.view` | View workflow rules and execution logs | ✓ | ✓ | ✓ | ✓ | | | | ✓ | |
| `workflow.manage` | Configure workflow and approval rules | ✓ | ✓ | ✓ | | | | | | |
| `quota.manage` | Adjust user and department quotas | ✓ | ✓ | ✓ | ✓ (Dept) | | | | | |
| `user.view` | View user profiles and directory | ✓ | ✓ | ✓ | ✓ (Dept) | | | | ✓ | Scoped |
| `user.manage` | Create, invite, deactivate users | ✓ | ✓ | | ✓ (Dept) | | | | | |
| `settings.view` | View organization settings | ✓ | ✓ | | | | | | ✓ | |
| `settings.manage` | Edit organization settings and branding | ✓ | | | | | | | | |
| `audit.view` | View immutable audit logs | ✓ | ✓ | | | | | | ✓ | |
| `backup.manage` | Trigger and download database backups | ✓ | ✓ | | | | | | | |
| `api.manage` | Generate, scope, and revoke API keys | ✓ | ✓ | | | | | | | |
| `health.view` | Access `/admin/health` diagnostics | ✓ | ✓ | | | | | | | |
| `emergency.use` | Access emergency panel and host recovery | ✓ | ✓ | | | | | | | |
| `privacy.manage` | Process GDPR/DPDP export/anonymize | ✓ | | | | | | | | |

---

## 3. Scoping & Authorization Rules

### 3.1 Department Scope
When a user possesses a permission marked with `(Dept)`:
- In Eloquent queries, policies automatically apply:
  ```php
  $query->where('department_id', $user->department_id);
  ```
- Department Administrators cannot see, book, or approve meetings outside their department.

### 3.2 Anti-Self-Approval Rule
- **Mandatory Policy Rule:** Under no circumstances may a user approve their own meeting request.
  ```php
  if ($meeting->requester_user_id === $approver->id || $meeting->owner_user_id === $approver->id) {
      return false; // Denied: Anti-self-approval
  }
  ```

### 3.3 Host Launch & Host Key Reveal
- Only the **Meeting Owner**, explicit **delegates**, or an authorized administrator using `emergency.use` can access the JIT `start_url` or reveal the host key.
- All host launches and host key reveals are audited with the requester's IP, timestamp, and context.
- If IT accesses host controls via `emergency.use`, an override record is created and an email alert is immediately dispatched to the meeting owner.
