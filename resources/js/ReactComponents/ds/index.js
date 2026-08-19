/**
 * OZee CRM design system — React primitives.
 *
 * Ported from Redesign/proposal/_ds/vibe-monday-3d3bcd2f-2617-45d4-830a-b39427322625/_ds_bundle.js,
 * which is a browser-global IIFE bundle and can't be imported by Vite. These are the same
 * components rewritten as ES modules so redesigned React pages can share them.
 *
 * They style themselves entirely from the CSS custom properties in
 * resources/css/ozee-ds — import that stylesheet once per page (see
 * resources/js/ReactPages/Portal/Project.jsx) or nothing will have colour.
 *
 * Only the components the guest proposals page needed are ported so far. The bundle has
 * ~54 more (Table, Menu, DatePicker, Accordion, Steps, …) — port them here as pages need
 * them rather than all at once.
 */

export { Icon, IconButton } from './Icon';
export { Button, ButtonGroup } from './Button';
export { FieldShell, TextField, TextArea, Dropdown, RadioButton } from './Fields';
export { Label, Counter, Tabs, ProgressBar, Avatar } from './Display';
export { AttentionBox, Toast, Modal } from './Feedback';
