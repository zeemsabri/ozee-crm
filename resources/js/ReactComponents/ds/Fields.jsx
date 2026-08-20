/**
 * Form primitives — FieldShell, TextField, TextArea, Dropdown, RadioButton.
 * Ported from the OZee design system bundle.
 *
 * Border colour ladder (resting -> hover -> focus -> validation) is the system's,
 * so fields read the same as every other redesigned page.
 */

import { useState } from 'react';
import { Icon } from './Icon';
import { Popover, useAnchoredPopover } from './Popover';

const H = { small: 32, medium: 40, large: 48 };

export function FieldShell({ label, required, subText, validation, children, style }) {
    return (
        <div style={{ width: '100%', ...style }}>
            {label ? (
                <label
                    style={{
                        display: 'block',
                        font: 'var(--font-text2-normal)',
                        color: 'var(--primary-text-color)',
                        paddingBlock: 'var(--space-4)',
                    }}
                >
                    {label}
                    {required ? <span style={{ color: 'var(--negative-color)' }}> *</span> : null}
                </label>
            ) : null}
            {children}
            {subText || validation ? (
                <div
                    style={{
                        display: 'flex',
                        paddingBlock: 1,
                        font: 'var(--font-text3-normal)',
                        color:
                            validation === 'error'
                                ? 'var(--negative-color)'
                                : validation === 'success'
                                  ? 'var(--positive-color)'
                                  : 'var(--secondary-text-color)',
                    }}
                >
                    {subText}
                </div>
            ) : null}
        </div>
    );
}

function borderColor(validation, focus, hover, disabled) {
    if (disabled) return 'transparent';
    if (validation === 'error') return 'var(--negative-color)';
    if (validation === 'success') return 'var(--positive-color)';
    if (focus) return 'var(--primary-color)';
    if (hover) return 'var(--primary-text-color)';
    return 'var(--ui-border-color)';
}

export function TextField({
    value,
    onChange,
    placeholder = '',
    label,
    size = 'medium',
    iconName,
    trailingIconName,
    disabled = false,
    readOnly = false,
    required = false,
    validation,
    subText,
    type = 'text',
    style,
    wrapperStyle,
    ...rest
}) {
    const [focus, setFocus] = useState(false);
    const [hover, setHover] = useState(false);
    const h = H[size] || 40;
    const font = size === 'small' ? 'var(--font-text2-normal)' : 'var(--font-text1-normal)';

    return (
        <FieldShell
            label={label}
            required={required}
            subText={subText}
            validation={validation}
            style={wrapperStyle}
        >
            <div
                style={{ position: 'relative', height: h, width: '100%' }}
                onMouseEnter={() => setHover(true)}
                onMouseLeave={() => setHover(false)}
            >
                {iconName ? (
                    <Icon
                        name={iconName}
                        size={16}
                        color="var(--icon-color)"
                        style={{
                            position: 'absolute',
                            insetInlineStart: 10,
                            top: '50%',
                            transform: 'translateY(-50%)',
                            pointerEvents: 'none',
                        }}
                    />
                ) : null}
                <input
                    type={type}
                    value={value ?? ''}
                    onChange={onChange}
                    placeholder={placeholder}
                    disabled={disabled}
                    readOnly={readOnly}
                    onFocus={() => setFocus(true)}
                    onBlur={() => setFocus(false)}
                    style={{
                        width: '100%',
                        height: '100%',
                        outline: 0,
                        font,
                        color: disabled ? 'var(--disabled-text-color)' : 'var(--primary-text-color)',
                        background: disabled
                            ? 'var(--disabled-background-color)'
                            : readOnly
                              ? 'var(--allgrey-background-color)'
                              : 'var(--secondary-background-color)',
                        border: readOnly || disabled ? 'none' : '1px solid',
                        borderColor: borderColor(validation, focus, hover, disabled),
                        borderRadius: 'var(--border-radius-small)',
                        transition: 'border-color var(--motion-productive-medium) ease-in',
                        padding: iconName
                            ? 'var(--space-8) var(--space-12) var(--space-8) 32px'
                            : 'var(--space-8) var(--space-12)',
                        textOverflow: 'ellipsis',
                        ...style,
                    }}
                    {...rest}
                />
                {trailingIconName ? (
                    <Icon
                        name={trailingIconName}
                        size={16}
                        color="var(--icon-color)"
                        style={{
                            position: 'absolute',
                            insetInlineEnd: 10,
                            top: '50%',
                            transform: 'translateY(-50%)',
                        }}
                    />
                ) : null}
            </div>
        </FieldShell>
    );
}

