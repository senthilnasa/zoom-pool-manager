/**
 * Module Instruction Guides for Zoom Pool Manager
 * Provides user-friendly, step-by-step guidance for every module in the application.
 */

export const moduleGuides = {
  // 1. Core Scheduling
  'dashboard': {
    title: 'Dashboard Overview',
    category: 'Core Scheduling',
    summary: 'Central mission control displaying real-time Zoom pooled resource metrics, upcoming meetings, pool utilization, and diagnostic heartbeats.',
    whoCanUse: 'All Authenticated Users (Faculty, Staff, Approvers, Administrators)',
    steps: [
      { step: 1, title: 'Inspect Utilization', desc: 'Check the real-time license utilization gauge to see how many host accounts are currently occupied.' },
      { step: 2, title: 'Track Today’s Meetings', desc: 'Review the active and upcoming meeting schedules for your department and the institution.' },
      { step: 3, title: 'Quick Action Shortcuts', desc: 'Use the "Book Meeting" button to immediately jump to the scheduling wizard or check pending approvals.' },
    ],
    keyFeatures: [
      { name: 'Live Metrics Cards', desc: 'Displays counts for meetings today, active sessions, total licenses, and pending approvals.' },
      { name: 'Pool Utilization Bar', desc: 'Visual capacity gauge indicating current concurrent load across all Zoom pools.' },
      { name: 'Recent Activity Feed', desc: 'Chronological timeline of meeting reservations and status transitions.' },
    ],
    tips: [
      'If you see high pool utilization during peak hours (e.g. >80%), consider booking during alternative time slots or using auto-select pool.',
      'Clicking on any meeting row in the feed takes you directly to its full management view.',
    ],
  },

  'meetings.index': {
    title: 'Scheduled Meetings Directory',
    category: 'Core Scheduling',
    summary: 'View, filter, manage, and start all Zoom pooled meetings. Reveal host keys during active windows and initiate emergency rotations.',
    whoCanUse: 'Faculty, Staff, Meeting Admins, IT Admins, Super Admins',
    steps: [
      { step: 1, title: 'Filter & Search', desc: 'Filter meetings by status (Scheduled, Started, Ended, Cancelled) or search by title, faculty name, or meeting ID.' },
      { step: 2, title: 'Host Start Window', desc: 'When within 15 minutes of the start time, the "Start as Host" button becomes active for the meeting owner.' },
      { step: 3, title: 'Reveal Host Key', desc: 'If starting via the native Zoom desktop app, click "Reveal Host Key" to claim host privileges inside Zoom.' },
      { step: 4, title: 'Cancel or Reschedule', desc: 'Cancel a meeting early to immediately liberate the Zoom pooled host license for colleagues.' },
    ],
    keyFeatures: [
      { name: 'Host Key Security', desc: 'Host keys are only visible to the designated meeting owner during the active meeting window.' },
      { name: 'Automatic Host Rotation', desc: 'Every host key is automatically rotated upon meeting completion to prevent unauthorized reuse.' },
      { name: 'ICS Calendar Download', desc: 'Download standard RFC-5545 .ics calendar files to import meetings into Outlook, Google Calendar, or Apple Calendar.' },
    ],
    tips: [
      'Canceling an unneeded meeting instantly credits back department quotas and allocates waiting queue requests.',
      'Admins can filter by department to oversee specific faculty scheduling.',
    ],
  },

  'meetings.create': {
    title: 'Book a Meeting Wizard',
    category: 'Core Scheduling',
    summary: 'Reserve a pooled Zoom host account with automatic conflict detection, security policies, and optional delegated scheduling on behalf of colleagues.',
    whoCanUse: 'Faculty, Staff, Meeting Admins, Super Admins',
    steps: [
      { step: 1, title: 'Enter Meeting Info', desc: 'Provide a title, agenda/description, and select the exact start and end times.' },
      { step: 2, title: 'Choose Archetype & Pool', desc: 'Select an institutional template (Lecture, Exam, Webinar) and preferred pool or let ZPM auto-select.' },
      { step: 3, title: 'Book on Behalf (Admins)', desc: 'If scheduling for faculty from an email request, toggle "Book on behalf of another user" and pick the professor.' },
      { step: 4, title: 'Live Conflict Preview', desc: 'Watch the real-time indicator to ensure pooled licenses are available without overlaps.' },
      { step: 5, title: 'Confirm Reservation', desc: 'Submit the form. If approved automatically by workflow rules, the Zoom join link and passcode generate instantly.' },
    ],
    keyFeatures: [
      { name: 'Delegated Scheduling', desc: 'Admins can schedule meetings on behalf of any faculty member, attributing the meeting to their schedule.' },
      { name: 'Conflict Prevention Engine', desc: 'Live availability checker detects blackout periods, advance notice violations, and capacity mismatches.' },
      { name: 'Security Baseline Auto-Apply', desc: 'Passcodes, waiting rooms, and recording settings apply automatically based on institutional policy.' },
    ],
    tips: [
      'Always enter an estimated participant count to ensure you are allocated a resource with sufficient license capacity (e.g. 100, 300, or 500 seats).',
      'If all licenses in a pool are booked, your request will automatically be given the option to join the Waitlist.',
    ],
  },

  'calendar': {
    title: 'Resource Calendar View',
    category: 'Core Scheduling',
    summary: 'Visual timeline and interactive calendar displaying scheduled Zoom pooled sessions and resource occupancy across all days and time slots.',
    whoCanUse: 'All Authenticated Users',
    steps: [
      { step: 1, title: 'Select Date', desc: 'Use the date picker or previous/next arrows to navigate between days and weeks.' },
      { step: 2, title: 'Inspect Time Slots', desc: 'View time blocks to check resource congestion and identify available booking windows.' },
      { step: 3, title: 'Inspect Meeting Details', desc: 'Click any colored meeting block to inspect its title, host, assigned resource, and status.' },
    ],
    keyFeatures: [
      { name: 'Color-Coded Statuses', desc: 'Instant visual differentiation between Scheduled, Active/Started, and Ended sessions.' },
      { name: 'Resource Allocation Timeline', desc: 'Shows exact start, end, and 15-minute buffer windows between consecutive bookings.' },
    ],
    tips: [
      'Look for green or open time blocks when planning high-capacity seminars or institutional webinars.',
    ],
  },

  'series.index': {
    title: 'Recurring Meeting Series',
    category: 'Core Scheduling',
    summary: 'Manage multi-session recurring classes, semester lecture series, and recurring committee meetings with independent occurrence management.',
    whoCanUse: 'Faculty, Staff, Meeting Admins, Super Admins',
    steps: [
      { step: 1, title: 'Review Series List', desc: 'Browse all active series, recurrence patterns (Weekly, Bi-weekly, Monthly), and total occurrences.' },
      { step: 2, title: 'Occurrence Inspection', desc: 'Expand a series to view each individual meeting date and its allocated Zoom resource.' },
      { step: 3, title: 'Detach Single Occurrence', desc: 'Modify or reschedule a single date without breaking the remaining recurring series.' },
      { step: 4, title: 'Series Cancellation', desc: 'Cancel future occurrences in bulk to release holds and quota reservations.' },
    ],
    keyFeatures: [
      { name: 'Recurrence Pattern Engine', desc: 'Automates expansion while respecting institutional blackout dates and holidays.' },
      { name: 'Single-Resource Locking', desc: 'Ensures recurring university classes retain the exact same Zoom meeting ID and join link across sessions when configured.' },
    ],
    tips: [
      'Blackout windows (such as semester breaks or exam freezes) are automatically skipped during series creation.',
    ],
  },

  // 2. Governance & Workflows
  'approvals.index': {
    title: 'Workflow Approvals Queue',
    category: 'Governance & Workflows',
    summary: 'Departmental and administrative review queue for high-capacity, external, or policy-restricted Zoom meeting requests.',
    whoCanUse: 'Department Administrators, Approvers, IT Admins, Super Admins',
    steps: [
      { step: 1, title: 'Review Pending Requests', desc: 'Review meeting details, requester, participant count, and requested security profile.' },
      { step: 2, title: 'Anti-Self-Approval', desc: 'Users cannot approve their own requests; another authorized department approver must sign off.' },
      { step: 3, title: 'Approve or Reject', desc: 'Click "Approve" to immediately allocate a Zoom host license, or "Reject" with a mandatory explanation note.' },
    ],
    keyFeatures: [
      { name: 'Departmental Scoping', desc: 'Department admins only see and act upon requests originating within their designated academic unit.' },
      { name: 'Delegation Support', desc: 'Colleagues with active delegation permissions can approve requests on behalf of absent managers.' },
    ],
    tips: [
      'Check the attendee count and external participants flag before approving large event licenses.',
    ],
  },

  'workflows.index': {
    title: 'Workflow Rules Builder & Simulation',
    category: 'Governance & Workflows',
    summary: 'Design conditional automation rules that automatically approve, require multi-step approvals, assign security profiles, or reject meeting requests.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Rule Evaluation Order', desc: 'Rules evaluate in order of priority (lowest priority number runs first).' },
      { step: 2, title: 'Define Conditions', desc: 'Match by department, participant count threshold, after-hours time, or external participants.' },
      { step: 3, title: 'Set Actions', desc: 'Choose Auto-Approve, Require Department Head Approval, Require IT Approval, or Assign Profile.' },
      { step: 4, title: 'Test in Simulation Sandbox', desc: 'Use the built-in simulator to test synthetic meeting payloads against active rules before deploying.' },
    ],
    keyFeatures: [
      { name: 'Sandbox Simulator', desc: 'Validate whether a rule will trigger without modifying live production meetings.' },
      { name: 'Multi-Step Chains', desc: 'Chain multiple approvers or sequential sign-offs for high-profile institutional webinars.' },
    ],
    tips: [
      'Keep general auto-approval rules at lower priority and place strict restrictions (e.g. >200 participants) at higher priority.',
    ],
  },

  'quotas.index': {
    title: 'Quota Policies Management',
    category: 'Governance & Workflows',
    summary: 'Monitor and enforce monthly meeting counts and hour quotas per user and per department to guarantee fair resource sharing.',
    whoCanUse: 'Department Administrators, IT Admins, Super Admins',
    steps: [
      { step: 1, title: 'Inspect Quota Meters', desc: 'View consumption progress bars showing hours and meetings booked against monthly caps.' },
      { step: 2, title: 'Create Quota Policy', desc: 'Set monthly maximum meetings and total hours for specific departments or high-frequency users.' },
      { step: 3, title: 'Enforce or Bypass', desc: 'Privileged IT admins automatically bypass quota limits during emergency institutional events.' },
    ],
    keyFeatures: [
      { name: 'Automated Monthly Reset', desc: 'Quotas reset on the 1st of each calendar month.' },
      { name: 'Release on Cancellation', desc: 'Canceling a future meeting immediately credits hours and bookings back to the quota balance.' },
    ],
    tips: [
      'Department admins should review quota consumption near month-end to ensure faculty have sufficient booking capacity.',
    ],
  },

  'delegations.index': {
    title: 'Approval Delegations',
    category: 'Governance & Workflows',
    summary: 'Temporarily delegate your meeting approval authority to a trusted colleague while traveling, on sabbatical, or on leave.',
    whoCanUse: 'Approvers, Department Administrators, Super Admins',
    steps: [
      { step: 1, title: 'Select Delegate', desc: 'Choose an active colleague from your department to grant sign-off permissions.' },
      { step: 2, title: 'Set Effective Dates', desc: 'Define start and end dates for when the delegation begins and automatically expires.' },
      { step: 3, title: 'Add Justification', desc: 'Provide an audit note explaining the leave or temporary coverage reason.' },
    ],
    keyFeatures: [
      { name: 'Automatic Expiration', desc: 'Delegations automatically terminate once the end date is reached.' },
      { name: 'Audit Attribution', desc: 'Decisions signed off by a delegate clearly display "Approved on behalf of [Name]".' },
    ],
    tips: [
      'You can revoke an active delegation at any time with a single click before its expiration date.',
    ],
  },

  'waitlist.index': {
    title: 'Meeting Waitlist Queue',
    category: 'Governance & Workflows',
    summary: 'Queue of meeting requests submitted when all pooled Zoom licenses were fully occupied during the requested time slot.',
    whoCanUse: 'Faculty, Staff, Approvers, Administrators',
    steps: [
      { step: 1, title: 'Queue Positioning', desc: 'View priority rank and submission timestamps for overbooked requests.' },
      { step: 2, title: 'Auto-Allocation', desc: 'When any conflicting meeting is canceled, ZPM automatically promotes the first eligible waitlisted meeting.' },
      { step: 3, title: 'Manual Promotion (Admin)', desc: 'Admins can promote high-priority requests or cancel abandoned queue items.' },
    ],
    keyFeatures: [
      { name: 'Automatic Slot Promotion', desc: 'No manual re-booking needed; liberated resources are automatically assigned to the queue.' },
      { name: 'Real-time Notifications', desc: 'Requesters receive instant notification when their waitlisted meeting is approved and scheduled.' },
    ],
    tips: [
      'If your meeting is urgent, coordinate with IT admins who can check for alternative license pools.',
    ],
  },

  // 3. Institutional Scheduling Config
  'templates.index': {
    title: 'Meeting Templates',
    category: 'Institutional Archetypes',
    summary: 'Standardized meeting blueprints (Faculty Classes, Exams, Webinars, Department Syncs) with preconfigured durations, security settings, and pools.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Browse Archetypes', desc: 'Review existing templates and see which security profiles and duration limits are bound to each.' },
      { step: 2, title: 'Create / Edit Template', desc: 'Define default title patterns, max durations, default participant limits, and whether auto-approval applies.' },
      { step: 3, title: 'Activate / Deactivate', desc: 'Toggle templates active or inactive based on semester requirements.' },
    ],
    keyFeatures: [
      { name: 'Default Security Pairing', desc: 'Enforces appropriate security postures (e.g. Exams mandate Waiting Rooms and no participant rename).' },
      { name: 'Streamlined Booking', desc: 'Reduces faculty booking errors by automatically configuring 10+ Zoom settings in one click.' },
    ],
    tips: [
      'Assign an appropriate template for high-stakes online examinations to guarantee cheating countermeasures are active.',
    ],
  },

  'security-profiles.index': {
    title: 'Security Profiles',
    category: 'Institutional Archetypes',
    summary: 'Security baselines mapped directly to Zoom API parameters: Waiting Rooms, Passcodes, Join Before Host, and AI Companion recording policies.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Inspect Security Postures', desc: 'View settings for Standard, Confidential, Exam, and Public Webinar profiles.' },
      { step: 2, title: 'Configure Safeguards', desc: 'Enforce mandatory passcodes, lock Waiting Rooms, restrict to authenticated university emails, or disable participant video/screenshare.' },
      { step: 3, title: 'Set AI Companion Policy', desc: 'Configure whether Zoom AI Companion summaries and transcripts are allowed, disabled, or admin-only.' },
    ],
    keyFeatures: [
      { name: 'Zoom API Field Mapping', desc: 'Every checkbox maps to verified Zoom v2 meeting settings.' },
      { name: 'Anti-Zoombombing', desc: 'Enforces domain restrictions (@univ.edu) and prevents unauthenticated guest access.' },
    ],
    tips: [
      'For sensitive faculty tenure meetings or confidential committee sessions, always use the Confidential profile.',
    ],
  },

  'blackouts.index': {
    title: 'Blackout Windows & Freeze Periods',
    category: 'Scheduling Constraints',
    summary: 'Institutional holiday calendars, exam freeze periods, and maintenance windows where booking pooled resources is blocked or restricted.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'View Calendar Freezes', desc: 'Review upcoming campus holidays, final exam blocks, and scheduled Zoom maintenance.' },
      { step: 2, title: 'Create Blackout Period', desc: 'Define start date/time, end date/time, reason, and scope (entire university or specific department).' },
      { step: 3, title: 'Scope Enforcement', desc: 'Booking attempts overlapping a blackout period are automatically blocked with a clear notice.' },
    ],
    keyFeatures: [
      { name: 'Department Scoping', desc: 'Block resources for specific schools or faculties without impacting the rest of the campus.' },
      { name: 'Recurring Series Skipping', desc: 'Semester recurring series automatically skip dates that collide with defined blackout holidays.' },
    ],
    tips: [
      'Set up semester break and graduation freeze dates ahead of time so bookings cannot be made in error.',
    ],
  },

  'policies.index': {
    title: 'Booking Policies & Lead Times',
    category: 'Scheduling Constraints',
    summary: 'Institutional scheduling rules controlling minimum advance notice, maximum booking horizon, duration caps, and buffer windows.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Inspect Policy Rules', desc: 'Review global and departmental constraints on booking timeframes.' },
      { step: 2, title: 'Adjust Notice Windows', desc: 'Set minimum notice (e.g. 2 hours before start) to prevent last-minute automated allocation conflicts.' },
      { step: 3, title: 'Set Advance Horizon', desc: 'Limit how far in advance faculty can book (e.g. max 60 or 90 days).' },
    ],
    keyFeatures: [
      { name: 'Session Buffer Times', desc: 'Guarantees 10–60 minute rest buffers between meetings on the same Zoom host account.' },
      { name: 'Duration Caps', desc: 'Prevents single reservations from monopolizing licenses for excessive hours.' },
    ],
    tips: [
      'Session buffers ensure host accounts have time to finish cloud recording uploads before the next session begins.',
    ],
  },

  // 4. Operations & Diagnostics
  'operations.health': {
    title: 'System Health & Diagnostics',
    category: 'Operations & Diagnostics',
    summary: 'Live infrastructure telemetry monitoring database latency, Redis cache, queue worker heartbeats, and on-demand self-tests.',
    whoCanUse: 'IT Administrators, Super Administrators, Auditors',
    steps: [
      { step: 1, title: 'Inspect Health Gauges', desc: 'Verify that Database, Cache, Queue Workers, and Zoom API connectivity show green OK status.' },
      { step: 2, title: 'Run Diagnostic Tests', desc: 'Click "Run Self-Test" to trigger live ping tests against Zoom OAuth servers and mail transports.' },
      { step: 3, title: 'Scheduler Telemetry', desc: 'Confirm that the background scheduler heartbeat ran within the last 5 minutes.' },
    ],
    keyFeatures: [
      { name: 'Heartbeat Monitor', desc: 'Detects stalled cron jobs or crashed background queue workers.' },
      { name: 'Real-time Latency Check', desc: 'Measures database read/write speeds and network response times.' },
    ],
    tips: [
      'If the queue worker status turns amber or red, inspect your background supervisord or systemd workers.',
    ],
  },

  'operations.alerts': {
    title: 'Operational Alerts & Anomalies',
    category: 'Operations & Diagnostics',
    summary: 'System incident alerts detecting webhook delivery failures, scheduler stalls, license exhaustion, and API rate-limiting.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Filter Active Alerts', desc: 'Review open anomalies grouped by severity (Critical, Warning, Info).' },
      { step: 2, title: 'Inspect Incident Details', desc: 'Click an alert to examine error stack traces, timestamps, and impacted resources.' },
      { step: 3, title: 'Resolve Alert', desc: 'Once rectified, mark the alert as "Resolved" with an audit resolution note.' },
    ],
    keyFeatures: [
      { name: 'Smart Deduplication', desc: 'Repeated occurrences of the same issue update the count rather than flooding the inbox.' },
      { name: 'Severity Badges', desc: 'Instant prioritization of urgent outages versus routine warnings.' },
    ],
    tips: [
      'Configure outbound webhook or email notifications to receive critical alerts directly on Slack, Teams, or email.',
    ],
  },

  'operations.backups': {
    title: 'System & Database Backups',
    category: 'Operations & Diagnostics',
    summary: 'Create, inspect, and download verified database snapshot archives to safeguard institutional meeting data and audit histories.',
    whoCanUse: 'Super Administrators',
    steps: [
      { step: 1, title: 'Inspect Backup History', desc: 'View archive files, file sizes, creation timestamps, and verification SHA-256 checksums.' },
      { step: 2, title: 'Create On-Demand Backup', desc: 'Click "Create Snapshot Now" to immediately trigger a verified database dump.' },
      { step: 3, title: 'Download Archive', desc: 'Securely download the archive to off-site storage for disaster recovery readiness.' },
    ],
    keyFeatures: [
      { name: 'Integrity Verification', desc: 'Every backup is validated for database consistency and checksum integrity upon creation.' },
      { name: 'Storage Retention', desc: 'Old backup snapshots are rotated according to configured retention periods.' },
    ],
    tips: [
      'Always take an on-demand backup before performing system upgrades or major pool reconfigurations.',
    ],
  },

  'operations.emergency': {
    title: 'Emergency IT Override',
    category: 'Operations & Diagnostics',
    summary: 'Break-glass administrative panel to force reallocate active meetings to different Zoom resources or emergency force-cancel with mandatory audited reasons.',
    whoCanUse: 'Super Administrators, IT Administrators (Strictly Audited)',
    steps: [
      { step: 1, title: 'Locate Problem Meeting', desc: 'Find the active or stuck meeting using the search or filter.' },
      { step: 2, title: 'Select Action', desc: 'Choose "Force Reallocate" (move to healthy host) or "Force Cancel" (terminate immediately).' },
      { step: 3, title: 'Mandatory Justification', desc: 'Provide an emergency reason (e.g. "Host compromised" or "License suspended by Zoom").' },
      { step: 4, title: 'Confirm Override', desc: 'The action is executed instantly and recorded to the immutable cryptographic audit trail.' },
    ],
    keyFeatures: [
      { name: 'Mandatory Reason Enforcement', desc: 'Cannot be submitted without a detailed explanation; empty reasons are rejected.' },
      { name: 'Immutable Audit Log', desc: 'Break-glass actions are highlighted with critical priority in compliance audits.' },
    ],
    tips: [
      'Use Force Reallocate if a Zoom host account is experiencing technical issues during an active institutional exam or seminar.',
    ],
  },

  'audit.index': {
    title: 'Cryptographic Audit Trail',
    category: 'Operations & Diagnostics',
    summary: 'Tamper-evident, append-only cryptographic audit ledger tracking all user logins, meeting bookings, host key reveals, approvals, and system changes.',
    whoCanUse: 'Super Administrators, IT Administrators, Auditors',
    steps: [
      { step: 1, title: 'Browse Events', desc: 'Filter audit records by event category (Auth, Meeting, Host Key, Policy, Admin) or actor.' },
      { step: 2, title: 'Verify Hash Chain', desc: 'Click "Verify Cryptographic Chain" to validate SHA-256 block linkages across all historical entries.' },
      { step: 3, title: 'Inspect Change Diffs', desc: 'Open any record to see exact before/after field changes, IP addresses, and user agents.' },
    ],
    keyFeatures: [
      { name: 'SHA-256 Merkle Linkage', desc: 'Each entry embeds the cryptographic hash of the prior entry, making historical tampering mathematically detectable.' },
      { name: 'Immutable Storage', desc: 'Database triggers and application rules strictly forbid UPDATE and DELETE queries on audit rows.' },
    ],
    tips: [
      'Export the audit ledger for annual FERPA, GDPR, or internal cybersecurity compliance audits.',
    ],
  },

  'privacy.index': {
    title: 'Privacy & Data Retention (GDPR/FERPA)',
    category: 'Operations & Diagnostics',
    summary: 'Compliance toolkit for handling Data Subject Access Requests (DSAR), user account anonymization, and automated data retention log purges.',
    whoCanUse: 'Super Administrators, Data Protection Officers',
    steps: [
      { step: 1, title: 'Export User Data (DSAR)', desc: 'Generate a complete, structured JSON archive of all meetings, audit trails, and logs associated with a user.' },
      { step: 2, title: 'Anonymize User (Right to be Forgotten)', desc: 'Redact personally identifiable information (PII) while preserving aggregate statistical counts.' },
      { step: 3, title: 'Purge Old Retention Logs', desc: 'Execute automated purging of temporary webhook payloads and expired operational logs older than retention policies.' },
    ],
    keyFeatures: [
      { name: 'FERPA/GDPR Compliance', desc: 'Full institutional compliance workflows for privacy legislation.' },
      { name: 'PII Redaction Engine', desc: 'Safely anonymizes names and emails while keeping historical booking metrics valid.' },
    ],
    tips: [
      'Always export a user’s DSAR archive before proceeding with account anonymization.',
    ],
  },

  // 5. Media & Sync
  'recordings.index': {
    title: 'Cloud Recordings Management',
    category: 'Media & Sync',
    summary: 'Browse, stream, and download cloud recordings captured during pooled Zoom sessions, complete with security passcodes and expiration management.',
    whoCanUse: 'Meeting Owners, Faculty, Meeting Admins, Super Admins',
    steps: [
      { step: 1, title: 'View Recordings Feed', desc: 'Faculty see recordings for their own sessions; administrators see recordings campus-wide.' },
      { step: 2, title: 'Stream or Share', desc: 'Click "Play" to open the streaming link or copy the secure playback link with password.' },
      { step: 3, title: 'Download MP4 / M4A', desc: 'Download video, audio, or transcript files directly to local storage.' },
    ],
    keyFeatures: [
      { name: 'Automated Webhook Ingestion', desc: 'Recordings populate automatically as soon as Zoom finishes processing the cloud video.' },
      { name: 'Passcode Protection', desc: 'Protected by encrypted Zoom viewing passcodes to prevent unauthorized public access.' },
    ],
    tips: [
      'Download recordings you wish to keep permanently before the Zoom cloud retention period expires.',
    ],
  },

  'attendance.index': {
    title: 'Meeting Attendance & Participation',
    category: 'Media & Sync',
    summary: 'Monitor attendee engagement, join and leave timestamps, session durations, calculate attendance percentages, and download CSV reports.',
    whoCanUse: 'Meeting Owners, Faculty, Department Admins, IT Admins, Super Admins',
    steps: [
      { step: 1, title: 'Browse Past Meetings', desc: 'Search or filter meetings to view logged attendance totals and completion status.' },
      { step: 2, title: 'Inspect Participant Roster', desc: 'Click "Report" to review the participant list, join/leave times, duration, and status (Present, Partial, Absent).' },
      { step: 3, title: 'Sync from Zoom Cloud', desc: 'Click "Sync Attendance from Zoom" or use the row-level resync button to ingest participant data from Zoom past meeting reports.' },
      { step: 4, title: 'Export Downloadable CSV', desc: 'Click "CSV" or "Export CSV" to download standard spreadsheet-ready attendee records for institutional records or grading.' },
    ],
    keyFeatures: [
      { name: 'Automated 1-Minute Cron Ingestion', desc: 'Background workers automatically query Zoom reports for ended meetings to log participant duration.' },
      { name: 'Attendance Rate Scoring', desc: 'Calculates the exact ratio of time attended versus meeting duration with visual indicator bars.' },
      { name: 'CSV Export Engine', desc: 'Direct stream download of attendee logs formatted for Excel, Canvas, and SIS software.' },
    ],
    tips: [
      'Ensure the report:read:admin scope is enabled in your Zoom Marketplace app to query past meeting participant reports.',
      'Partial status is flagged when a participant leaves early or attends less than 80% of the session.',
    ],
  },

  'drift.index': {
    title: 'State Drift Reconciliation',
    category: 'Media & Sync',
    summary: 'Discrepancy detector that compares local ZPM meeting schedules against live Zoom Cloud states to catch out-of-band edits or unmanaged external meetings.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Run Reconciliation Scan', desc: 'Click "Trigger Live Scan" to query Zoom Cloud for all active resources.' },
      { step: 2, title: 'Review Conflicts', desc: 'Inspect flagged discrepancies: time mismatches, meetings deleted in Zoom directly, or unexpected external reservations.' },
      { step: 3, title: 'Choose Resolution Strategy', desc: 'Accept Zoom Cloud changes to update ZPM, or re-push ZPM schedule to Zoom, or mark external hold.' },
    ],
    keyFeatures: [
      { name: 'Two-Way Synchronization', desc: 'Prevents "ghost meetings" and double-booking when someone alters settings directly in Zoom console.' },
      { name: 'External Meeting Blocker', desc: 'Automatically blocks ZPM allocation when someone uses the Zoom host account outside the pool.' },
    ],
    tips: [
      'Set up daily automated drift reconciliation scans in the scheduler to keep database parity at 100%.',
    ],
  },

  // 6. Developer & Integrations
  'api.keys': {
    title: 'API Keys & Personal Access Tokens',
    category: 'Developer & Integrations',
    summary: 'Generate, manage, and revoke REST API tokens with granular permission scopes for campus ERP, LMS (Canvas/Moodle), or SIS integrations.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Generate New Token', desc: 'Click "Generate API Key", enter a name, and choose an expiration date.' },
      { step: 2, title: 'Assign Scopes', desc: 'Select least-privilege scopes (e.g. meetings:read, meetings:write, recordings:read).' },
      { step: 3, title: 'Copy Secret Token', desc: 'Copy the bearer token immediately; for security, the plaintext token is never displayed again.' },
      { step: 4, title: 'Revoke Key', desc: 'Revoke compromised or obsolete keys with instant effect.' },
    ],
    keyFeatures: [
      { name: 'Granular Scoping', desc: 'Restricts third-party integrations to only the endpoints they need.' },
      { name: 'Rate Limiting Protection', desc: 'API endpoints enforce per-minute rate limits to protect server resources.' },
    ],
    tips: [
      'Refer to the interactive OpenAPI documentation at /docs/api for code examples and schema definitions.',
    ],
  },

  'api.outbound-webhooks': {
    title: 'Outbound Webhooks Subscriptions',
    category: 'Developer & Integrations',
    summary: 'Send real-time signed HTTP POST notifications to institutional systems (LMS, ERP, Slack, Teams) whenever meeting events occur.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Create Subscription', desc: 'Enter endpoint URL and select event topics (meeting.created, meeting.started, meeting.ended).' },
      { step: 2, title: 'HMAC Secret', desc: 'Store the generated HMAC secret to verify X-ZPM-Signature on your receiving webhook server.' },
      { step: 3, title: 'Send Test Ping', desc: 'Click "Ping" to dispatch a simulated payload and verify your server responds with HTTP 200.' },
      { step: 4, title: 'Inspect Delivery Logs', desc: 'Review delivery history, response latency, HTTP codes, and retry failed attempts.' },
    ],
    keyFeatures: [
      { name: 'HMAC SHA-256 Signatures', desc: 'Every payload is cryptographically signed to prevent spoofing.' },
      { name: 'Exponential Backoff Retry', desc: 'Failed deliveries are automatically retried up to 3 times.' },
    ],
    tips: [
      'Use outbound webhooks to automatically notify departmental calendar bots or sync recordings to Panopto/Echo360.',
    ],
  },

  'api.inbound-webhooks': {
    title: 'Zoom Webhook Intake Stream',
    category: 'Developer & Integrations',
    summary: 'Live stream of inbound webhook notifications from Zoom Cloud with HMAC verification, CRC validation, and dead-letter queue replay.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Monitor Intake Stream', desc: 'Watch real-time Zoom events: meeting.started, meeting.ended, recording.completed.' },
      { step: 2, title: 'Inspect Raw Payload', desc: 'Click "View JSON" to inspect the exact payload sent by Zoom’s servers.' },
      { step: 3, title: 'Replay Dead-Letter Events', desc: 'If an event failed due to database locks or temporary outages, click "Retry" to re-process.' },
    ],
    keyFeatures: [
      { name: 'HMAC-SHA256 Verification', desc: 'Validates Zoom webhook secret token and rejects unauthorized or tampered requests.' },
      { name: 'Anti-Replay Attack Protection', desc: 'Deduplicates repeated webhook deliveries using unique event IDs and timestamps.' },
    ],
    tips: [
      'Ensure the Zoom Webhook Secret Token in Settings matches the verification token in your Zoom Marketplace App.',
    ],
  },

  // 7. Identity & Resources
  'pools.index': {
    title: 'Zoom Resource Pools & Host Licenses',
    category: 'Identity & Resources',
    summary: 'Discover Zoom host accounts from your organization, organize them into pools (General Classrooms, Large Webinars, Exam Proctoring), and configure automated load balancing.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Sync Users from Zoom', desc: 'After configuring Server-to-Server OAuth, click "Sync Users from Zoom". ZPM queries the Zoom Users API and registers all licensed host accounts into the catalog.' },
      { step: 2, title: 'Create Resource Pools', desc: 'Click "New Pool". Provide a Pool Name (e.g. Standard Classroom Pool), Code (e.g. POOL_STANDARD), and select an Allocation Strategy (Least Hours Today, Round Robin, or Priority).' },
      { step: 3, title: 'Assign Zoom Accounts to Pools', desc: 'Either check off accounts during pool creation, or click "Assign Pools" on any host account in the table to link it to one or more pools.' },
      { step: 4, title: 'Include or Exclude Accounts', desc: 'Use the "Include/Exclude" toggle to keep administrative or personal Zoom accounts in your organization from receiving automated meeting bookings.' },
    ],
    keyFeatures: [
      { name: '1-Click Zoom Discovery', desc: 'Directly queries GET /users via OAuth to sync host names, emails, and participant capacities.' },
      { name: 'Multi-Pool Membership', desc: 'A Zoom host account can serve in multiple pools or be dedicated to a specific department.' },
      { name: 'Least-Hours-Today Strategy', desc: 'Distributes booking hours evenly across all hosts in the pool to prevent wear and avoid Zoom daily rate limits.' },
      { name: 'Automatic Host Rotation', desc: 'Host keys on pooled accounts rotate automatically upon meeting completion.' },
    ],
    tips: [
      'Ensure your Zoom Server-to-Server app has the user:read:admin scope before syncing accounts.',
      'Always use "Least Hours Today" for general classroom pools so that no single host account is overburdened.',
      'Excluded accounts remain in the database for tracking but will never be scheduled by the booking engine.',
    ],
  },

  'users.index': {
    title: 'User Directory & Permissions',
    category: 'Identity & Resources',
    summary: 'Manage university user accounts, assign roles (Faculty, Staff, Approver, Admin), assign academic departments, and monitor 2FA security status.',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Search & Filter Users', desc: 'Filter by department, role, or 2FA status, or search by name and email.' },
      { step: 2, title: 'Create / Edit User', desc: 'Create manual accounts or modify assigned roles and departmental affiliation.' },
      { step: 3, title: 'Toggle Active Status', desc: 'Deactivate departing faculty to instantly revoke access while preserving historical meeting records.' },
    ],
    keyFeatures: [
      { name: '9 Built-in Roles', desc: 'Super Admin, IT Admin, Meeting Admin, Dept Admin, Approver, Faculty, Staff, Auditor, API Client.' },
      { name: '2FA TOTP Status', desc: 'Audit which accounts have active two-factor authentication enabled.' },
    ],
    tips: [
      'Users authenticated via Google or Microsoft SSO are auto-provisioned without needing manual creation.',
    ],
  },

  'departments.index': {
    title: 'Academic & Admin Departments',
    category: 'Identity & Resources',
    summary: 'Organize campus users and scheduling quotas by department (Computer Science, Business School, Medicine, Human Resources).',
    whoCanUse: 'IT Administrators, Super Administrators',
    steps: [
      { step: 1, title: 'Review Department List', desc: 'View department codes, department heads, and active user member counts.' },
      { step: 2, title: 'Add New Department', desc: 'Click "Add Department", enter name, code (e.g. CS, ENG), and optional description.' },
      { step: 3, title: 'Department Scoping', desc: 'Used across the system to scope approvals, monthly quotas, and blackout periods.' },
    ],
    keyFeatures: [
      { name: 'Member Count Tracking', desc: 'View how many faculty and staff belong to each department.' },
      { name: 'Scoped Governance', desc: 'Enables Department Admins to oversee only their unit’s schedules and quotas.' },
    ],
    tips: [
      'Set clean departmental codes (e.g. "CHEM", "MATH") to allow automatic mapping from SAML/SSO group claims.',
    ],
  },

  // 8. Settings & Communications
  'settings.zoom': {
    title: 'Zoom API & OAuth Configuration',
    category: 'Settings & Administration',
    summary: 'Configure Server-to-Server OAuth credentials, webhook verification tokens, inspect granted scopes, and verify live connectivity with Zoom Cloud API.',
    whoCanUse: 'Super Administrators',
    steps: [
      { step: 1, title: 'Open Zoom Developer Portal', desc: 'Navigate to https://marketplace.zoom.us/develop/ and log in as your organization’s Zoom Account Owner or Administrator.' },
      { step: 2, title: 'Create Server-to-Server OAuth App', desc: 'Click "Develop" → "Build App" → select "Server-to-Server OAuth". Enter an app name like "Zoom Pool Manager".' },
      { step: 3, title: 'Add Required Permission Scopes', desc: 'Under the Scopes tab, add meeting:write:admin, meeting:read:admin, user:read:admin, and user:write:admin. For cloud recordings and attendance, add recording:read:admin and report:read:admin.' },
      { step: 4, title: 'Enable Webhooks (Event Subscriptions)', desc: 'Under Feature → Event Subscriptions, add https://your-domain/api/webhooks/zoom and subscribe to meeting.started, meeting.ended, and recording.completed.' },
      { step: 5, title: 'Copy Credentials & Activate App', desc: 'Copy Account ID, Client ID, Client Secret, and Webhook Secret Token. Click "Continue" to activate the app, then paste credentials into ZPM and click "Test Connection".' },
    ],
    keyFeatures: [
      { name: 'Live Scope Inspection', desc: 'Detects granted vs missing scopes from Zoom OAuth token response.' },
      { name: 'Automated Host Key Rotation', desc: 'Requires user:write:admin to rotate 6-digit host PINs after every session.' },
      { name: 'Attendance Sync Readiness', desc: 'Requires report:read:admin to ingest past meeting participant reports.' },
      { name: 'Encrypted Credential Vault', desc: 'Secrets are encrypted using AES-256-GCM and never displayed in plain text.' },
    ],
    tips: [
      'Zoom requires an Account Owner or Admin with Marketplace permissions to create Server-to-Server OAuth applications.',
      'Always activate the app in Zoom Marketplace before testing connection, otherwise Zoom will return invalid_client.',
      'Use the "Copy All Scopes" button on the Zoom Settings page to quickly search and select scopes in the Zoom Marketplace picker.',
    ],
  },

  'zoom.marketplace_guide': {
    title: 'Zoom Marketplace App Creation Guide',
    category: 'Settings & Administration',
    summary: 'Complete walkthrough for creating a Server-to-Server OAuth app on https://marketplace.zoom.us/develop/ with the exact permission scopes required by Zoom Pool Manager.',
    whoCanUse: 'Super Administrators & Zoom Account Owners',
    steps: [
      { step: 1, title: 'Access Zoom Developer Marketplace', desc: 'Open https://marketplace.zoom.us/develop/ and sign in with your institutional Zoom administrator credentials.' },
      { step: 2, title: 'Choose Server-to-Server OAuth', desc: 'In the top-right menu, click "Develop" → "Build App". Locate the "Server-to-Server OAuth" tile and click "Create".' },
      { step: 3, title: 'Fill Basic Information', desc: 'Provide an App Name (e.g. "Zoom Pool Manager Production"), Company Name, and Developer Contact Email.' },
      { step: 4, title: 'Select Scopes (Where to Find Them in Zoom)', desc: 'Click "+ Add Scopes". In the left category menu: (1) Click "Recording" → check "View all user recordings" (recording:read:admin) or "View a meeting\'s recordings" (recording:read:recording:admin). (2) Click "Meeting" → check "View and manage all user meetings" (meeting:write:admin) and "View all user meetings" (meeting:read:admin). (3) Click "User" → check "View all user information" (user:read:admin) and "View and manage all user information" (user:write:admin). (4) Click "Report" → check "View all user and meeting reports" (report:read:admin).' },
      { step: 5, title: 'Configure Event Webhooks', desc: 'Under "Feature" → enable "Event Subscriptions". Enter your ZPM Webhook URL (https://your-domain/api/webhooks/zoom). Subscribe to meeting.started, meeting.ended, and recording.completed.' },
      { step: 6, title: 'Activate & Test in ZPM', desc: 'Click "Continue" through to the "Activation" step and click "Activate your app". Copy Account ID, Client ID, Client Secret, and Webhook Secret Token into ZPM Zoom Settings and click "Test Connection".' },
    ],
    keyFeatures: [
      { name: 'How to Find Recordings Scope', desc: 'Select "Recording" on the left sidebar of the Zoom modal, then check "View all user recordings" (recording:read:admin).' },
      { name: 'Required Core Scopes', desc: 'meeting:write:admin, meeting:read:admin, user:read:admin, user:write:admin.' },
      { name: 'Recommended & V1.1 Scopes', desc: 'recording:read:admin (Recordings), report:read:admin (Attendance), dashboard:read:admin (Telemetry).' },
      { name: 'Granular Scopes Compatible', desc: 'Works with both classic admin scopes and granular OAuth scopes (recording:read:recording:admin, meeting:write:meeting:admin, etc.).' },
    ],
    tips: [
      'Server-to-Server OAuth apps are internal to your Zoom account and do NOT require public Zoom Marketplace review or publishing.',
      'If you rotate your Zoom Client Secret in Zoom Marketplace, remember to update it in Zoom Pool Manager to maintain connectivity.',
    ],
  },

  'mail.settings': {
    title: 'Mail Server Configuration',
    category: 'Settings & Administration',
    summary: 'Configure email delivery transports (SMTP, SendGrid, Amazon SES, Postmark), test live mail dispatch, and review outbox delivery logs.',
    whoCanUse: 'Super Administrators',
    steps: [
      { step: 1, title: 'Select Transport', desc: 'Choose SMTP, SendGrid, Amazon SES, Postmark, or local Log.' },
      { step: 2, title: 'Configure Host & Port', desc: 'Enter host, port (587 / 465), encryption (TLS / SSL), username, and password.' },
      { step: 3, title: 'Send Test Email', desc: 'Enter a recipient address and click "Send Test Email" to verify live delivery.' },
      { step: 4, title: 'Monitor Delivery Logs', desc: 'Inspect the Outbox logs to ensure notifications are delivered with 0 errors.' },
    ],
    keyFeatures: [
      { name: 'Multi-Transport Support', desc: 'Seamlessly switch between university SMTP relays and cloud email services.' },
      { name: 'Outbox Retry Engine', desc: 'Failed emails can be inspected and retried directly from the delivery log.' },
    ],
    tips: [
      'Use TLS on port 587 for modern university SMTP relays to prevent delivery timeouts.',
    ],
  },

  'mail.templates': {
    title: 'Email Notification Templates',
    category: 'Settings & Administration',
    summary: 'Customize transactional email templates (Meeting Confirmation, Host Key Details, Approval Requests, Cancellations) with merge tags and live preview.',
    whoCanUse: 'Super Administrators',
    steps: [
      { step: 1, title: 'Select Template', desc: 'Choose a notification trigger (e.g. Meeting Scheduled, Approval Required, Meeting Cancelled).' },
      { step: 2, title: 'Use Merge Tags', desc: 'Insert dynamic tags such as {{ recipient_name }}, {{ meeting.title }}, {{ meeting.starts_at }}, and {{ join_url }}.' },
      { step: 3, title: 'Live HTML Preview', desc: 'Inspect real-time mobile and desktop rendering in the preview sandbox before saving.' },
    ],
    keyFeatures: [
      { name: 'Dynamic Variable Replacements', desc: 'Automatically populates recipient details, dates, and meeting links.' },
      { name: 'Responsive Layouts', desc: 'Clean HTML emails that render seamlessly on mobile phones and desktop mail clients.' },
    ],
    tips: [
      'Always test formatting with the live preview to verify merge tags appear correctly.',
    ],
  },

  'notifications.index': {
    title: 'In-App Notifications Center',
    category: 'Settings & Administration',
    summary: 'Personal notification feed alerting you to meeting confirmations, approval decisions, host key releases, and scheduling changes.',
    whoCanUse: 'All Authenticated Users',
    steps: [
      { step: 1, title: 'Check Unread Alerts', desc: 'Review new notifications indicated by the unread badge counter.' },
      { step: 2, title: 'Navigate to Source', desc: 'Click any notification to navigate directly to the relevant meeting or approval request.' },
      { step: 3, title: 'Mark All Read', desc: 'Click "Mark all as read" to clear unread badges once reviewed.' },
    ],
    keyFeatures: [
      { name: 'Real-Time Alerts', desc: 'Instant feedback when an approval is granted or when a meeting is rescheduled.' },
      { name: 'Direct Deep-Linking', desc: 'Jumps directly to the relevant action item in one click.' },
    ],
    tips: [
      'Keep notifications clear by marking items read once you have taken action.',
    ],
  },

  'settings.updates': {
    title: 'System Updates & Releases',
    category: 'Settings & Administration',
    summary: 'Check for upstream GitHub releases, review changelogs and security patches, and manage maintenance mode during deployments.',
    whoCanUse: 'Super Administrators',
    steps: [
      { step: 1, title: 'Check for Updates', desc: 'Click "Check for Updates" to query the official GitHub release repository.' },
      { step: 2, title: 'Review Release Notes', desc: 'Read new features, bug fixes, and upgrade instructions in the release changelog.' },
      { step: 3, title: 'Deploy & Lock', desc: 'Safely execute updates with automatic database migration and cache flushing.' },
    ],
    keyFeatures: [
      { name: 'Release Version Checker', desc: 'Compares installed version against the latest upstream release.' },
      { name: 'Update Mutex Locking', desc: 'Prevents concurrent update executions that could interrupt database migrations.' },
    ],
    tips: [
      'Always verify that a full database snapshot has been taken before executing major version updates.',
    ],
  },

  'settings.sso': {
    title: 'Single Sign-On (SSO) & SAML 2.0 Configuration',
    category: 'Settings & Administration',
    summary: 'Configure Google Workspace, Microsoft Entra ID (Azure AD), and SAML 2.0 Identity Providers with automated JIT user provisioning and role mapping.',
    whoCanUse: 'Super Administrators',
    steps: [
      { step: 1, title: 'Select Identity Protocol', desc: 'Choose Google Workspace OAuth 2.0, Microsoft Entra ID, or SAML 2.0 based on your institution’s identity infrastructure.' },
      { step: 2, title: 'Configure Identity Provider', desc: 'Enter Client ID, Client Secret, Tenant ID, or IdP SSO Service URL and primary X.509 certificate.' },
      { step: 3, title: 'Configure SP in Identity Provider', desc: 'For SAML, click "SP Metadata" to copy your Entity ID, ACS URL, and download SP Metadata XML to register ZPM in Azure AD or Okta.' },
      { step: 4, title: 'Set Allowed Domains & Enable', desc: 'Restrict sign-in to authorized institutional domains (e.g. univ.edu) and toggle the provider to Enabled.' },
    ],
    keyFeatures: [
      { name: 'Multi-IdP Support', desc: 'Run Google Workspace, Microsoft Entra ID, and SAML 2.0 side-by-side.' },
      { name: 'Zero-Downtime Certificate Rotation', desc: 'Configure primary and secondary X.509 certs to rotate IdP signing keys without authentication outage.' },
      { name: 'SP Metadata Generator', desc: 'Automatically generates compliant SAML 2.0 SP metadata XML.' },
    ],
    tips: [
      'For Microsoft Entra ID SAML apps, copy the ACS URL from ZPM into the Azure Portal Reply URL (Assertion Consumer Service URL).',
      'Use domain restrictions to prevent unauthorized external personal Google or Microsoft accounts from logging in.',
    ],
  },

  'settings.directory-sync': {
    title: 'Active Directory & Directory Synchronization',
    category: 'Settings & Administration',
    summary: 'Automate account discovery, user provisioning, and department mapping directly from Microsoft Entra ID (Microsoft Graph), Google Workspace, or LDAP.',
    whoCanUse: 'Super Administrators',
    steps: [
      { step: 1, title: 'Add Directory Connector', desc: 'Select Microsoft Entra ID (Graph API), Google Workspace Directory, or LDAP / On-Premise Active Directory.' },
      { step: 2, title: 'Enter Application Credentials', desc: 'Provide Application (Client) ID, Client Secret, and Directory Tenant GUID with User.Read.All permission.' },
      { step: 3, title: 'Define Provisioning Rules', desc: 'Specify domain filters, default roles, and enable automatic department creation from directory attributes.' },
      { step: 4, title: 'Execute Synchronization', desc: 'Click "Test Connection" to verify credentials, then "Sync Now" to run an immediate discovery pass.' },
    ],
    keyFeatures: [
      { name: 'Automated 30-Minute Sync', desc: 'Background cron job polls directory changes and creates or updates users automatically.' },
      { name: 'Department Auto-Creation', desc: 'Reads the department attribute from the directory and assigns or creates departments in ZPM.' },
      { name: 'Deactivation Safeguard', desc: 'Optional deactivation of departed staff without ever impacting Super Administrators.' },
    ],
    tips: [
      'Grant the Microsoft Entra ID App the application permission User.Read.All and grant Admin Consent in the Azure portal.',
      'Use the domain filter (e.g. "univ.edu") to exclude service or test accounts from being ingested into ZPM.',
    ],
  },

  'settings.jobs': {
    title: 'Scheduled Jobs & Automation Cadence',
    category: 'Settings & Administration',
    summary: 'Centralized administrative control over all 10 background automation cron jobs, execution frequencies, telemetry heartbeats, and on-demand triggers.',
    whoCanUse: 'Super Administrators, IT Administrators',
    steps: [
      { step: 1, title: 'Inspect Daemon Health', desc: 'Verify the top telemetry banner shows "Daemon Operational" with recent heartbeat activity.' },
      { step: 2, title: 'Adjust Execution Frequencies', desc: 'Change the cadence dropdown for any job (e.g. from 15m to 5m or 1m) to increase or decrease automation frequency.' },
      { step: 3, title: 'Toggle Automation Tasks', desc: 'Use the status switch to disable or re-enable background tasks without modifying code or cron files.' },
      { step: 4, title: 'Run Jobs On-Demand', desc: 'Click "Run Now" to immediately execute any job synchronously and view its live console output in the modal terminal.' },
    ],
    keyFeatures: [
      { name: 'Unified Automation Engine', desc: 'Controls Meeting Reconciler, Directory Sync, Zoom Discovery, Cloud Recordings, Attendance, and Backups.' },
      { name: 'Live Console Terminal', desc: 'Inspect full stdout logs and exit codes for every background execution directly from the web browser.' },
      { name: 'Zero-Downtime Cadence Switching', desc: 'Frequency changes take effect on the next scheduled tick without restarting Docker services.' },
    ],
    tips: [
      'For rapid testing of directory sync or recordings sync, temporarily select the "Every minute (1m)" cadence or click "Run Now".',
      'The scheduler daemon runs continuously inside the Docker container via artisan schedule:work.',
    ],
  },
};
