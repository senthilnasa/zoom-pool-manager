import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useBrandingStore = defineStore('branding', () => {
    const initialBranding = window.__ZPM__?.branding || {};

    const orgName = ref(initialBranding.org_name || 'Zoom Pool Manager');
    const orgLogoUrl = ref(initialBranding.org_logo_url || '');
    const orgSupportEmail = ref(initialBranding.org_support_email || '');
    const orgWebsite = ref(initialBranding.org_website || '');
    const privacyPolicy = ref(initialBranding.privacy_policy || { type: 'none', url: '' });
    const terms = ref(initialBranding.terms || { type: 'none', url: '' });

    const updateBranding = (data) => {
        if (data.org_name !== undefined) {
            orgName.value = data.org_name || 'Zoom Pool Manager';
        }
        if (data.org_logo_url !== undefined) {
            orgLogoUrl.value = data.org_logo_url;
        }
        if (data.org_support_email !== undefined) {
            orgSupportEmail.value = data.org_support_email;
        }
        if (data.org_website !== undefined) {
            orgWebsite.value = data.org_website;
        }
        if (data.privacy_policy !== undefined) {
            privacyPolicy.value = { ...privacyPolicy.value, ...data.privacy_policy };
        } else if (data.privacy_policy_type !== undefined) {
            privacyPolicy.value = {
                type: data.privacy_policy_type,
                url: data.privacy_policy_url || '',
            };
        }
        if (data.terms !== undefined) {
            terms.value = { ...terms.value, ...data.terms };
        } else if (data.terms_type !== undefined) {
            terms.value = {
                type: data.terms_type,
                url: data.terms_url || '',
            };
        }

        // Update document title if needed
        const pageTitle = document.title.split('—')[0]?.trim();
        if (pageTitle && pageTitle !== orgName.value) {
            document.title = `${pageTitle} — ${orgName.value}`;
        }
    };

    const fetchBranding = async () => {
        try {
            const res = await axios.get('/spa/branding');
            if (res.data) {
                updateBranding(res.data);
            }
        } catch (e) {
            // Silently retain initial branding
        }
    };

    return {
        orgName,
        orgLogoUrl,
        orgSupportEmail,
        orgWebsite,
        privacyPolicy,
        terms,
        updateBranding,
        fetchBranding,
    };
});
