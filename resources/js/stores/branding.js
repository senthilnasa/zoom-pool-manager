import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useBrandingStore = defineStore('branding', () => {
    const initialBranding = window.__ZPM__?.branding || {};

    const orgName = ref(initialBranding.org_name || 'Zoom Pool Manager');
    const orgLogoUrl = ref(initialBranding.org_logo_url || '');
    const orgLogoDarkUrl = ref(initialBranding.org_logo_dark_url || '');
    const orgFaviconUrl = ref(initialBranding.org_favicon_url || '');
    const orgTagline = ref(initialBranding.org_tagline || 'Zoom Pool Manager');
    const orgPrimaryColor = ref(initialBranding.org_primary_color || '#0ea5e9');
    const orgHelpUrl = ref(initialBranding.org_help_url || '');
    const orgFooterText = ref(initialBranding.org_footer_text || '');
    const orgSupportEmail = ref(initialBranding.org_support_email || '');
    const orgWebsite = ref(initialBranding.org_website || '');
    const privacyPolicy = ref(initialBranding.privacy_policy || { type: 'none', url: '' });
    const terms = ref(initialBranding.terms || { type: 'none', url: '' });

    const applyFavicon = (url) => {
        if (typeof document === 'undefined') return;
        let link = document.querySelector("link[rel*='icon']");
        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            document.head.appendChild(link);
        }
        link.href = url || '/favicon.svg';
    };

    const applyPrimaryColor = (color) => {
        if (typeof document === 'undefined' || !color) return;
        document.documentElement.style.setProperty('--brand-primary', color);
    };

    // Initialize DOM with initial settings if present
    if (orgFaviconUrl.value) {
        applyFavicon(orgFaviconUrl.value);
    }
    if (orgPrimaryColor.value) {
        applyPrimaryColor(orgPrimaryColor.value);
    }

    const updateBranding = (data) => {
        if (data.org_name !== undefined) {
            orgName.value = data.org_name || 'Zoom Pool Manager';
        }
        if (data.org_logo_url !== undefined) {
            orgLogoUrl.value = data.org_logo_url;
        }
        if (data.org_logo_dark_url !== undefined) {
            orgLogoDarkUrl.value = data.org_logo_dark_url;
        }
        if (data.org_favicon_url !== undefined) {
            orgFaviconUrl.value = data.org_favicon_url;
            applyFavicon(data.org_favicon_url);
        }
        if (data.org_tagline !== undefined) {
            orgTagline.value = data.org_tagline || 'Zoom Pool Manager';
        }
        if (data.org_primary_color !== undefined) {
            orgPrimaryColor.value = data.org_primary_color || '#0ea5e9';
            applyPrimaryColor(orgPrimaryColor.value);
        }
        if (data.org_help_url !== undefined) {
            orgHelpUrl.value = data.org_help_url;
        }
        if (data.org_footer_text !== undefined) {
            orgFooterText.value = data.org_footer_text;
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
        orgLogoDarkUrl,
        orgFaviconUrl,
        orgTagline,
        orgPrimaryColor,
        orgHelpUrl,
        orgFooterText,
        orgSupportEmail,
        orgWebsite,
        privacyPolicy,
        terms,
        applyFavicon,
        applyPrimaryColor,
        updateBranding,
        fetchBranding,
    };
});