export function TextArea({
    value,
    onChange,
    placeholder = '',
    label,
    rows = 4,
    disabled = false,
    validation,
    subText,
    maxLength,
    style,
    ...rest
}) {
    const [focus, setFocus] = useState(false);
    const [hover, setHover] = useState(false);

    return (
        <FieldShell label={label} subText={subText} validation={validation}>
            <textarea
                value={value ?? ''}
                onChange={onChange}
                rows={rows}
                placeholder={placeholder}
                disabled={disabled}
                maxLength={maxLength}
                onFocus={() => setFocus(true)}
                onBlur={() => setFocus(false)}
                onMouseEnter={() => setHover(true)}
                onMouseLeave={() => setHover(false)}
                style={{
                    display: 'block',
                    width: '100%',
                    outline: 0,
                    resize: 'vertical',
                    font: 'var(--font-text1-normal)',
                    color: 'var(--primary-text-color)',
                    background: disabled
                        ? 'var(--disabled-background-color)'
                        : 'var(--secondary-background-color)',
                    border: '1px solid',
                    borderColor: borderColor(validation, focus, hover, disabled),
                    borderRadius: 'var(--border-radius-small)',
                    padding: 'var(--space-8) var(--space-12)',
                    transition: 'border-color var(--motion-productive-medium) ease-in',
                    ...style,
                }}
                {...rest}
            />
        </FieldShell>
    );
}

function DialogContentContainer({ children, size = 'medium', style }) {
    return (
        <div
            style={{
                background: 'var(--dialog-background-color)',
                borderRadius:
                    size === 'large' ? 'var(--border-radius-medium)' : 'var(--border-radius-small)',
                boxShadow: 'var(--box-shadow-medium)',
                padding: 'var(--space-8)',
                ...style,
            }}
        >
            {children}
        </div>
    );
}

