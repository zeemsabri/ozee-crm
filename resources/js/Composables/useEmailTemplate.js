import { computed } from 'vue';

/**
 * Composable to process raw HTML content for links and lists.
 * It does NOT generate a full HTML email template.
 *
 * @param {ComputedRef<string>} mainBodyHtml - A computed ref for the main email content (from EmailEditor).
 * @returns {Object} An object containing a computed ref for the processed HTML fragment.
 */
export function useEmailTemplate(mainBodyHtml) { // Removed subject and signatureHtml parameters

    /**
     * Processes the raw HTML content to convert custom link formats
     * like [Link Text]{URL} into standard HTML <a> tags.
     * @param {string} htmlContent - The HTML content from the EmailEditor.
     * @returns {string} The processed HTML content with hyperlinks.
     */
    const processLinks = (htmlContent) => {
        const linkRegex = /\[([^\]]+?)\][\s\u00A0]*\{([^}]+?)\}/g;

        return htmlContent.replace(linkRegex, (match, linkText, url) => {
            let fullUrl = url.trim();
            if (!fullUrl.startsWith('http://') && !fullUrl.startsWith('https://')) {
                fullUrl = `https://${fullUrl}`;
            }
            return `<a href="${fullUrl}" target="_blank" style="color: #1a73e8; text-decoration: underline;">${linkText.trim()}</a>`;
        });
    };

    /**
     * Processes the raw HTML content to convert structured list tags
     * like <ul><li>...</li></ul> or <ol><li>...</li></ol> into
     * properly styled HTML lists.
     * @param {string} htmlContent - The HTML content from the EmailEditor.
     * @returns {string} The processed HTML content with styled lists.
     */
    const processLists = (htmlContent) => {
        const listRegex = /<(ul|ol)>(.*?)<\/(ul|ol)>/gs;

        return htmlContent.replace(listRegex, (match, openTag, listContent) => {
            const listType = openTag;
            const listStyle = listType === 'ul' ? 'list-style-type: disc;' : 'list-style-type: decimal;';

            const listItemRegex = /<li>(.*?)<\/li>/gs;
            const processedListItems = listContent.replace(listItemRegex, (liMatch, itemContent) => {
                return `<li style="margin-bottom: 5px; font-family: 'Inter', sans-serif; font-size: 16px; line-height: 1.6; color: #1a202c;">${itemContent}</li>`;
            });

            return `<${listType} style="margin: 0; padding: 0 0 0 20px; ${listStyle}">${processedListItems}</${listType}>`;
        });
    };

    // This computed property will now return only the processed HTML fragment
    // that should be saved to the database.
    const processedHtmlBody = computed(() => {
        let processedContent = mainBodyHtml.value;

        // Apply link processing
        processedContent = processLinks(processedContent);

        // Then apply list processing
        processedContent = processLists(processedContent);

        return processedContent;
    });

    return { processedHtmlBody }; // Return only the processed HTML body
}
