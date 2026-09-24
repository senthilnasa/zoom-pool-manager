<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Mail Server & Delivery Config
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Transactional email delivery pipeline, SMTP/cloud mailer configuration, and outbox logs
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="showTestSendModal = true"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold bg-white/80 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition shadow-sm"
        >
          <Send class="w-3.5 h-3.5 text-brand-500" />
          <span>Send Test Email</span>
        </button>

        <button
          @click="testConnection"
          :disabled="testingConnection"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition disabled:opacity-50"
        >
          <Activity class="w-3.5 h-3.5" :class="{ 'animate-spin': testingConnection }" />
          <span>{{ testingConnection ? 'Testing...' : 'Test Connection' }}</span>
        </button>
      </div>
    </div>

    <!-- Feedback Message -->
    <div v-if="feedback" class="p-4 rounded-xl text-xs font-semibold flex items-center justify-between" :class="feedbackIsError ? 'bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400'">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="hover:underline">Dismiss</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Settings Form -->
      <div class="lg:col-span-2 glass-card rounded-2xl p-6 border border-slate-200/60 dark:border-slate-800/60 space-y-5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <Mail class="w-4 h-4 text-brand-500" />
          <span>Delivery Provider Setup</span>
        </h2>

        <form @submit.prevent="saveSettings" class="space-y-4">
          <!-- Provider Picker -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Email Transport Provider *</label>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
              <button
                type="button"
                v-for="prov in providers"
                :key="prov.value"
                @click="form.provider = form.current_provider = prov.value"
                class="px-3 py-2 rounded-xl text-xs font-bold border transition text-center"
                :class="(form.provider === prov.value || form.current_provider === prov.value) ? 'bg-brand-500/10 border-brand-500 text-brand-600 dark:text-brand-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
              >
                {{ prov.label }}
              </button>
            </div>
          </div>

          <!-- Sender Identity -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">From Email Address *</label>
              <input
                v-model="form.from_address"
                type="email"
                required
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">From Sender Name *</label>
              <input
                v-model="form.from_name"
                type="text"
                required
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reply-To Address</label>
              <input
                v-model="form.reply_to"
                type="email"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
          </div>

          <!-- SMTP Options (visible when smtp chosen) -->
          <div v-if="['smtp', 'sendgrid', 'ses', 'postmark'].includes(form.provider || form.current_provider)" class="space-y-3 pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
            <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">SMTP Server Credentials</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">SMTP Host</label>
                <input
                  v-model="form.smtp_host"
                  type="text"
                  placeholder="smtp.mailgun.org or smtp.gmail.com"
                  class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">SMTP Port</label>
                <input
                  v-model.number="form.smtp_port"
                  type="number"
                  placeholder="587"
                  class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Encryption</label>
                <select
                  v-model="form.smtp_encryption"
                  class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                >
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                  <option value="none">None</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">SMTP Username</label>
                <input
                  v-model="form.smtp_username"
                  type="text"
                  class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">SMTP Password</label>
                <input
                  v-model="form.smtp_password"
                  type="password"
                  placeholder="••••••••"
                  class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
                />
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end pt-3">
            <button
              type="submit"
              :disabled="saving"
              class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ saving ? 'Saving Changes...' : 'Save Mail Configuration' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Quick Info / Mail Status Card -->
      <div class="glass-card rounded-2xl p-6 border border-slate-200/60 dark:border-slate-800/60 space-y-4">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <ShieldCheck class="w-4 h-4 text-emerald-500" />
          <span>Security & Best Practice</span>
        </h2>
        <div class="text-xs text-slate-600 dark:text-slate-300 space-y-2.5">
          <p>
            Transactional emails are dispatched asynchronously via the database queue worker (<code>queue:work</code>).
          </p>
          <p>
            When utilizing corporate Google Workspace or Microsoft 365 SMTP relay, ensure an App-Specific Password or OAuth service credential is configured.
          </p>
        </div>
      </div>
    </div>

    <!-- Deliveries Log Outbox -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div class="px-6 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Email Outbox Deliveries</h2>
        <button @click="fetchDeliveries" class="text-xs text-brand-600 hover:underline">Refresh Log</button>
      </div>

      <div v-if="!deliveries.length" class="p-8 text-center text-slate-400 text-xs">
        No recent email deliveries logged.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Recipient</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Subject</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Sent At</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <tr
              v-for="d in deliveries"
              :key="d.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3 px-4 font-medium text-slate-900 dark:text-white">
                <div>{{ d.to_name || d.to_email }}</div>
                <div class="text-[11px] text-slate-400 font-mono">{{ d.to_email }}</div>
              </td>
              <td class="py-3 px-4 text-slate-700 dark:text-slate-300 max-w-xs truncate" :title="d.subject">
                {{ d.subject }}
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="d.status === 'sent' || d.status === 'delivered' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'"
                >
                  {{ d.status }}
                </span>
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                {{ formatDate(d.sent_at || d.created_at) }}
              </td>
              <td class="py-3 px-4 text-right">
                <button
                  v-if="d.status === 'failed'"
                  @click="retryDelivery(d)"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400 hover:bg-brand-100 transition"
                >
                  <RotateCw class="w-3.5 h-3.5" />
                  <span>Retry</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Test Send Modal -->
    <div
      v-if="showTestSendModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">Send Test Diagnostic Email</h2>
          <button @click="showTestSendModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Target Email Address *</label>
          <input
            v-model="testEmailTo"
            type="email"
            placeholder="admin@example.com"
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            @click="showTestSendModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Cancel
          </button>
          <button
            @click="sendTestEmail"
            :disabled="sendingTest || !testEmailTo"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
          >
            {{ sendingTest ? 'Sending...' : 'Send Test' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import {
  Mail,
  Send,
  Activity,
  ShieldCheck,
  RotateCw,
  X,
} from 'lucide-vue-next';

const feedback = ref('');
const feedbackIsError = ref(false);
const saving = ref(false);
const testingConnection = ref(false);
const deliveries = ref([]);

const showTestSendModal = ref(false);
const testEmailTo = ref('');
const sendingTest = ref(false);

const providers = [
  { value: 'smtp', label: 'SMTP' },
  { value: 'sendgrid', label: 'SendGrid' },
  { value: 'ses', label: 'Amazon SES' },
  { value: 'postmark', label: 'Postmark' },
  { value: 'log', label: 'Local Log' },
];

const form = ref({
  provider: 'smtp',
  current_provider: 'smtp',
  from_address: '',
  from_name: '',
  reply_to: '',
  smtp_host: '',
  smtp_port: 587,
  smtp_encryption: 'tls',
  smtp_username: '',
  smtp_password: '',
});

const fetchSettings = async () => {
  try {
    const res = await axios.get('/admin/mail', {
      headers: { Accept: 'application/json' },
    });
    if (res.data) {
      const activeProvider = res.data.provider || res.data.current_provider || 'smtp';
      form.value.provider = activeProvider;
      form.value.current_provider = activeProvider;
      form.value.from_address = res.data.from_address || '';
      form.value.from_name = res.data.from_name || '';
      form.value.reply_to = res.data.reply_to || '';
      form.value.smtp_host = res.data.smtp_host || '';
      form.value.smtp_port = res.data.smtp_port || 587;
      form.value.smtp_encryption = res.data.smtp_encryption || 'tls';
      form.value.smtp_username = res.data.smtp_username || '';
    }
  } catch (err) {
    console.error('Failed to load mail settings', err);
  }
};

const fetchDeliveries = async () => {
  try {
    const res = await axios.get('/admin/mail/deliveries', {
      headers: { Accept: 'application/json' },
    });
    deliveries.value = res.data?.deliveries?.data || res.data?.deliveries || [];
  } catch (err) {
    console.error('Failed to load deliveries', err);
  }
};

const saveSettings = async () => {
  saving.value = true;
  feedback.value = '';
  feedbackIsError.value = false;
  try {
    const payload = {
      ...form.value,
      provider: form.value.provider || form.value.current_provider || 'smtp',
      current_provider: form.value.provider || form.value.current_provider || 'smtp',
    };
    await axios.post('/admin/mail', payload, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = 'Mail server settings saved successfully.';
  } catch (err) {
    feedback.value = 'Failed to save settings: ' + (err.response?.data?.message || err.message);
    feedbackIsError.value = true;
  } finally {
    saving.value = false;
  }
};

const testConnection = async () => {
  testingConnection.value = true;
  feedback.value = '';
  try {
    const res = await axios.post('/admin/mail/test-connection', {}, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = res.data?.message || 'Mail connection test successful.';
    feedbackIsError.value = !res.data?.success;
  } catch (err) {
    feedback.value = 'Connection test failed: ' + (err.response?.data?.message || err.message);
    feedbackIsError.value = true;
  } finally {
    testingConnection.value = false;
  }
};

const sendTestEmail = async () => {
  sendingTest.value = true;
  try {
    const res = await axios.post('/admin/mail/test-send', {
      to_email: testEmailTo.value,
      test_email: testEmailTo.value,
      recipient: testEmailTo.value,
    }, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = res.data?.message || `Test email dispatched to ${testEmailTo.value}.`;
    feedbackIsError.value = !res.data?.success;
    showTestSendModal.value = false;
    await fetchDeliveries();
  } catch (err) {
    feedback.value = 'Failed to send test email: ' + (err.response?.data?.message || err.message);
    feedbackIsError.value = true;
  } finally {
    sendingTest.value = false;
  }
};

const retryDelivery = async (d) => {
  try {
    await axios.post(`/admin/mail/deliveries/${d.id}/retry`, {}, {
      headers: { Accept: 'application/json' },
    });
    await fetchDeliveries();
  } catch (err) {
    console.error('Failed to retry delivery', err);
  }
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(() => {
  fetchSettings();
  fetchDeliveries();
});
</script>