export function Dropdown({
    options = [],
    value,
    onChange,
    placeholder = 'Select',
    label,
    size = 'medium',
    disabled = false,
    clearable = false,
    multi = false,
    searchable = false,
    style,
}) {
    const [hover, setHover] = useState(false);
    const [query, setQuery] = useState('');

    /*
     * The option list is portalled to document.body rather than positioned inside this
     * field. An absolutely-positioned menu is clipped by any `overflow: hidden|auto`
     * ancestor, and this app has several exactly where dropdowns live — Modal's scrolling
     * body, Modal's dialog, the reply composer. A template picker low in a dialog was
     * cut off at the container edge with no way to reach the rest of the list.
     *
     * useAnchoredPopover also owns outside-click and Escape, because the panel is no
     * longer inside `anchorRef` and a naive `ref.contains(e.target)` check would treat
     * clicking an option as a click outside.
     */
    const { open, setOpen, anchorRef, panelRef, position } = useAnchoredPopover({
        preferredHeight: 260,
        matchWidth: true,
        onClose: () => setQuery(''),
    });

    const selected = multi
        ? options.filter((o) => (value || []).includes(o.value))
        : options.find((o) => o.value === value);
    const shown =
        searchable && query
            ? options.filter((o) => String(o.label).toLowerCase().includes(query.toLowerCase()))
            : options;
    const h = H[size] || 40;
    const hasValue = multi ? selected.length > 0 : Boolean(selected);

    const pick = (o) => {
        if (multi) {
            const set = new Set(value || []);
            if (set.has(o.value)) set.delete(o.value);
            else set.add(o.value);
            onChange && onChange([...set]);
        } else {
            onChange && onChange(o.value);
            setOpen(false);
        }
    };

    return (
        <div ref={anchorRef} style={{ position: 'relative', width: '100%', ...style }}>
            {label ? (
                <label
                    style={{
                        display: 'block',
                        font: 'var(--font-text2-normal)',
                        paddingBlock: 'var(--space-4)',
                    }}
                >
                    {label}
                </label>
            ) : null}
            <div
                role="button"
                tabIndex={0}
                aria-expanded={open}
                onMouseEnter={() => setHover(true)}
                onMouseLeave={() => setHover(false)}
                onClick={() => !disabled && setOpen((o) => !o)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        if (!disabled) setOpen((o) => !o);
                    }
                    if (e.key === 'Escape') setOpen(false);
                }}
                style={{
                    minHeight: h,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 'var(--space-4)',
                    padding: '0 var(--space-8) 0 var(--space-12)',
                    border: '1px solid',
                    borderColor: disabled
                        ? 'transparent'
                        : open
                          ? 'var(--primary-color)'
                          : hover
                            ? 'var(--primary-text-color)'
                            : 'var(--ui-border-color)',
                    borderRadius: 'var(--border-radius-small)',
                    background: disabled
                        ? 'var(--disabled-background-color)'
                        : 'var(--secondary-background-color)',
                    cursor: disabled ? 'not-allowed' : 'pointer',
                    font: size === 'small' ? 'var(--font-text2-normal)' : 'var(--font-text1-normal)',
                    color: 'var(--primary-text-color)',
                    transition: 'border-color var(--motion-productive-medium) ease-in',
                }}
            >
                <span
                    style={{
                        flex: 1,
                        display: 'flex',
                        gap: 'var(--space-4)',
                        flexWrap: 'wrap',
                        alignItems: 'center',
                        overflow: 'hidden',
                        paddingBlock: multi && hasValue ? 4 : 0,
                        color: hasValue ? 'var(--primary-text-color)' : 'var(--placeholder-color)',
                    }}
                >
                    {multi
                        ? hasValue
                            ? selected.map((s) => (
                                  <span
                                      key={s.value}
                                      style={{
                                          display: 'inline-flex',
                                          alignItems: 'center',
                                          gap: 4,
                                          height: 24,
                                          padding: '0 var(--space-8)',
                                          borderRadius: 4,
                                          background: 'var(--primary-selected-color)',
                                          font: 'var(--font-text2-normal)',
                                      }}
                                  >
                                      {s.label}
                                  </span>
                              ))
                            : placeholder
                        : selected
                          ? selected.label
                          : placeholder}
                </span>
                {clearable && hasValue ? (
                    <span
                        role="button"
                        aria-label="Clear selection"
                        onClick={(e) => {
                            e.stopPropagation();
                            onChange && onChange(multi ? [] : null);
                        }}
                        style={{ display: 'inline-flex' }}
                    >
                        <Icon name="CloseSmall" size={16} color="var(--icon-color)" />
                    </span>
                ) : null}
                <Icon
                    name="DropdownChevronDown"
                    size={16}
                    color="var(--icon-color)"
                    style={{
                        transform: open ? 'rotate(180deg)' : 'none',
                        transition: 'transform var(--motion-productive-medium)',
                    }}
                />
            </div>
            {open ? (
                <Popover position={position} panelRef={panelRef}>
                    <DialogContentContainer
                        style={{
                            // Height comes from the measured space below (or above, when
                            // flipped), so the list always ends on screen.
                            maxHeight: position?.maxHeight ?? 260,
                            overflow: 'auto',
                        }}
                    >
                        {searchable ? (
                            <input
                                autoFocus
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search"
                                style={{
                                    width: '100%',
                                    height: 32,
                                    marginBottom: 4,
                                    border: '1px solid var(--ui-border-color)',
                                    borderRadius: 'var(--border-radius-small)',
                                    padding: '0 var(--space-8)',
                                    font: 'var(--font-text2-normal)',
                                    background: 'var(--secondary-background-color)',
                                    color: 'var(--primary-text-color)',
                                    outline: 'none',
                                }}
                            />
                        ) : null}
                        {shown.map((o) => {
                            const isSel = multi ? (value || []).includes(o.value) : o.value === value;
                            return (
                                <div
                                    key={o.value}
                                    onClick={() => pick(o)}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 'var(--space-8)',
                                        minHeight: 32,
                                        padding: '0 var(--space-8)',
                                        borderRadius: 'var(--border-radius-small)',
                                        font: 'var(--font-text2-normal)',
                                        color: 'var(--primary-text-color)',
                                        background: isSel ? 'var(--primary-selected-color)' : 'transparent',
                                        cursor: 'pointer',
                                    }}
                                    onMouseEnter={(e) => {
                                        if (!isSel)
                                            e.currentTarget.style.background =
                                                'var(--primary-background-hover-color)';
                                    }}
                                    onMouseLeave={(e) => {
                                        if (!isSel) e.currentTarget.style.background = 'transparent';
                                    }}
                                >
                                    {o.icon ? <Icon name={o.icon} size={16} color="var(--icon-color)" /> : null}
                                    {o.color ? (
                                        <span
                                            style={{
                                                width: 10,
                                                height: 10,
                                                flex: 'none',
                                                borderRadius: '50%',
                                                background: o.color,
                                            }}
                                        />
                                    ) : null}
                                    <span style={{ flex: 1 }}>{o.label}</span>
                                    {isSel ? <Icon name="Check" size={14} color="var(--primary-color)" /> : null}
                                </div>
                            );
                        })}
                        {shown.length === 0 ? (
                            <div
                                style={{
                                    padding: 'var(--space-8)',
                                    font: 'var(--font-text2-normal)',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                No results
                            </div>
                        ) : null}
                    </DialogContentContainer>
                </Popover>
            ) : null}
        </div>
    );
}

