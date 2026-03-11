/**
 * Formats a message string containing mentions in the format @{id:name}
 * into a string with HTML spans for mentions.
 * 
 * @param {string} message 
 * @returns {string}
 */
export const formatMentions = (message) => {
    if (!message) return '';
    
    // Escape HTML to prevent XSS
    let escaped = message
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    // Replace @{id:name} with a styled span
    return escaped.replace(/@\{(\d+):([^}]+)\}/g, (match, id, name) => {
        return `<span class="bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-md font-bold text-[0.95em] border border-indigo-200">@${name}</span>`;
    });
};
