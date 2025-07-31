// src/Composables/useFormatter.js

import { ref } from 'vue';

export function useFormatter() {

    /**
     * Formats a given date string into a localized date string.
     * @param {string} dateString - The date string to format.
     * @returns {string} The formatted date string.
     */
    const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        // You can customize the options for toLocaleDateString as needed
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    /**
     * Formats a given date string into a localized date and time string.
     * @param {string} dateString - The date string to format.
     * @returns {string} The formatted date and time string.
     */
    const formatDateTime = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    /**
     * Calculates the time elapsed since a given date string.
     * @param {string} dateString - The date string to calculate time from.
     * @returns {string} A human-readable string indicating time elapsed (e.g., "5 minutes ago").
     */
    const timeAgo = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        let interval = seconds / 31536000;
        if (interval > 1) {
            return Math.floor(interval) + " years ago";
        }
        interval = seconds / 2592000;
        if (interval > 1) {
            return Math.floor(interval) + " months ago";
        }
        interval = seconds / 86400;
        if (interval > 1) {
            return Math.floor(interval) + " days ago";
        }
        interval = seconds / 3600;
        if (interval > 1) {
            return Math.floor(interval) + " hours ago";
        }
        interval = seconds / 60;
        if (interval > 1) {
            return Math.floor(interval) + " minutes ago";
        }
        return Math.floor(seconds) + " seconds ago";
    };

    return {
        formatDate,
        formatDateTime,
        timeAgo
    };
}
