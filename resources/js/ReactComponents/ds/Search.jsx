/**
 * Search — ported from the OZee design system bundle (components/forms/TextField.jsx).
 *
 * A TextField with a leading magnifier and a trailing clear button. `onChange` receives
 * the raw input event, matching TextField; `onClear` is called with no argument.
 */

import { TextField } from './Fields';
import { Icon } from './Icon';

export function Search({
    value = '',
    onChange,
    onClear,
    placeholder = 'Search',
    size = 'medium',
    ariaLabel,
    style,
}) {
    return (
        <div style={{ position: 'relative', width: '100%', ...style }}>
            <TextField
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                size={size}
                iconName="Search"
                ariaLabel={ariaLabel || placeholder}
            />
            {value ? (
                <button
                    type="button"
                    aria-label="Clear search"
                    onClick={onClear}
                    style={{
                        position: 'absolute',
                        insetInlineEnd: 6,
                        top: '50%',
                        transform: 'translateY(-50%)',
                        width: 24,
                        height: 24,
                        border: 'none',
                        background: 'transparent',
                        cursor: 'pointer',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: 'var(--icon-color)',
                    }}
                >
                    <Icon name="CloseSmall" size={16} />
                </button>
            ) : null}
        </div>
    );
}
