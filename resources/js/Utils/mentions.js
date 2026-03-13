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
    let formatted = escaped.replace(/@\{(\d+):([^}]+)\}/g, (match, id, name) => {
        return `<span class="bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-md font-bold text-[0.95em] border border-indigo-200">@${name}</span>`;
    });

    // Replace #{id:task_number:title} or #{id:task_number} with a styled clickable span for tasks
    return formatted.replace(/#\{(\d+):([^:]+)(?::([^}]+))?\}/g, (match, id, taskNumber, title) => {
        const displayLabel = title ? `#${taskNumber} ${title}` : `#${taskNumber}`;
        return `<span class="task-mention bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded-md font-bold text-[0.95em] border border-emerald-200 cursor-pointer" data-task-id="${id}">${displayLabel}</span>`;
    });
};
