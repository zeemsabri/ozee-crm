import { computed } from 'vue';
import { useAuthUser } from '@/Directives/permissions.js';

/**
 * Composable to generate a user's email signature HTML.
 * The signature includes user details, social media links,
 * an environmental message, and a review prompt.
 *
 * @param {ComputedRef<Object|null>} projectRole - A computed ref for the user's project-specific role.
 * @returns {Object} An object containing a computed ref for the user's signature.
 */
export function useEmailSignature(projectRole) { // Accept projectRole as a parameter
    const authUser = useAuthUser();
    const userSignature = computed(() => {
        const userName = authUser.value && authUser.value.name ? authUser.value.name : '';
        // Use the projectRole if available, otherwise fallback to a generic title
        const userTitle = projectRole.name ?? '';
        const userPhone = '+61 456 639 389'; // Updated phone number
        const userWebsite = 'ozeeweb.com.au'; // Updated website

        // Brand Colors
        const brandPrimaryColor = '#1a73e8'; // A vibrant blue, common in modern branding
        const brandSecondaryColor = '#fbbc05'; // A vibrant yellow/orange for accents
        const textColorPrimary = '#1a202c'; // Dark grey for main text
        const textColorSecondary = '#4a5568'; // Medium grey for secondary text
        const borderColor = '#e5e7eb'; // Light grey for borders
        const backgroundColor = '#f9fafb'; // Light background for the signature block

        // Your company logo from the public directory.
        const companyLogoUrl = `${window.location.origin}/logo.png`;

        // Placeholder for social media icons. Using a brand-consistent color.
        const facebookIconUrl = `https://img.icons8.com/ios-filled/20/${brandPrimaryColor.substring(1)}/facebook-new.png`;
        const twitterIconUrl = `https://img.icons8.com/ios-filled/20/${brandPrimaryColor.substring(1)}/twitter.png`;
        const linkedinIconUrl = `https://img.icons8.com/ios-filled/20/${brandPrimaryColor.substring(1)}/linkedin.png`;
        const instagramIconUrl = `https://img.icons8.com/ios-filled/20/${brandPrimaryColor.substring(1)}/instagram-new.png`;


        return `
            <div style="font-family: 'Inter', sans-serif; padding: 20px; border: 1px solid ${borderColor}; margin-top: 30px; background-color: ${backgroundColor}; border-radius: 8px; overflow: hidden; box-shadow: 0 0 0 3px ${brandSecondaryColor};">
                <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="vertical-align: middle;">
                            <p style="margin: 0; font-size: 18px; color: ${textColorPrimary};">Best Regards</p>
                            <p style="margin: 0; font-size: 18px; font-weight: bold; color: ${textColorPrimary};">${userName}</p>
                            <p style="margin: 5px 0 5px 0; font-size: 14px; color: ${textColorSecondary};">${userTitle}</p>
                            <p style="margin: 0 0 15px 0; font-size: 12px; color: ${textColorSecondary};">
                                Your website and social media are like your home, first impressions matter!
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding-right: 15px;">
                                        <p style="margin: 0; font-size: 13px; color: ${textColorSecondary};">📞 ${userPhone}</p>
                                        <p style="margin: 5px 0 0 0; font-size: 13px; color: ${textColorSecondary};">🌐 <a href="https://${userWebsite}" target="_blank" style="color: ${brandPrimaryColor}; text-decoration: none;">${userWebsite}</a></p>
                                    </td>
                                </tr>
                            </table>
                            <div style="margin-top: 15px; text-align: left;">
                                <a href="https://www.facebook.com/yourprofile" target="_blank" style="display: inline-block; margin-right: 8px; color: ${brandPrimaryColor}; text-decoration: none;">
                                    <img src="${facebookIconUrl}" alt="Facebook" style="vertical-align: middle;">
                                </a>
                                <a href="https://twitter.com/yourhandle" target="_blank" style="display: inline-block; margin-right: 8px; color: ${brandPrimaryColor}; text-decoration: none;">
                                    <img src="${twitterIconUrl}" alt="Twitter" style="vertical-align: middle;">
                                </a>
                                <a href="https://www.linkedin.com/in/yourprofile" target="_blank" style="display: inline-block; margin-right: 8px; color: ${brandPrimaryColor}; text-decoration: none;">
                                    <img src="${linkedinIconUrl}" alt="LinkedIn" style="vertical-align: middle;">
                                </a>
                                <a href="https://www.instagram.com/yourprofile" target="_blank" style="display: inline-block; margin-right: 8px; color: ${brandPrimaryColor}; text-decoration: none;">
                                    <img src="${instagramIconUrl}" alt="Instagram" style="vertical-align: middle;">
                                </a>
                            </div>
                        </td>
                        <td style="padding-left: 20px; vertical-align: middle; width: 120px; text-align: right;">
                            <img src="${companyLogoUrl}" alt="Company Logo" width="100" style="display: block; border-radius: 8px; margin-left: auto;">
                        </td>
                    </tr>
                </table>

                <p style="margin-top: 25px; font-size: 12px; color: ${textColorSecondary}; text-align: center;">
                    Powered by MMS IT & Web Solutions PTY Ltd.
                </p>
                <p style="margin-top: 10px; font-style: italic; color: ${textColorSecondary}; text-align: center; font-size: 13px;">
                    Please consider the environment before printing this email.
                </p>
                <p style="margin-top: 10px; text-align: center; font-size: 13px;">
                    How did I do? <a href="https://www.example.com/review" target="_blank" style="color: ${brandPrimaryColor}; text-decoration: none; font-weight: bold;">Leave a review!</a>
                </p>
            </div>
        `;
    });

    return { userSignature };
}
