/**
 * OZee CRM design system — React primitives.
 *
 * Ported from Redesign/.../_ds/vibe-monday-3d3bcd2f-2617-45d4-830a-b39427322625/_ds_bundle.js,
 * which is a browser-global IIFE bundle and can't be imported by Vite. These are the same
 * components rewritten as ES modules so redesigned React pages can share them.
 *
 * They style themselves entirely from the CSS custom properties in
 * resources/css/ozee-ds — import that stylesheet once per page (or use the AppShell in
 * ReactComponents/app, which imports it for you) or nothing will have colour.
 *
 * The bundle has ~54 components; port more here as pages need them rather than all at
 * once, matching the bundle's implementation instead of inventing new ones.
 */

export { Icon, IconButton } from './Icon';
export { Button, ButtonGroup } from './Button';
export { FieldShell, TextField, TextArea, Dropdown, RadioButton } from './Fields';
export { Checkbox, Toggle } from './Toggles';
export { Search } from './Search';
export { Chips } from './Chips';
export { Label, Counter, Tabs, ProgressBar, Avatar } from './Display';
export { AttentionBox, Toast, Modal } from './Feedback';
export { AlertBanner, EmptyState, Loader, Skeleton, Divider } from './States';
export { DialogContentContainer, Menu, MenuButton } from './Menu';
// Anchored, portalled panels. Dropdown and MenuButton use this; reach for it
// rather than position:absolute, which gets clipped inside modals and the composer.
export { Popover, useAnchoredPopover } from './Popover';
