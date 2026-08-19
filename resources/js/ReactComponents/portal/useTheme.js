/**
 * The portal shell's theme hook now lives in ReactComponents/app/useTheme.js, shared
 * with the authenticated app shell so both use one storage key and one data-attribute
 * contract. Re-exported here so the portal's existing imports keep working.
 */

export { useTheme, THEME_OPTIONS } from '../app/useTheme';
