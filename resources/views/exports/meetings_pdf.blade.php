<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scheduled Meetings Report - Zoom Pool Manager</title>
  <style>
    @page {
      size: A4 landscape;
      margin: 15mm;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      color: #1e293b;
      margin: 0;
      padding: 20px;
      font-size: 11px;
      line-height: 1.4;
      background: #ffffff;
    }
    .header-bar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #0284c7;
      padding-bottom: 12px;
      margin-bottom: 15px;
    }
    .header-title h1 {
      margin: 0;
      font-size: 18px;
      color: #0f172a;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .header-title p {
      margin: 4px 0 0 0;
      color: #64748b;
      font-size: 11px;
    }
    .meta-box {
      text-align: right;
      font-size: 10px;
      color: #64748b;
    }
    .filters-bar {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 8px 12px;
      margin-bottom: 15px;
      display: flex;
      gap: 20px;
      font-size: 10px;
    }
    .filters-bar strong {
      color: #334155;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 5px;
    }
    th {
      background: #f1f5f9;
      color: #475569;
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      text-align: left;
      padding: 8px 6px;
      border-bottom: 1.5px solid #cbd5e1;
    }
    td {
      padding: 6px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: top;
    }
    tr:nth-child(even) td {
      background: #fcfcfd;
    }
    .badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 9999px;
      font-size: 8px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-scheduled { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .badge-started { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-allocating { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .badge-completed { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .badge-cancelled { background: #ffe4e6; color: #be123c; border: 1px solid #fecdd3; }
    .badge-failed { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
    .font-mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 9px;
    }
    .footer {
      margin-top: 25px;
      padding-top: 10px;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      color: #94a3b8;
      font-size: 9px;
    }
    .print-actions {
      margin-bottom: 15px;
      text-align: right;
    }
    .btn-print {
      background: #0284c7;
      color: #ffffff;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-print:hover {
      background: #0369a1;
    }
    @media print {
      .print-actions {
        display: none !important;
      }
      body {
        padding: 0;
      }
    }
  </style>
</head>
<body>

  <div class="print-actions">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
  </div>

  <div class="header-bar">
    <div class="header-title">
      <h1>Zoom Pool Manager</h1>
      <p>Institutional Scheduled Meetings Official Report</p>
    </div>
    <div class="meta-box">
      <div><strong>Export Date:</strong> {{ $exported_at->format('M d, Y H:i:s T') }}</div>
      <div><strong>Exported By:</strong> {{ $user->name }} ({{ $user->email }})</div>
    </div>
  </div>

  <div class="filters-bar">
    <div><strong>Filter Status:</strong> {{ ucfirst($filters['status']) }}</div>
    <div><strong>Search Filter:</strong> {{ $filters['search'] }}</div>
    <div><strong>Total Records Exported:</strong> {{ $filters['total'] }}</div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width: 25%;">Meeting Title & ID</th>
        <th style="width: 20%;">Time Window</th>
        <th style="width: 18%;">Host / Resource</th>
        <th style="width: 12%;">Zoom ID</th>
        <th style="width: 10%;">Status</th>
        <th style="width: 15%;">Join URL</th>
      </tr>
    </thead>
    <tbody>
      @forelse($meetings as $m)
        <tr>
          <td>
            <div style="font-weight: 700; color: #0f172a;">{{ $m->title }}</div>
            <div class="font-mono" style="color: #64748b;">ID: {{ $m->public_id }}</div>
            @if($m->template)
              <div style="font-size: 9px; color: #0284c7;">Template: {{ $m->template->name }}</div>
            @endif
          </td>
          <td>
            <div>{{ $m->starts_at ? $m->starts_at->format('M d, Y H:i') : 'N/A' }}</div>
            <div style="color: #64748b; font-size: 9px;">to {{ $m->ends_at ? $m->ends_at->format('H:i T') : 'N/A' }}</div>
          </td>
          <td>
            <div style="font-weight: 600;">{{ $m->zoomResource?->name ?? 'Pooled Auto-Assign' }}</div>
            <div style="color: #64748b; font-size: 9px;">Owner: {{ $m->owner?->name ?? 'N/A' }}</div>
          </td>
          <td class="font-mono">
            {{ $m->zoom_meeting_id ?: 'N/A' }}
          </td>
          <td>
            @php
              $badgeClass = match($m->status) {
                'scheduled' => 'badge-scheduled',
                'started' => 'badge-started',
                'allocating' => 'badge-allocating',
                'completed' => 'badge-completed',
                'cancelled' => 'badge-cancelled',
                'failed' => 'badge-failed',
                default => 'badge-completed'
              };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ $m->status }}</span>
          </td>
          <td class="font-mono" style="word-break: break-all; font-size: 9px;">
            {{ $m->join_url ?: 'N/A' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" style="text-align: center; padding: 25px; color: #94a3b8;">
            No meetings matching the selected criteria.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer">
    <div>Generated by Zoom Pool Manager • Enterprise License Allocation System</div>
    <div>Confidential & Proprietary • Page 1 of 1</div>
  </div>

</body>
</html>
