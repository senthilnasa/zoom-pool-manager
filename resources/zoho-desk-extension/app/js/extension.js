(function() {
  'use strict';

  // State
  let config = {
    serverUrl: '__ZPM_SERVER_URL__',
    apiToken: '__ZPM_API_TOKEN__'
  };

  let currentTicket = {
    id: '',
    ticketNumber: '',
    subject: '',
    email: '',
    contactName: ''
  };

  let currentAgent = {
    id: '',
    name: '',
    email: ''
  };

  let zpmOptions = {
    pools: [],
    templates: []
  };

  // DOM Elements
  const elConfigPanel = document.getElementById('config-panel');
  const btnToggleConfig = document.getElementById('btn-toggle-config');
  const btnCloseConfig = document.getElementById('btn-close-config');
  const btnSaveConfig = document.getElementById('btn-save-config');
  const cfgServerUrl = document.getElementById('cfg-server-url');
  const cfgApiToken = document.getElementById('cfg-api-token');

  const lblTicketNumber = document.getElementById('lbl-ticket-number');
  const lblTicketEmail = document.getElementById('lbl-ticket-email');
  const lblContactName = document.getElementById('lbl-contact-name');
  const lblAgentName = document.getElementById('lbl-agent-name');
  const lblAgentEmail = document.getElementById('lbl-agent-email');

  const formBooking = document.getElementById('booking-form');
  const inpTopic = document.getElementById('inp-topic');
  const inpDate = document.getElementById('inp-date');
  const inpTime = document.getElementById('inp-time');
  const inpDuration = document.getElementById('inp-duration');
  const inpPool = document.getElementById('inp-pool');
  const chkPostTicket = document.getElementById('chk-post-ticket');
  const chkCloseTicket = document.getElementById('chk-close-ticket');
  const chkShareHostKey = document.getElementById('chk-share-host-key');
  const btnSubmit = document.getElementById('btn-submit');
  const btnSubmitText = document.getElementById('btn-submit-text');
  const btnSubmitSpinner = document.getElementById('btn-submit-spinner');

  const resultCard = document.getElementById('result-card');
  const lblResultStatus = document.getElementById('lbl-result-status');
  const resJoinUrl = document.getElementById('res-join-url');
  const resMeetingId = document.getElementById('res-meeting-id');
  const resPasscode = document.getElementById('res-passcode');
  const resHostKey = document.getElementById('res-host-key');
  const badgeComment = document.getElementById('badge-comment');
  const badgeClosed = document.getElementById('badge-closed');
  const btnOpenZpm = document.getElementById('btn-open-zpm');
  const btnReset = document.getElementById('btn-reset');
  const alertBanner = document.getElementById('alert-banner');

  // Copy helper
  window.copyField = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.select();
    document.execCommand('copy');
    showAlert('Copied to clipboard!', 'success');
  };

  function showAlert(msg, type = 'error') {
    if (!alertBanner) return;
    alertBanner.textContent = msg;
    alertBanner.className = `alert-banner ${type}`;
    alertBanner.classList.remove('hidden');
    setTimeout(() => {
      alertBanner.classList.add('hidden');
    }, 5000);
  }

  // Load saved config from local/extension storage
  function loadConfig() {
    try {
      const saved = localStorage.getItem('zpm_zd_config');
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.serverUrl) config.serverUrl = parsed.serverUrl;
        if (parsed.apiToken) config.apiToken = parsed.apiToken;
      }
    } catch (e) {}

    if (cfgServerUrl) cfgServerUrl.value = config.serverUrl;
    if (cfgApiToken) cfgApiToken.value = config.apiToken;
  }

  function saveConfig() {
    config.serverUrl = cfgServerUrl.value.trim().replace(/\/$/, '');
    config.apiToken = cfgApiToken.value.trim();

    try {
      localStorage.setItem('zpm_zd_config', JSON.stringify(config));
    } catch (e) {}

    elConfigPanel.classList.add('hidden');
    showAlert('Connection settings saved!', 'success');
    fetchOptions();
  }

  // Set default date & time (next upcoming 30m slot)
  function initDateTimeDefaults() {
    const now = new Date();
    const minutes = now.getMinutes();
    const roundedMinutes = minutes < 30 ? 30 : 60;
    now.setMinutes(roundedMinutes, 0, 0);

    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const mins = String(now.getMinutes()).padStart(2, '0');

    if (inpDate) inpDate.value = `${year}-${month}-${day}`;
    if (inpTime) inpTime.value = `${hours}:${mins}`;
  }

  // Fetch ZPM options (pools, templates)
  async function fetchOptions() {
    if (!config.serverUrl) return;

    try {
      const res = await fetch(`${config.serverUrl}/api/v1/integrations/zoho-desk/options`, {
        headers: {
          'X-API-KEY': config.apiToken,
          'Accept': 'application/json'
        }
      });

      if (res.ok) {
        const data = await res.json();
        zpmOptions = data;

        if (inpPool && data.pools) {
          inpPool.innerHTML = '<option value="">Default Available Pool</option>';
          data.pools.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.name} (Cap: ${p.capacity})`;
            if (data.default_pool_id && data.default_pool_id === p.id) {
              opt.selected = true;
            }
            inpPool.appendChild(opt);
          });
        }
      }
    } catch (err) {
      console.warn('Could not fetch ZPM options:', err);
    }
  }

  // Handle Form Submission
  async function handleBookingSubmit(e) {
    e.preventDefault();

    if (!config.serverUrl || !config.apiToken) {
      elConfigPanel.classList.remove('hidden');
      showAlert('Please configure ZPM Server URL and API Token first.');
      return;
    }

    const topic = inpTopic.value.trim();
    const date = inpDate.value;
    const time = inpTime.value;
    const duration = parseInt(inpDuration.value, 10) || 60;
    const poolId = inpPool.value ? parseInt(inpPool.value, 10) : null;
    const postToTicket = chkPostTicket.checked;
    const isPublic = document.querySelector('input[name="reply_type"]:checked')?.value === 'public';
    const closeTicket = chkCloseTicket.checked;
    const shareHostKey = chkShareHostKey.checked;

    const startsAt = new Date(`${date}T${time}:00`).toISOString();

    btnSubmit.disabled = true;
    btnSubmitText.textContent = 'Booking meeting...';
    btnSubmitSpinner.classList.remove('hidden');

    try {
      const payload = {
        ticket_id: currentTicket.id,
        ticket_number: currentTicket.ticketNumber,
        ticket_subject: currentTicket.subject,
        ticket_email: currentTicket.email,
        ticket_contact_name: currentTicket.contactName,
        agent_id: currentAgent.id,
        agent_name: currentAgent.name,
        agent_email: currentAgent.email,
        title: topic,
        starts_at: startsAt,
        duration_minutes: duration,
        pool_id: poolId,
        post_to_ticket: postToTicket,
        is_public: isPublic,
        close_ticket: closeTicket,
        share_host_key: shareHostKey
      };

      const res = await fetch(`${config.serverUrl}/api/v1/integrations/zoho-desk/book-and-reply`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-API-KEY': config.apiToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const result = await res.json();

      if (!res.ok || !result.success) {
        throw new Error(result.message || 'Scheduling failed. Please check ZPM configuration.');
      }

      // Fallback: If server couldn't post to Zoho Desk directly (e.g. server-side OAuth wasn't configured),
      // we post directly via the agent's browser session using Zoho Desk Apps SDK!
      if (postToTicket && !result.ticket_comment_posted && window.ZOHODESK) {
        try {
          await window.ZOHODESK.comment.add({
            id: currentTicket.id,
            isPublic: isPublic,
            content: result.comment_text
          });
          result.ticket_comment_posted = true;
        } catch (sdkErr) {
          console.warn('Client-side SDK comment fallback failed:', sdkErr);
        }
      }

      if (closeTicket && !result.ticket_closed && window.ZOHODESK) {
        try {
          await window.ZOHODESK.ticket.update({
            id: currentTicket.id,
            status: 'Closed'
          });
          result.ticket_closed = true;
        } catch (sdkErr) {
          console.warn('Client-side SDK ticket close fallback failed:', sdkErr);
        }
      }

      displaySuccess(result);
    } catch (err) {
      showAlert(err.message || 'An error occurred during booking.');
    } finally {
      btnSubmit.disabled = false;
      btnSubmitText.textContent = 'Book Meeting & Update Ticket';
      btnSubmitSpinner.classList.add('hidden');
    }
  }

  function displaySuccess(res) {
    const meeting = res.meeting;
    formBooking.classList.add('hidden');
    resultCard.classList.remove('hidden');

    const bookedByName = (res.meeting && res.meeting.booked_by && res.meeting.booked_by.name) || currentAgent.name || 'IT Agent';
    lblResultStatus.textContent = `Scheduled by ${bookedByName} on behalf of ${meeting.owner?.name || currentTicket.contactName}`;
    resJoinUrl.value = meeting.join_url || '';
    resMeetingId.value = meeting.zoom_meeting_id || '';
    resPasscode.value = meeting.passcode || '';
    resHostKey.value = meeting.host_key || '';

    if (btnOpenZpm) {
      btnOpenZpm.href = `${config.serverUrl}/app/meetings`;
    }

    if (badgeComment) {
      if (res.ticket_comment_posted) {
        badgeComment.textContent = '✓ Comment Posted';
        badgeComment.className = 'status-badge';
      } else {
        badgeComment.textContent = 'Comment Not Posted';
        badgeComment.className = 'status-badge failed';
      }
    }

    if (badgeClosed) {
      if (res.ticket_closed) {
        badgeClosed.textContent = '✓ Ticket Closed';
        badgeClosed.className = 'status-badge';
      } else {
        badgeClosed.textContent = 'Ticket Not Closed';
        badgeClosed.className = 'status-badge failed';
      }
    }
  }

  function resetForm() {
    resultCard.classList.add('hidden');
    formBooking.classList.remove('hidden');
    initDateTimeDefaults();
  }

  // Initialize Zoho Desk SDK
  function initDeskSdk() {
    if (typeof ZOHODESK !== 'undefined') {
      ZOHODESK.init().then(function(App) {
        // 1. Fetch current ticket context
        ZOHODESK.get('ticket').then(function(response) {
          const t = response && (response.ticket || response['ticket']);
          if (t) {
            currentTicket.id = t.id || '';
            currentTicket.ticketNumber = t.ticketNumber || t.id || '';
            currentTicket.subject = t.subject || '';
            currentTicket.email = t.email || (t.contact && t.contact.email) || '';
            currentTicket.contactName = (t.contact && t.contact.name) || t.contactName || currentTicket.email.split('@')[0];

            updateTicketUi();
          }
        }).catch(function(err) {
          console.warn('Failed to get ticket from Desk SDK:', err);
        });

        // 2. Fetch logged-in IT Agent identity for cryptographic audit & attribution
        ZOHODESK.get('currentUser').then(function(userRes) {
          const u = userRes && (userRes.currentUser || userRes['currentUser']);
          if (u) {
            currentAgent.id = u.id || '';
            currentAgent.name = u.name || '';
            currentAgent.email = u.email || '';
            updateAgentUi();
          }
        }).catch(function(err) {
          console.warn('Failed to get currentUser from Desk SDK:', err);
        });
      }).catch(function(err) {
        console.warn('Desk SDK init error:', err);
      });
    } else {
      // Demo / simulation mode outside Zoho Desk
      currentTicket = {
        id: '1001',
        ticketNumber: 'TKT-10492',
        subject: 'Need Zoom meeting with Dean',
        email: 'faculty@krea.edu.in',
        contactName: 'Prof. Rajesh Sharma'
      };
      currentAgent = {
        id: 'agent-101',
        name: 'IT Support Engineer',
        email: 'it-support@krea.edu.in'
      };
      updateTicketUi();
      updateAgentUi();
    }
  }

  function updateAgentUi() {
    if (lblAgentName) lblAgentName.textContent = currentAgent.name || 'IT Support';
    if (lblAgentEmail) lblAgentEmail.textContent = currentAgent.email ? `(${currentAgent.email})` : '';
  }

  function updateTicketUi() {
    if (lblTicketNumber) lblTicketNumber.textContent = `#${currentTicket.ticketNumber}`;
    if (lblTicketEmail) lblTicketEmail.textContent = currentTicket.email;
    if (lblContactName) lblContactName.textContent = currentTicket.contactName;
    if (inpTopic && (!inpTopic.value || inpTopic.value === 'Zoom Meeting with requester')) {
      inpTopic.value = currentTicket.subject ? `Meeting: ${currentTicket.subject}` : `Zoom Meeting with ${currentTicket.contactName}`;
    }
  }

  // Event Listeners
  if (btnToggleConfig) {
    btnToggleConfig.addEventListener('click', () => elConfigPanel.classList.toggle('hidden'));
  }
  if (btnCloseConfig) {
    btnCloseConfig.addEventListener('click', () => elConfigPanel.classList.add('hidden'));
  }
  if (btnSaveConfig) {
    btnSaveConfig.addEventListener('click', saveConfig);
  }
  if (formBooking) {
    formBooking.addEventListener('submit', handleBookingSubmit);
  }
  if (btnReset) {
    btnReset.addEventListener('click', resetForm);
  }

  // Startup
  loadConfig();
  initDateTimeDefaults();
  initDeskSdk();
  fetchOptions();
})();