export function RadioButton({ label, name, value, checked, disabled = false, onChange, style }) {
    const [hover, setHover] = useState(false);

    return (
        <label
            onMouseEnter={() => setHover(true)}
            onMouseLeave={() => setHover(false)}
            style={{
                display: 'grid',
                gridTemplateColumns: '1.5em auto',
                gridGap: '0.5em',
                alignItems: 'center',
                cursor: disabled ? 'not-allowed' : 'pointer',
                font: 'var(--font-text2-normal)',
                color: disabled ? 'var(--disabled-text-color)' : 'var(--primary-text-color)',
                ...style,
            }}
        >
            <span style={{ display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <input
                    type="radio"
                    name={name}
                    value={value}
                    checked={!!checked}
                    disabled={disabled}
                    onChange={onChange}
                    style={{ opacity: 0, width: 0, height: 0, margin: 0 }}
                />
                <span
                    style={{
                        width: '1em',
                        height: '1em',
                        boxSizing: 'border-box',
                        borderRadius: '50%',
                        border: checked ? '0.3em solid' : '0.1em solid',
                        borderColor: disabled
                            ? 'var(--disabled-background-color)'
                            : checked
                              ? hover
                                  ? 'var(--primary-hover-color)'
                                  : 'var(--primary-color)'
                              : hover
                                ? 'var(--primary-text-color)'
                                : 'var(--ui-border-color)',
                        background: 'var(--secondary-background-color)',
                        transition:
                            'border-width var(--motion-productive-medium) var(--motion-timing-enter)',
                    }}
                />
            </span>
            <span>{label}</span>
        </label>
    );
}
