/* @ds-bundle: {"format":4,"namespace":"OZeeCRMDesignSystem_3d3bcd","components":[{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Icon","sourcePath":"components/core/Icon.jsx"},{"name":"IconButton","sourcePath":"components/core/Icon.jsx"},{"name":"ButtonGroup","sourcePath":"components/core/Icon.jsx"},{"name":"SplitButton","sourcePath":"components/core/Icon.jsx"},{"name":"Link","sourcePath":"components/core/Link.jsx"},{"name":"Avatar","sourcePath":"components/data/Avatar.jsx"},{"name":"AvatarGroup","sourcePath":"components/data/Avatar.jsx"},{"name":"Chips","sourcePath":"components/data/Chips.jsx"},{"name":"Label","sourcePath":"components/data/Chips.jsx"},{"name":"Counter","sourcePath":"components/data/Chips.jsx"},{"name":"Badge","sourcePath":"components/data/Chips.jsx"},{"name":"Table","sourcePath":"components/data/Table.jsx"},{"name":"List","sourcePath":"components/data/Table.jsx"},{"name":"ListTitle","sourcePath":"components/data/Table.jsx"},{"name":"ListItem","sourcePath":"components/data/Table.jsx"},{"name":"Toast","sourcePath":"components/feedback/Toast.jsx"},{"name":"AlertBanner","sourcePath":"components/feedback/Toast.jsx"},{"name":"AttentionBox","sourcePath":"components/feedback/Toast.jsx"},{"name":"Tipseen","sourcePath":"components/feedback/Toast.jsx"},{"name":"Tooltip","sourcePath":"components/feedback/Tooltip.jsx"},{"name":"Info","sourcePath":"components/feedback/Tooltip.jsx"},{"name":"Loader","sourcePath":"components/feedback/Tooltip.jsx"},{"name":"Skeleton","sourcePath":"components/feedback/Tooltip.jsx"},{"name":"EmptyState","sourcePath":"components/feedback/Tooltip.jsx"},{"name":"Checkbox","sourcePath":"components/forms/Checkbox.jsx"},{"name":"RadioButton","sourcePath":"components/forms/Checkbox.jsx"},{"name":"Toggle","sourcePath":"components/forms/Checkbox.jsx"},{"name":"DatePicker","sourcePath":"components/forms/DatePicker.jsx"},{"name":"DialogContentContainer","sourcePath":"components/forms/Dropdown.jsx"},{"name":"Dropdown","sourcePath":"components/forms/Dropdown.jsx"},{"name":"Combobox","sourcePath":"components/forms/Dropdown.jsx"},{"name":"Slider","sourcePath":"components/forms/Slider.jsx"},{"name":"ProgressBar","sourcePath":"components/forms/Slider.jsx"},{"name":"ColorPicker","sourcePath":"components/forms/Slider.jsx"},{"name":"TextField","sourcePath":"components/forms/TextField.jsx"},{"name":"TextArea","sourcePath":"components/forms/TextField.jsx"},{"name":"Search","sourcePath":"components/forms/TextField.jsx"},{"name":"NumberField","sourcePath":"components/forms/TextField.jsx"},{"name":"Menu","sourcePath":"components/navigation/Menu.jsx"},{"name":"MenuButton","sourcePath":"components/navigation/Menu.jsx"},{"name":"Steps","sourcePath":"components/navigation/Menu.jsx"},{"name":"MultiStepIndicator","sourcePath":"components/navigation/Menu.jsx"},{"name":"Tabs","sourcePath":"components/navigation/Tabs.jsx"},{"name":"BreadcrumbsBar","sourcePath":"components/navigation/Tabs.jsx"},{"name":"Divider","sourcePath":"components/navigation/Tabs.jsx"},{"name":"Accordion","sourcePath":"components/navigation/Tabs.jsx"},{"name":"ExpandCollapse","sourcePath":"components/navigation/Tabs.jsx"},{"name":"Modal","sourcePath":"components/overlays/Modal.jsx"},{"name":"Heading","sourcePath":"components/typography/Heading.jsx"},{"name":"Text","sourcePath":"components/typography/Heading.jsx"},{"name":"TextWithHighlight","sourcePath":"components/typography/Heading.jsx"},{"name":"FormattedNumber","sourcePath":"components/typography/Heading.jsx"},{"name":"EditableText","sourcePath":"components/typography/Heading.jsx"},{"name":"EditableHeading","sourcePath":"components/typography/Heading.jsx"}],"sourceHashes":{"components/core/Button.jsx":"b6fbe43577fc","components/core/Icon.jsx":"2c616fb87f8b","components/core/Link.jsx":"009c8c996bf1","components/data/Avatar.jsx":"d2e782833977","components/data/Chips.jsx":"fdf4457cb0de","components/data/Table.jsx":"6b758ee77cdc","components/feedback/Toast.jsx":"bede215cda85","components/feedback/Tooltip.jsx":"461b013d4288","components/forms/Checkbox.jsx":"543ddf390456","components/forms/DatePicker.jsx":"748665061c17","components/forms/Dropdown.jsx":"8cba82fe6a64","components/forms/Slider.jsx":"bc912b5b252e","components/forms/TextField.jsx":"b778ce10ee14","components/navigation/Menu.jsx":"54c15da284ed","components/navigation/Tabs.jsx":"edb96d45c573","components/overlays/Modal.jsx":"b2ee82d7fe70","components/typography/Heading.jsx":"f354931ea25a","ui_kits/crm/Approvals.jsx":"440ea65c5240","ui_kits/crm/Dashboard.jsx":"16a4e39aade3","ui_kits/crm/Login.jsx":"c63740eaa9e2","ui_kits/crm/ProjectDetail.jsx":"5ff523cd2c08","ui_kits/crm/Projects.jsx":"c9dad72e2942","ui_kits/crm/Shell.jsx":"1d442e407142","ui_kits/crm/data.js":"a1c28288f605"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.OZeeCRMDesignSystem_3d3bcd = window.OZeeCRMDesignSystem_3d3bcd || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const SIZES = {
  xxs: {
    height: 16,
    padding: "2px var(--space-4)",
    font: "var(--font-text2-normal)",
    line: "16px"
  },
  xs: {
    height: 24,
    padding: "var(--space-4) var(--space-8)",
    font: "var(--font-text2-normal)",
    line: "21px"
  },
  small: {
    height: 32,
    padding: "var(--space-4) var(--space-8)",
    font: "var(--font-text2-normal)",
    line: "24px"
  },
  medium: {
    height: 40,
    padding: "var(--space-8) var(--space-16)",
    font: "var(--font-text1-normal)",
    line: "22px"
  },
  large: {
    height: 48,
    padding: "12px var(--space-24)",
    font: "var(--font-text1-normal)",
    line: "22px"
  }
};
const FILL = {
  primary: {
    bg: "var(--primary-color)",
    hover: "var(--primary-hover-color)",
    fg: "var(--text-color-on-primary)"
  },
  brand: {
    bg: "var(--brand-color)",
    hover: "var(--brand-hover-color)",
    fg: "var(--text-color-on-brand)"
  },
  positive: {
    bg: "var(--positive-color)",
    hover: "var(--positive-color-hover)",
    fg: "var(--text-color-on-primary)"
  },
  negative: {
    bg: "var(--negative-color)",
    hover: "var(--negative-color-hover)",
    fg: "var(--text-color-on-primary)"
  },
  inverted: {
    bg: "var(--inverted-color-background)",
    hover: "var(--placeholder-color)",
    fg: "var(--text-color-on-inverted)"
  }
};
const OUTLINE_FG = {
  primary: "var(--primary-text-color)",
  brand: "var(--primary-text-color)",
  positive: "var(--positive-color)",
  negative: "var(--negative-color)",
  inverted: "var(--primary-text-color)"
};
function Button({
  children,
  kind = "primary",
  color = "primary",
  size = "medium",
  disabled = false,
  active = false,
  loading = false,
  leftIcon = null,
  rightIcon = null,
  fullWidth = false,
  type = "button",
  onClick,
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const s = SIZES[size] || SIZES.medium;
  const tone = FILL[color] || FILL.primary;
  const base = {
    display: fullWidth ? "flex" : "inline-flex",
    width: fullWidth ? "100%" : undefined,
    alignItems: "center",
    justifyContent: "center",
    gap: "var(--space-8)",
    height: s.height,
    padding: s.padding,
    font: s.font,
    lineHeight: s.line,
    borderRadius: "var(--border-radius-small)",
    border: kind === "secondary" ? "1px solid" : "none",
    cursor: disabled ? "not-allowed" : "pointer",
    whiteSpace: "nowrap",
    userSelect: "none",
    transition: "var(--motion-productive-short) transform, var(--motion-productive-medium) background-color",
    transform: "scale(1) translate3d(0,0,0)"
  };
  let skin;
  if (kind === "primary") {
    skin = {
      background: hover && !disabled ? tone.hover : tone.bg,
      color: tone.fg
    };
    if (disabled) skin = {
      background: "var(--disabled-background-color)",
      color: "var(--disabled-text-color)"
    };
  } else if (kind === "secondary") {
    skin = {
      background: active ? "var(--primary-selected-color)" : hover && !disabled ? "var(--primary-background-hover-color)" : "transparent",
      borderColor: disabled ? "var(--disabled-text-color)" : active ? "var(--primary-color)" : color === "positive" ? "var(--positive-color)" : color === "negative" ? "var(--negative-color)" : "var(--ui-border-color)",
      color: disabled ? "var(--disabled-text-color)" : OUTLINE_FG[color]
    };
  } else {
    skin = {
      background: active ? "var(--primary-selected-color)" : hover && !disabled ? "var(--primary-background-hover-color)" : "transparent",
      color: disabled ? "var(--disabled-text-color)" : OUTLINE_FG[color]
    };
  }
  return /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    disabled: disabled,
    onClick: disabled ? undefined : onClick,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    onMouseDown: e => {
      if (!disabled) e.currentTarget.style.transform = "scale(0.95) translate3d(0,0,0)";
    },
    onMouseUp: e => {
      e.currentTarget.style.transform = "scale(1) translate3d(0,0,0)";
    },
    style: {
      ...base,
      ...skin,
      ...style
    }
  }, rest), loading ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-block",
      width: 16,
      height: 16,
      border: "2px solid currentColor",
      borderTopColor: "transparent",
      borderRadius: "50%",
      animation: "ozeeSpin 800ms linear infinite"
    }
  }) : leftIcon, !loading && children, !loading && rightIcon, /*#__PURE__*/React.createElement("style", null, "@keyframes ozeeSpin{to{transform:rotate(360deg)}}"));
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Icon.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const ICON_SIZES = {
  xs: 16,
  small: 20,
  medium: 24,
  large: 32
};

/** Renders a Vibe SVG glyph from assets/icons as a recolourable mask.
 *  Pass `name` (file stem, e.g. "Search") or an explicit `src`.
 *  Override the folder globally with window.OZEE_ICON_BASE. */
function Icon({
  name,
  src,
  size = 20,
  color = "currentColor",
  title,
  style,
  ...rest
}) {
  const base = typeof window !== "undefined" && window.OZEE_ICON_BASE || "assets/icons";
  const url = src || `${base}/${name}.svg`;
  const px = typeof size === "string" ? ICON_SIZES[size] || 20 : size;
  return /*#__PURE__*/React.createElement("span", _extends({
    role: title ? "img" : "presentation",
    "aria-label": title,
    style: {
      display: "inline-block",
      flex: "none",
      width: px,
      height: px,
      background: color,
      WebkitMask: `url("${url}") center / contain no-repeat`,
      mask: `url("${url}") center / contain no-repeat`,
      ...style
    }
  }, rest));
}
const IB_SIZES = {
  xs: 24,
  small: 32,
  medium: 40,
  large: 48
};
function IconButton({
  icon,
  name,
  size = "medium",
  kind = "tertiary",
  active = false,
  disabled = false,
  ariaLabel,
  onClick,
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const box = IB_SIZES[size] || 40;
  const glyph = {
    xs: 16,
    small: 16,
    medium: 20,
    large: 24
  }[size] || 20;
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    "aria-label": ariaLabel,
    disabled: disabled,
    onClick: disabled ? undefined : onClick,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      width: box,
      height: box,
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      border: kind === "secondary" ? "1px solid var(--ui-border-color)" : "none",
      borderRadius: "var(--border-radius-small)",
      background: active ? "var(--primary-selected-color)" : hover && !disabled ? "var(--primary-background-hover-color)" : "transparent",
      color: disabled ? "var(--disabled-text-color)" : active ? "var(--primary-color)" : "var(--icon-color)",
      cursor: disabled ? "not-allowed" : "pointer",
      transition: "background-color var(--motion-productive-medium) var(--motion-timing-transition)",
      ...style
    }
  }, rest), icon || /*#__PURE__*/React.createElement(Icon, {
    name: name,
    size: glyph
  }));
}
function ButtonGroup({
  options = [],
  value,
  onChange,
  size = "small",
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "inline-flex",
      border: "1px solid var(--ui-border-color)",
      borderRadius: "var(--border-radius-small)",
      overflow: "hidden",
      ...style
    }
  }, options.map((o, i) => {
    const selected = o.value === value;
    return /*#__PURE__*/React.createElement("button", {
      key: o.value,
      type: "button",
      onClick: () => onChange && onChange(o.value),
      style: {
        height: size === "small" ? 32 : 40,
        padding: "0 var(--space-12)",
        border: "none",
        borderInlineStart: i === 0 ? "none" : "1px solid var(--ui-border-color)",
        background: selected ? "var(--primary-selected-color)" : "transparent",
        color: selected ? "var(--primary-color)" : "var(--primary-text-color)",
        font: "var(--font-text2-normal)",
        cursor: "pointer",
        display: "inline-flex",
        alignItems: "center",
        gap: "var(--space-4)"
      }
    }, o.icon ? /*#__PURE__*/React.createElement(Icon, {
      name: o.icon,
      size: 16
    }) : null, o.text);
  }));
}
function SplitButton({
  children,
  onClick,
  onMenuClick,
  kind = "primary",
  size = "medium",
  style
}) {
  const [hover, setHover] = React.useState(null);
  const h = {
    small: 32,
    medium: 40,
    large: 48
  }[size] || 40;
  const bg = k => hover === k ? "var(--primary-hover-color)" : "var(--primary-color)";
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "inline-flex",
      ...style
    }
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClick,
    onMouseEnter: () => setHover("main"),
    onMouseLeave: () => setHover(null),
    style: {
      height: h,
      padding: "0 var(--space-16)",
      border: "none",
      background: bg("main"),
      color: "var(--text-color-on-primary)",
      font: "var(--font-text1-normal)",
      borderStartStartRadius: "var(--border-radius-small)",
      borderEndStartRadius: "var(--border-radius-small)",
      cursor: "pointer"
    }
  }, children), /*#__PURE__*/React.createElement("span", {
    style: {
      width: 1,
      background: "rgba(255,255,255,0.3)"
    }
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "More options",
    onClick: onMenuClick,
    onMouseEnter: () => setHover("menu"),
    onMouseLeave: () => setHover(null),
    style: {
      height: h,
      width: 32,
      border: "none",
      background: bg("menu"),
      color: "var(--text-color-on-primary)",
      borderStartEndRadius: "var(--border-radius-small)",
      borderEndEndRadius: "var(--border-radius-small)",
      cursor: "pointer",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "DropdownChevronDown",
    size: 16
  })));
}
Object.assign(__ds_scope, { Icon, IconButton, ButtonGroup, SplitButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Icon.jsx", error: String((e && e.message) || e) }); }

// components/core/Link.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Link({
  href = "#",
  children,
  text,
  inheritFontSize = false,
  external = false,
  disabled = false,
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("a", _extends({
    href: disabled ? undefined : href,
    target: external ? "_blank" : undefined,
    rel: external ? "noreferrer" : undefined,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: "var(--space-4)",
      color: disabled ? "var(--disabled-text-color)" : "var(--link-color)",
      font: inheritFontSize ? "inherit" : "var(--font-text2-normal)",
      textDecoration: hover && !disabled ? "underline" : "none",
      cursor: disabled ? "not-allowed" : "pointer",
      ...style
    }
  }, rest), text || children, external ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "Share",
    size: 14
  }) : null);
}
Object.assign(__ds_scope, { Link });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Link.jsx", error: String((e && e.message) || e) }); }

// components/data/Avatar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const SIZES = {
  xs: 16,
  small: 24,
  medium: 32,
  large: 50
};
const PALETTE = ["var(--color-bright-blue)", "var(--color-purple)", "var(--color-done-green)", "var(--color-working-orange)", "var(--color-lipstick)", "var(--color-aquamarine)", "var(--color-indigo)", "var(--color-dark-orange)"];
function initials(text = "") {
  return text.split(" ").filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join("");
}
function Avatar({
  src,
  text,
  ariaLabel,
  size = "medium",
  type = "img",
  square = false,
  backgroundColor,
  withoutBorder = false,
  bottomRightBadge,
  style,
  ...rest
}) {
  const px = SIZES[size] || 32;
  const seedIndex = text ? text.charCodeAt(0) % PALETTE.length : 0;
  const bg = backgroundColor || PALETTE[seedIndex];
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      position: "relative",
      display: "inline-block",
      width: px,
      height: px,
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("span", {
    "aria-label": ariaLabel || text,
    style: {
      width: "100%",
      height: "100%",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      borderRadius: square ? "var(--border-radius-small)" : "50%",
      overflow: "hidden",
      border: withoutBorder ? "none" : "1px solid",
      borderColor: src && type === "img" ? "var(--primary-background-color)" : "var(--layout-border-color)",
      background: src && type === "img" ? "transparent" : bg,
      color: "var(--text-color-on-primary)",
      font: px <= 24 ? "var(--font-text3-medium)" : px <= 32 ? "var(--font-text2-medium)" : "var(--font-text1-medium)"
    }
  }, src && type === "img" ? /*#__PURE__*/React.createElement("img", {
    src: src,
    alt: "",
    style: {
      width: "100%",
      height: "100%",
      objectFit: "cover"
    }
  }) : initials(text)), bottomRightBadge ? /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      bottom: -2,
      insetInlineEnd: -2,
      width: Math.max(8, px / 3),
      height: Math.max(8, px / 3),
      borderRadius: "50%",
      background: bottomRightBadge,
      border: "2px solid var(--primary-background-color)"
    }
  }) : null);
}
function AvatarGroup({
  people = [],
  size = "medium",
  max = 4,
  style
}) {
  const px = SIZES[size] || 32;
  const shown = people.slice(0, max);
  const extra = people.length - shown.length;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      ...style
    }
  }, shown.map((p, i) => /*#__PURE__*/React.createElement("span", {
    key: p.id || i,
    style: {
      marginInlineStart: i === 0 ? 0 : -px / 4
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    src: p.src,
    text: p.text || p.name,
    size: size
  }))), extra > 0 ? /*#__PURE__*/React.createElement("span", {
    style: {
      marginInlineStart: -px / 4,
      width: px,
      height: px,
      borderRadius: "50%",
      background: "var(--ui-background-color)",
      color: "var(--primary-text-color)",
      border: "1px solid var(--layout-border-color)",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      font: "var(--font-text3-medium)"
    }
  }, "+", extra) : null);
}
Object.assign(__ds_scope, { Avatar, AvatarGroup });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/data/Chips.jsx
try { (() => {
function Chips({
  label,
  color = "primary",
  leftIcon,
  leftAvatar,
  onDelete,
  readOnly = false,
  disabled = false,
  size = "medium",
  style
}) {
  const bg = {
    primary: "var(--primary-selected-color)",
    positive: "var(--positive-color-selected)",
    negative: "var(--negative-color-selected)",
    warning: "var(--warning-color-selected)",
    neutral: "var(--ui-background-color)"
  }[color] || color;
  const small = size === "small";
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: small ? "var(--space-2)" : "var(--space-4)",
      height: small ? 20 : 24,
      padding: small ? "0 4px" : "0 8px",
      borderRadius: 4,
      background: bg,
      color: disabled ? "var(--disabled-text-color)" : "var(--primary-text-color)",
      font: "var(--font-text2-normal)",
      userSelect: readOnly ? "text" : "none",
      flex: "none",
      maxWidth: 220,
      ...style
    }
  }, leftAvatar ? /*#__PURE__*/React.createElement("img", {
    src: leftAvatar,
    alt: "",
    style: {
      width: 18,
      height: 18,
      borderRadius: "50%",
      objectFit: "cover"
    }
  }) : null, leftIcon ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: leftIcon,
    size: 16
  }) : null, /*#__PURE__*/React.createElement("span", {
    style: {
      overflow: "hidden",
      textOverflow: "ellipsis",
      whiteSpace: "nowrap"
    }
  }, label), onDelete && !readOnly ? /*#__PURE__*/React.createElement("span", {
    onClick: onDelete,
    style: {
      display: "inline-flex",
      cursor: "pointer"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "CloseSmall",
    size: 14
  })) : null);
}
function Label({
  text,
  kind = "fill",
  color = "primary",
  size = "medium",
  style
}) {
  const fill = {
    primary: "var(--primary-color)",
    dark: "var(--inverted-color-background)",
    positive: "var(--positive-color)",
    negative: "var(--negative-color)"
  }[color] || color;
  const small = size === "small";
  const base = {
    display: "inline-flex",
    alignItems: "center",
    justifyContent: "center",
    borderRadius: small ? 2 : "var(--border-radius-small)",
    padding: small ? "0 var(--space-4)" : "2px var(--space-8)",
    font: small ? "var(--font-text3-medium)" : "var(--font-text2-normal)",
    ...style
  };
  if (kind === "line") {
    return /*#__PURE__*/React.createElement("span", {
      style: {
        ...base,
        border: "1px solid currentColor",
        padding: small ? "0 var(--space-4)" : "1px var(--space-8)",
        color: fill
      }
    }, text);
  }
  return /*#__PURE__*/React.createElement("span", {
    style: {
      ...base,
      background: fill,
      color: "var(--text-color-on-primary)"
    }
  }, text);
}
function Counter({
  count = 0,
  kind = "fill",
  color = "primary",
  size = "small",
  maxDigits = 3,
  style
}) {
  const shown = String(count).length > maxDigits ? `${"9".repeat(maxDigits)}+` : count;
  const fills = {
    primary: ["var(--primary-color)", "var(--fixed-light-color)"],
    dark: ["var(--inverted-color-background)", "var(--text-color-on-inverted)"],
    negative: ["var(--negative-color)", "var(--fixed-light-color)"],
    light: ["var(--ui-background-color)", "var(--primary-text-color)"]
  };
  const [bg, fg] = fills[color] || fills.primary;
  const large = size === "large";
  const base = {
    display: "inline-flex",
    justifyContent: "center",
    alignItems: "center",
    borderRadius: 30,
    minWidth: large ? 24 : 18,
    lineHeight: large ? "20px" : "18px",
    padding: large ? "2px var(--space-8)" : "0 var(--space-8)",
    font: size === "xs" ? "var(--font-text3-normal)" : large ? "var(--font-text2-normal)" : "var(--font-text3-medium)",
    ...style
  };
  if (kind === "line") return /*#__PURE__*/React.createElement("span", {
    style: {
      ...base,
      color: bg,
      boxShadow: "0 0 0 1px currentColor inset"
    }
  }, shown);
  return /*#__PURE__*/React.createElement("span", {
    style: {
      ...base,
      background: bg,
      color: fg
    }
  }, shown);
}
function Badge({
  children,
  count,
  dot = false,
  color = "negative",
  anchor = "topEnd",
  style
}) {
  const bg = {
    negative: "var(--negative-color)",
    positive: "var(--positive-color)",
    primary: "var(--primary-color)"
  }[color] || color;
  const pos = anchor === "topStart" ? {
    top: 0,
    insetInlineStart: 0,
    translate: "-50% -50%"
  } : {
    top: 0,
    insetInlineEnd: 0,
    translate: "50% -50%"
  };
  return /*#__PURE__*/React.createElement("span", {
    style: {
      position: "relative",
      display: "inline-flex",
      ...style
    }
  }, children, /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      zIndex: 1,
      borderRadius: 16,
      border: "2px solid var(--primary-background-color)",
      background: bg,
      ...pos
    }
  }, dot ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      width: 8,
      height: 8,
      borderRadius: "50%"
    }
  }) : /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      minWidth: 16,
      height: 16,
      padding: "0 4px",
      font: "var(--font-text3-medium)",
      color: "var(--fixed-light-color)"
    }
  }, count)));
}
Object.assign(__ds_scope, { Chips, Label, Counter, Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Chips.jsx", error: String((e && e.message) || e) }); }

// components/data/Table.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Table({
  columns = [],
  rows = [],
  border = true,
  dense = false,
  emptyState,
  onRowClick,
  style
}) {
  const rowH = dense ? 36 : 40;
  return /*#__PURE__*/React.createElement("div", {
    role: "table",
    style: {
      background: "var(--primary-background-color)",
      width: "100%",
      overflow: "auto",
      border: border ? "1px solid var(--layout-border-color)" : "none",
      borderRadius: border ? "var(--border-radius-small)" : 0,
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    role: "rowgroup"
  }, /*#__PURE__*/React.createElement("div", {
    role: "row",
    style: {
      display: "grid",
      gridTemplateColumns: columns.map(c => c.width || "1fr").join(" "),
      height: rowH,
      alignItems: "center",
      borderBottom: "1px solid var(--layout-border-color)",
      background: "var(--primary-background-color)"
    }
  }, columns.map(c => /*#__PURE__*/React.createElement("div", {
    key: c.id,
    role: "columnheader",
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-4)",
      padding: "0 var(--space-12)",
      font: "var(--font-text2-normal)",
      color: "var(--secondary-text-color)",
      cursor: c.sortable ? "pointer" : "default",
      justifyContent: c.align === "end" ? "flex-end" : "flex-start"
    },
    onClick: c.sortable && c.onSort ? c.onSort : undefined
  }, c.title, c.sortable ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: c.sortDirection === "desc" ? "DropdownChevronDown" : "DropdownChevronDown",
    size: 12,
    color: "var(--icon-color)",
    style: c.sortDirection === "asc" ? {
      transform: "rotate(180deg)"
    } : undefined
  }) : null, c.infoContent ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "Info",
    size: 14,
    color: "var(--icon-color)"
  }) : null)))), /*#__PURE__*/React.createElement("div", {
    role: "rowgroup"
  }, rows.length === 0 && emptyState ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-32)",
      textAlign: "center"
    }
  }, emptyState) : rows.map((r, ri) => /*#__PURE__*/React.createElement("div", {
    key: r.id || ri,
    role: "row",
    onClick: onRowClick ? () => onRowClick(r) : undefined,
    onMouseEnter: e => e.currentTarget.style.background = "var(--primary-background-hover-color)",
    onMouseLeave: e => e.currentTarget.style.background = "transparent",
    style: {
      display: "grid",
      gridTemplateColumns: columns.map(c => c.width || "1fr").join(" "),
      minHeight: rowH,
      alignItems: "center",
      cursor: onRowClick ? "pointer" : "default",
      transition: "background-color var(--motion-productive-short)"
    }
  }, columns.map(c => /*#__PURE__*/React.createElement("div", {
    key: c.id,
    role: "cell",
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      padding: "var(--space-4) var(--space-12)",
      minHeight: rowH,
      boxSizing: "border-box",
      borderBottom: ri === rows.length - 1 ? "none" : "1px solid var(--layout-border-color)",
      font: "var(--font-text2-normal)",
      color: "var(--primary-text-color)",
      justifyContent: c.align === "end" ? "flex-end" : "flex-start",
      overflow: "hidden"
    }
  }, c.render ? c.render(r) : r[c.id]))))));
}
function List({
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "list",
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 2,
      ...style
    }
  }, rest), children);
}
function ListTitle({
  children,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-8) var(--space-8) var(--space-4)",
      font: "var(--font-text3-medium)",
      color: "var(--secondary-text-color)",
      textTransform: "none",
      ...style
    }
  }, children);
}
function ListItem({
  children,
  iconName,
  avatarSrc,
  selected = false,
  disabled = false,
  onClick,
  endAdornment,
  style
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    role: "listitem",
    onClick: disabled ? undefined : onClick,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      minHeight: 32,
      padding: "0 var(--space-8)",
      borderRadius: "var(--border-radius-small)",
      font: "var(--font-text2-normal)",
      color: disabled ? "var(--disabled-text-color)" : "var(--primary-text-color)",
      background: selected ? "var(--primary-selected-color)" : hover && !disabled ? "var(--primary-background-hover-color)" : "transparent",
      cursor: disabled ? "not-allowed" : "pointer",
      ...style
    }
  }, iconName ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: iconName,
    size: 16,
    color: "var(--icon-color)"
  }) : null, avatarSrc ? /*#__PURE__*/React.createElement("img", {
    src: avatarSrc,
    alt: "",
    style: {
      width: 20,
      height: 20,
      borderRadius: "50%",
      objectFit: "cover"
    }
  }) : null, /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1,
      overflow: "hidden",
      textOverflow: "ellipsis",
      whiteSpace: "nowrap"
    }
  }, children), endAdornment);
}
Object.assign(__ds_scope, { Table, List, ListTitle, ListItem });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Table.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Toast.jsx
try { (() => {
const TONES = {
  primary: {
    bg: "var(--primary-color)",
    fg: "var(--fixed-light-color)",
    icon: "Info"
  },
  positive: {
    bg: "var(--positive-color)",
    fg: "var(--fixed-light-color)",
    icon: "Check"
  },
  negative: {
    bg: "var(--negative-color)",
    fg: "var(--fixed-light-color)",
    icon: "Alert"
  },
  warning: {
    bg: "var(--warning-color)",
    fg: "var(--fixed-dark-color)",
    icon: "Warning"
  },
  dark: {
    bg: "var(--inverted-color-background)",
    fg: "var(--text-color-on-inverted)",
    icon: "Info"
  }
};
function Toast({
  children,
  type = "normal",
  open = true,
  onClose,
  action,
  withIcon = true,
  style
}) {
  const tone = TONES[type === "normal" ? "primary" : type] || TONES.primary;
  if (!open) return null;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      minWidth: 200,
      width: "max-content",
      maxWidth: "min(90vw, 520px)",
      padding: "var(--space-8)",
      margin: "var(--space-16)",
      borderRadius: "var(--border-radius-small)",
      background: tone.bg,
      color: tone.fg,
      boxShadow: "var(--box-shadow-medium)",
      font: "var(--font-text2-normal)",
      ...style
    }
  }, withIcon ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: tone.icon,
    size: 20,
    style: {
      marginInlineStart: "var(--space-8)"
    }
  }) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      margin: "0 var(--space-8)",
      flex: 1
    }
  }, children), action ? /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: action.onClick,
    style: {
      background: "transparent",
      border: "1px solid currentColor",
      color: "inherit",
      height: 24,
      padding: "0 var(--space-8)",
      borderRadius: "var(--border-radius-small)",
      font: "var(--font-text2-normal)",
      cursor: "pointer"
    }
  }, action.text) : null, onClose ? /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Close",
    onClick: onClose,
    style: {
      marginInlineStart: "var(--space-8)",
      background: "transparent",
      border: "none",
      color: "inherit",
      cursor: "pointer",
      display: "inline-flex"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "Close",
    size: 16
  })) : null);
}
function AlertBanner({
  children,
  type = "primary",
  onClose,
  action,
  style
}) {
  const tone = TONES[type] || TONES.primary;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      minHeight: 40,
      padding: "var(--space-4) var(--space-16)",
      background: tone.bg,
      color: tone.fg,
      font: "var(--font-text2-normal)",
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      gap: "var(--space-8)"
    }
  }, children, action ? /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: action.onClick,
    style: {
      background: "transparent",
      border: "none",
      color: "inherit",
      textDecoration: "underline",
      cursor: "pointer",
      font: "var(--font-text2-medium)"
    }
  }, action.text) : null), onClose ? /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Close",
    onClick: onClose,
    style: {
      background: "transparent",
      border: "none",
      color: "inherit",
      cursor: "pointer",
      display: "inline-flex"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "Close",
    size: 16
  })) : null);
}
const BOX_TONES = {
  primary: {
    bg: "var(--primary-highlighted-color)",
    border: "var(--primary-color)",
    icon: "Info",
    iconColor: "var(--primary-color)"
  },
  success: {
    bg: "#eaf5ef",
    border: "var(--positive-color)",
    icon: "Check",
    iconColor: "var(--positive-color)"
  },
  danger: {
    bg: "#fdeff1",
    border: "var(--negative-color)",
    icon: "Alert",
    iconColor: "var(--negative-color)"
  },
  warning: {
    bg: "#fff8e0",
    border: "var(--warning-color-hover)",
    icon: "Warning",
    iconColor: "var(--warning-color-hover)"
  },
  dark: {
    bg: "var(--allgrey-background-color)",
    border: "var(--layout-border-color)",
    icon: "Info",
    iconColor: "var(--secondary-text-color)"
  }
};
function AttentionBox({
  title,
  children,
  type = "primary",
  withIcon = true,
  onClose,
  style
}) {
  const tone = BOX_TONES[type] || BOX_TONES.primary;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: "var(--space-8)",
      padding: "var(--space-12) var(--space-16)",
      background: tone.bg,
      border: `1px solid ${tone.border}`,
      borderRadius: "var(--border-radius-small)",
      ...style
    }
  }, withIcon ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: tone.icon,
    size: 18,
    color: tone.iconColor,
    style: {
      marginTop: 2
    }
  }) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, title ? /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-text2-bold)",
      color: "var(--primary-text-color)",
      marginBottom: 2
    }
  }, title) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-text2-normal)",
      color: "var(--primary-text-color)"
    }
  }, children)), onClose ? /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Close",
    onClick: onClose,
    style: {
      background: "transparent",
      border: "none",
      cursor: "pointer",
      color: "var(--icon-color)",
      display: "inline-flex",
      height: 20
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "CloseSmall",
    size: 16
  })) : null);
}
function Tipseen({
  title,
  children,
  onClose,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 320,
      padding: "var(--space-16)",
      borderRadius: "var(--border-radius-medium)",
      background: "var(--primary-color)",
      color: "var(--text-color-on-primary)",
      boxShadow: "var(--box-shadow-medium)",
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "flex-start",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, title ? /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-text1-bold)",
      marginBottom: "var(--space-4)"
    }
  }, title) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-text2-normal)"
    }
  }, children)), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Dismiss",
    onClick: onClose,
    style: {
      background: "transparent",
      border: "none",
      color: "inherit",
      cursor: "pointer",
      display: "inline-flex"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "Close",
    size: 16
  }))));
}
Object.assign(__ds_scope, { Toast, AlertBanner, AttentionBox, Tipseen });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Toast.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Tooltip.jsx
try { (() => {
function Tooltip({
  content,
  children,
  position = "top",
  theme = "dark",
  style
}) {
  const [show, setShow] = React.useState(false);
  const offsets = {
    top: {
      bottom: "calc(100% + 6px)",
      left: "50%",
      transform: "translateX(-50%)"
    },
    bottom: {
      top: "calc(100% + 6px)",
      left: "50%",
      transform: "translateX(-50%)"
    },
    left: {
      right: "calc(100% + 6px)",
      top: "50%",
      transform: "translateY(-50%)"
    },
    right: {
      left: "calc(100% + 6px)",
      top: "50%",
      transform: "translateY(-50%)"
    }
  };
  return /*#__PURE__*/React.createElement("span", {
    style: {
      position: "relative",
      display: "inline-flex",
      ...style
    },
    onMouseEnter: () => setShow(true),
    onMouseLeave: () => setShow(false)
  }, children, show && content ? /*#__PURE__*/React.createElement("span", {
    role: "tooltip",
    style: {
      position: "absolute",
      zIndex: 50,
      maxWidth: 240,
      padding: "var(--space-8) var(--space-16)",
      borderRadius: "var(--border-radius-small)",
      background: theme === "primary" ? "var(--primary-color)" : "var(--inverted-color-background)",
      color: theme === "primary" ? "var(--text-color-on-primary)" : "var(--text-color-on-inverted)",
      font: "var(--font-text2-normal)",
      boxShadow: "var(--box-shadow-medium)",
      whiteSpace: "pre-wrap",
      width: "max-content",
      ...offsets[position]
    }
  }, content) : null);
}
function Info({
  content,
  style
}) {
  return /*#__PURE__*/React.createElement(Tooltip, {
    content: content,
    style: style
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "Info",
    size: 16,
    color: "var(--icon-color)"
  }));
}
function Loader({
  size = 32,
  color = "var(--primary-color)",
  style
}) {
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-block",
      width: size,
      height: size,
      border: `${Math.max(2, size / 12)}px solid var(--ui-background-color)`,
      borderTopColor: color,
      borderRadius: "50%",
      animation: "ozeeLoaderSpin 800ms linear infinite",
      ...style
    }
  }, /*#__PURE__*/React.createElement("style", null, "@keyframes ozeeLoaderSpin{to{transform:rotate(360deg)}}"));
}
function Skeleton({
  type = "rectangle",
  width,
  height,
  fullWidth = false,
  style
}) {
  const dims = type === "circle" ? {
    width: width || 40,
    height: height || 40,
    borderRadius: "50%"
  } : type === "text" ? {
    width: fullWidth ? "100%" : width || 162,
    height: height || 16,
    borderRadius: "var(--border-radius-small)"
  } : {
    width: fullWidth ? "100%" : width || 40,
    height: height || 40,
    borderRadius: "var(--border-radius-small)"
  };
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      background: "var(--ui-background-color)",
      animation: "ozeeShine 0.8s steps(10,end) infinite alternate",
      ...dims,
      ...style
    }
  }, /*#__PURE__*/React.createElement("style", null, "@keyframes ozeeShine{0%{opacity:.4}100%{opacity:1}}"));
}
function EmptyState({
  title,
  description,
  action,
  illustrationSrc,
  iconName = "Board",
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      alignItems: "center",
      textAlign: "center",
      gap: "var(--space-8)",
      padding: "var(--space-48) var(--space-24)",
      ...style
    }
  }, illustrationSrc ? /*#__PURE__*/React.createElement("img", {
    src: illustrationSrc,
    alt: "",
    style: {
      width: 120,
      height: "auto",
      opacity: 0.9,
      marginBottom: "var(--space-8)"
    }
  }) : /*#__PURE__*/React.createElement("span", {
    style: {
      width: 56,
      height: 56,
      borderRadius: "50%",
      background: "var(--allgrey-background-color)",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      marginBottom: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: iconName,
    size: 26,
    color: "var(--secondary-text-color)"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-h3-medium)",
      color: "var(--primary-text-color)"
    }
  }, title), description ? /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-text2-normal)",
      color: "var(--secondary-text-color)",
      maxWidth: 380
    }
  }, description) : null, action ? /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: "var(--space-8)"
    }
  }, action) : null);
}
Object.assign(__ds_scope, { Tooltip, Info, Loader, Skeleton, EmptyState });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Tooltip.jsx", error: String((e && e.message) || e) }); }

// components/forms/Checkbox.jsx
try { (() => {
function Checkbox({
  label,
  checked,
  indeterminate = false,
  disabled = false,
  onChange,
  style
}) {
  const on = checked || indeterminate;
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("label", {
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      position: "relative",
      display: "inline-flex",
      alignItems: "center",
      width: "fit-content",
      cursor: disabled ? "not-allowed" : "pointer",
      ...style
    }
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: !!checked,
    disabled: disabled,
    onChange: onChange,
    style: {
      position: "absolute",
      opacity: 0,
      width: 0,
      height: 0
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      width: 16,
      height: 16,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      border: "1px solid",
      borderColor: disabled ? "var(--ui-border-color)" : on ? "transparent" : hover ? "var(--secondary-text-color)" : "var(--ui-border-color)",
      borderRadius: 2,
      background: disabled ? "var(--disabled-background-color)" : on ? hover ? "var(--primary-hover-color)" : "var(--primary-color)" : "var(--secondary-background-color)",
      transition: "transform var(--motion-productive-short) var(--motion-timing-enter)",
      flex: "none"
    }
  }, indeterminate ? /*#__PURE__*/React.createElement("span", {
    style: {
      width: 8,
      height: 2,
      background: disabled ? "var(--disabled-text-color)" : "var(--text-color-on-primary)"
    }
  }) : checked ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "Check",
    size: 14,
    color: disabled ? "var(--disabled-text-color)" : "var(--text-color-on-primary)"
  }) : null), label ? /*#__PURE__*/React.createElement("span", {
    style: {
      marginInlineStart: "var(--space-8)",
      font: "var(--font-text2-normal)",
      color: disabled ? "var(--disabled-text-color)" : "var(--primary-text-color)",
      userSelect: "none"
    }
  }, label) : null);
}
function RadioButton({
  label,
  name,
  value,
  checked,
  disabled = false,
  onChange,
  style
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("label", {
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      display: "grid",
      gridTemplateColumns: "1.5em auto",
      gridGap: "0.5em",
      alignItems: "center",
      cursor: disabled ? "not-allowed" : "pointer",
      font: "var(--font-text2-normal)",
      color: disabled ? "var(--disabled-text-color)" : "var(--primary-text-color)",
      ...style
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "center"
    }
  }, /*#__PURE__*/React.createElement("input", {
    type: "radio",
    name: name,
    value: value,
    checked: !!checked,
    disabled: disabled,
    onChange: onChange,
    style: {
      opacity: 0,
      width: 0,
      height: 0,
      margin: 0
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      width: "1em",
      height: "1em",
      boxSizing: "border-box",
      borderRadius: "50%",
      border: checked ? "0.3em solid" : "0.1em solid",
      borderColor: disabled ? "var(--disabled-background-color)" : checked ? hover ? "var(--primary-hover-color)" : "var(--primary-color)" : hover ? "var(--primary-text-color)" : "var(--ui-border-color)",
      background: "var(--secondary-background-color)",
      transition: "border-width var(--motion-productive-medium) var(--motion-timing-enter)"
    }
  })), /*#__PURE__*/React.createElement("span", null, label));
}
function Toggle({
  checked,
  onChange,
  disabled = false,
  size = "medium",
  areLabelsHidden = true,
  onLabel = "On",
  offLabel = "Off",
  style
}) {
  const dims = size === "small" ? {
    w: 28,
    h: 16,
    c: 12,
    on: 14,
    off: 2
  } : {
    w: 41,
    h: 24,
    c: 18,
    on: 20,
    off: 3
  };
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: "var(--space-8)",
      cursor: disabled ? "not-allowed" : "pointer",
      opacity: disabled ? 0.4 : 1,
      ...style
    }
  }, !areLabelsHidden ? /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-text2-normal)",
      color: "var(--secondary-text-color)"
    }
  }, checked ? onLabel : offLabel) : null, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: !!checked,
    disabled: disabled,
    onChange: onChange,
    style: {
      position: "absolute",
      opacity: 0,
      width: 0,
      height: 0
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      position: "relative",
      width: dims.w,
      height: dims.h,
      borderRadius: 100,
      background: checked ? "var(--primary-color)" : "var(--ui-border-color)",
      transition: "background-color var(--motion-productive-medium) var(--motion-timing-transition)",
      flex: "none"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      width: dims.c,
      height: dims.c,
      borderRadius: "50%",
      background: "var(--primary-background-color)",
      top: `calc(50% - ${dims.c / 2}px)`,
      insetInlineStart: checked ? dims.on : dims.off,
      transition: "inset-inline-start var(--motion-productive-medium) var(--motion-timing-transition)"
    }
  })));
}
Object.assign(__ds_scope, { Checkbox, RadioButton, Toggle });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Checkbox.jsx", error: String((e && e.message) || e) }); }

// components/forms/Dropdown.jsx
try { (() => {
function DialogContentContainer({
  children,
  size = "medium",
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--dialog-background-color)",
      borderRadius: size === "large" ? "var(--border-radius-medium)" : "var(--border-radius-small)",
      boxShadow: "var(--box-shadow-medium)",
      padding: "var(--space-8)",
      ...style
    }
  }, children);
}
function Dropdown({
  options = [],
  value,
  onChange,
  placeholder = "Select",
  label,
  size = "medium",
  disabled = false,
  clearable = false,
  multi = false,
  searchable = false,
  style
}) {
  const [open, setOpen] = React.useState(false);
  const [hover, setHover] = React.useState(false);
  const [query, setQuery] = React.useState("");
  const ref = React.useRef(null);
  React.useEffect(() => {
    const close = e => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, []);
  const selected = multi ? options.filter(o => (value || []).includes(o.value)) : options.find(o => o.value === value);
  const shown = searchable && query ? options.filter(o => o.label.toLowerCase().includes(query.toLowerCase())) : options;
  const h = {
    small: 32,
    medium: 40,
    large: 48
  }[size] || 40;
  const pick = o => {
    if (multi) {
      const set = new Set(value || []);
      set.has(o.value) ? set.delete(o.value) : set.add(o.value);
      onChange && onChange([...set]);
    } else {
      onChange && onChange(o.value);
      setOpen(false);
    }
  };
  return /*#__PURE__*/React.createElement("div", {
    ref: ref,
    style: {
      position: "relative",
      width: "100%",
      ...style
    }
  }, label ? /*#__PURE__*/React.createElement("label", {
    style: {
      display: "block",
      font: "var(--font-text2-normal)",
      paddingBlock: "var(--space-4)"
    }
  }, label) : null, /*#__PURE__*/React.createElement("div", {
    role: "button",
    tabIndex: 0,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    onClick: () => !disabled && setOpen(o => !o),
    style: {
      minHeight: h,
      display: "flex",
      alignItems: "center",
      gap: "var(--space-4)",
      padding: "0 var(--space-8) 0 var(--space-12)",
      border: "1px solid",
      borderColor: disabled ? "transparent" : open ? "var(--primary-color)" : hover ? "var(--primary-text-color)" : "var(--ui-border-color)",
      borderRadius: "var(--border-radius-small)",
      background: disabled ? "var(--disabled-background-color)" : "var(--secondary-background-color)",
      cursor: disabled ? "not-allowed" : "pointer",
      font: size === "small" ? "var(--font-text2-normal)" : "var(--font-text1-normal)",
      color: "var(--primary-text-color)",
      transition: "border-color var(--motion-productive-medium) ease-in"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1,
      display: "flex",
      gap: "var(--space-4)",
      flexWrap: "wrap",
      alignItems: "center",
      overflow: "hidden",
      whiteSpace: "nowrap",
      color: (multi ? selected.length : selected) ? "var(--primary-text-color)" : "var(--placeholder-color)"
    }
  }, multi ? selected.length ? selected.map(s => /*#__PURE__*/React.createElement("span", {
    key: s.value,
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 4,
      height: 24,
      padding: "0 var(--space-8)",
      borderRadius: 4,
      background: "var(--primary-selected-color)",
      font: "var(--font-text2-normal)"
    }
  }, s.label)) : placeholder : selected ? selected.label : placeholder), clearable && (multi ? selected.length : selected) ? /*#__PURE__*/React.createElement("span", {
    onClick: e => {
      e.stopPropagation();
      onChange && onChange(multi ? [] : null);
    },
    style: {
      display: "inline-flex"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "CloseSmall",
    size: 16,
    color: "var(--icon-color)"
  })) : null, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "DropdownChevronDown",
    size: 16,
    color: "var(--icon-color)",
    style: {
      transform: open ? "rotate(180deg)" : "none",
      transition: "transform var(--motion-productive-medium)"
    }
  })), open ? /*#__PURE__*/React.createElement(DialogContentContainer, {
    style: {
      position: "absolute",
      zIndex: 40,
      insetInlineStart: 0,
      insetInlineEnd: 0,
      marginTop: 4,
      maxHeight: 260,
      overflow: "auto"
    }
  }, searchable ? /*#__PURE__*/React.createElement("input", {
    autoFocus: true,
    value: query,
    onChange: e => setQuery(e.target.value),
    placeholder: "Search",
    style: {
      width: "100%",
      height: 32,
      marginBottom: 4,
      border: "1px solid var(--ui-border-color)",
      borderRadius: "var(--border-radius-small)",
      padding: "0 var(--space-8)",
      font: "var(--font-text2-normal)",
      outline: "none"
    }
  }) : null, shown.map(o => {
    const isSel = multi ? (value || []).includes(o.value) : o.value === value;
    return /*#__PURE__*/React.createElement("div", {
      key: o.value,
      onClick: () => pick(o),
      style: {
        display: "flex",
        alignItems: "center",
        gap: "var(--space-8)",
        height: 32,
        padding: "0 var(--space-8)",
        borderRadius: "var(--border-radius-small)",
        font: "var(--font-text2-normal)",
        color: "var(--primary-text-color)",
        background: isSel ? "var(--primary-selected-color)" : "transparent",
        cursor: "pointer"
      },
      onMouseEnter: e => {
        if (!isSel) e.currentTarget.style.background = "var(--primary-background-hover-color)";
      },
      onMouseLeave: e => {
        if (!isSel) e.currentTarget.style.background = "transparent";
      }
    }, o.icon ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
      name: o.icon,
      size: 16,
      color: "var(--icon-color)"
    }) : null, o.color ? /*#__PURE__*/React.createElement("span", {
      style: {
        width: 10,
        height: 10,
        borderRadius: "50%",
        background: o.color
      }
    }) : null, /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 1
      }
    }, o.label), isSel ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
      name: "Check",
      size: 14,
      color: "var(--primary-color)"
    }) : null);
  }), shown.length === 0 ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-8)",
      font: "var(--font-text2-normal)",
      color: "var(--secondary-text-color)"
    }
  }, "No results") : null) : null);
}
function Combobox({
  options = [],
  value,
  onChange,
  placeholder = "Search…",
  label,
  style
}) {
  return /*#__PURE__*/React.createElement(Dropdown, {
    options: options,
    value: value,
    onChange: onChange,
    placeholder: placeholder,
    label: label,
    searchable: true,
    style: style
  });
}
Object.assign(__ds_scope, { DialogContentContainer, Dropdown, Combobox });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Dropdown.jsx", error: String((e && e.message) || e) }); }

// components/forms/DatePicker.jsx
try { (() => {
const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const DAYS = ["S", "M", "T", "W", "T", "F", "S"];
function startOfMonth(d) {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}
function sameDay(a, b) {
  return a && b && a.toDateString() === b.toDateString();
}
function DatePicker({
  value,
  onChange,
  style
}) {
  const [cursor, setCursor] = React.useState(startOfMonth(value || new Date()));
  const first = startOfMonth(cursor);
  const offset = first.getDay();
  const daysInMonth = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
  const cells = [];
  for (let i = 0; i < offset; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), d));
  const today = new Date();
  return /*#__PURE__*/React.createElement(__ds_scope.DialogContentContainer, {
    style: {
      width: 280,
      padding: "var(--space-16)",
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      marginBottom: "var(--space-12)"
    }
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Previous month",
    onClick: () => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1)),
    style: {
      border: "none",
      background: "transparent",
      cursor: "pointer",
      color: "var(--icon-color)",
      display: "inline-flex"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "NavigationChevronLeft",
    size: 16
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-text2-medium)",
      color: "var(--primary-text-color)"
    }
  }, MONTHS[cursor.getMonth()], " ", cursor.getFullYear()), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Next month",
    onClick: () => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1)),
    style: {
      border: "none",
      background: "transparent",
      cursor: "pointer",
      color: "var(--icon-color)",
      display: "inline-flex"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "NavigationChevronRight",
    size: 16
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(7, 1fr)",
      gap: 2
    }
  }, DAYS.map((d, i) => /*#__PURE__*/React.createElement("span", {
    key: i,
    style: {
      font: "var(--font-text3-normal)",
      color: "var(--secondary-text-color)",
      textAlign: "center",
      paddingBottom: 4
    }
  }, d)), cells.map((d, i) => d ? /*#__PURE__*/React.createElement("button", {
    key: i,
    type: "button",
    onClick: () => onChange && onChange(d),
    style: {
      height: 32,
      border: sameDay(d, today) && !sameDay(d, value) ? "1px solid var(--primary-color)" : "none",
      borderRadius: "50%",
      background: sameDay(d, value) ? "var(--primary-color)" : "transparent",
      color: sameDay(d, value) ? "var(--text-color-on-primary)" : "var(--primary-text-color)",
      font: "var(--font-text2-normal)",
      cursor: "pointer"
    },
    onMouseEnter: e => {
      if (!sameDay(d, value)) e.currentTarget.style.background = "var(--primary-background-hover-color)";
    },
    onMouseLeave: e => {
      if (!sameDay(d, value)) e.currentTarget.style.background = "transparent";
    }
  }, d.getDate()) : /*#__PURE__*/React.createElement("span", {
    key: i
  }))));
}
Object.assign(__ds_scope, { DatePicker });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/DatePicker.jsx", error: String((e && e.message) || e) }); }

// components/forms/Slider.jsx
try { (() => {
function Slider({
  value = 0,
  onChange,
  min = 0,
  max = 100,
  step = 1,
  showValue = false,
  disabled = false,
  color = "primary",
  style
}) {
  const pct = (value - min) / (max - min) * 100;
  const track = color === "positive" ? "var(--positive-color)" : color === "negative" ? "var(--negative-color)" : "var(--primary-color)";
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-12)",
      width: "100%",
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      flex: 1,
      height: 20,
      display: "flex",
      alignItems: "center"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      inset: "auto 0",
      height: 4,
      borderRadius: 2,
      background: "var(--ui-background-color)"
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      insetInlineStart: 0,
      width: `${pct}%`,
      height: 4,
      borderRadius: 2,
      background: disabled ? "var(--disabled-background-color)" : track
    }
  }), /*#__PURE__*/React.createElement("input", {
    type: "range",
    min: min,
    max: max,
    step: step,
    value: value,
    disabled: disabled,
    onChange: e => onChange && onChange(Number(e.target.value)),
    style: {
      position: "relative",
      width: "100%",
      margin: 0,
      appearance: "none",
      background: "transparent",
      height: 20,
      cursor: disabled ? "not-allowed" : "pointer"
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      insetInlineStart: `calc(${pct}% - 8px)`,
      width: 16,
      height: 16,
      borderRadius: "50%",
      background: "var(--primary-background-color)",
      border: `2px solid ${disabled ? "var(--disabled-background-color)" : track}`,
      boxShadow: "var(--box-shadow-xs)",
      pointerEvents: "none"
    }
  })), showValue ? /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-text2-normal)",
      color: "var(--secondary-text-color)",
      minWidth: 32,
      textAlign: "end"
    }
  }, value) : null);
}
function ProgressBar({
  value = 0,
  max = 100,
  color = "primary",
  size = "medium",
  showLabel = false,
  style
}) {
  const pct = Math.max(0, Math.min(100, value / max * 100));
  const fill = {
    primary: "var(--primary-color)",
    positive: "var(--positive-color)",
    negative: "var(--negative-color)",
    warning: "var(--warning-color)"
  }[color] || "var(--primary-color)";
  const h = size === "small" ? 4 : size === "large" ? 12 : 8;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      width: "100%",
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      height: h,
      borderRadius: h / 2,
      background: "var(--ui-background-color)",
      overflow: "hidden"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: `${pct}%`,
      height: "100%",
      background: fill,
      borderRadius: h / 2,
      transition: "width var(--motion-expressive-short) var(--motion-timing-transition)"
    }
  })), showLabel ? /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-text3-medium)",
      color: "var(--secondary-text-color)",
      minWidth: 32,
      textAlign: "end"
    }
  }, Math.round(pct), "%") : null);
}
const SWATCHES = ["var(--color-done-green)", "var(--color-grass-green)", "var(--color-bright-green)", "var(--color-saladish)", "var(--color-egg-yolk)", "var(--color-working-orange)", "var(--color-dark-orange)", "var(--color-sunset)", "var(--color-stuck-red)", "var(--color-dark-red)", "var(--color-sofia-pink)", "var(--color-lipstick)", "var(--color-bubble)", "var(--color-purple)", "var(--color-dark-purple)", "var(--color-berry)", "var(--color-dark-indigo)", "var(--color-indigo)", "var(--color-navy)", "var(--color-bright-blue)", "var(--color-dark-blue)", "var(--color-aquamarine)", "var(--color-chili-blue)", "var(--color-river)", "var(--color-winter)", "var(--color-explosive)", "var(--color-american-gray)", "var(--color-blackish)", "var(--color-brown)", "var(--color-tan)", "var(--color-sky)", "var(--color-lavender)"];
function ColorPicker({
  value,
  onChange,
  colors = SWATCHES,
  columns = 8,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: `repeat(${columns}, 1fr)`,
      gap: "var(--space-4)",
      ...style
    }
  }, colors.map(c => /*#__PURE__*/React.createElement("button", {
    key: c,
    type: "button",
    "aria-label": c,
    onClick: () => onChange && onChange(c),
    style: {
      width: 28,
      height: 28,
      borderRadius: "var(--border-radius-small)",
      background: c,
      border: value === c ? "2px solid var(--primary-text-color)" : "none",
      cursor: "pointer",
      transition: "transform var(--motion-productive-short) var(--motion-timing-enter)"
    },
    onMouseEnter: e => e.currentTarget.style.transform = "scale(1.1)",
    onMouseLeave: e => e.currentTarget.style.transform = "scale(1)"
  })));
}
Object.assign(__ds_scope, { Slider, ProgressBar, ColorPicker });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Slider.jsx", error: String((e && e.message) || e) }); }

// components/forms/TextField.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const H = {
  small: 32,
  medium: 40,
  large: 48
};
function FieldShell({
  label,
  required,
  subText,
  validation,
  children,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      width: "100%",
      ...style
    }
  }, label ? /*#__PURE__*/React.createElement("label", {
    style: {
      display: "block",
      font: "var(--font-text2-normal)",
      color: "var(--primary-text-color)",
      paddingBlock: "var(--space-4)"
    }
  }, label, required ? /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--negative-color)"
    }
  }, " *") : null) : null, children, subText || validation ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      paddingBlock: 1,
      font: "var(--font-text3-normal)",
      color: validation === "error" ? "var(--negative-color)" : validation === "success" ? "var(--positive-color)" : "var(--secondary-text-color)"
    }
  }, subText) : null);
}
function borderColor(validation, focus, hover, disabled) {
  if (disabled) return "transparent";
  if (validation === "error") return "var(--negative-color)";
  if (validation === "success") return "var(--positive-color)";
  if (focus) return "var(--primary-color)";
  if (hover) return "var(--primary-text-color)";
  return "var(--ui-border-color)";
}
function TextField({
  value,
  onChange,
  placeholder = "",
  label,
  size = "medium",
  iconName,
  trailingIconName,
  disabled = false,
  readOnly = false,
  required = false,
  validation,
  subText,
  type = "text",
  style,
  wrapperStyle,
  ...rest
}) {
  const [focus, setFocus] = React.useState(false);
  const [hover, setHover] = React.useState(false);
  const h = H[size] || 40;
  const font = size === "small" ? "var(--font-text2-normal)" : "var(--font-text1-normal)";
  return /*#__PURE__*/React.createElement(FieldShell, {
    label: label,
    required: required,
    subText: subText,
    validation: validation,
    style: wrapperStyle
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      height: h,
      width: "100%"
    },
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false)
  }, iconName ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: iconName,
    size: 16,
    color: "var(--icon-color)",
    style: {
      position: "absolute",
      insetInlineStart: 10,
      top: "50%",
      transform: "translateY(-50%)",
      pointerEvents: "none"
    }
  }) : null, /*#__PURE__*/React.createElement("input", _extends({
    type: type,
    value: value,
    onChange: onChange,
    placeholder: placeholder,
    disabled: disabled,
    readOnly: readOnly,
    onFocus: () => setFocus(true),
    onBlur: () => setFocus(false),
    style: {
      width: "100%",
      height: "100%",
      outline: 0,
      font,
      color: disabled ? "var(--disabled-text-color)" : "var(--primary-text-color)",
      background: disabled ? "var(--disabled-background-color)" : readOnly ? "var(--allgrey-background-color)" : "var(--secondary-background-color)",
      border: readOnly || disabled ? "none" : "1px solid",
      borderColor: borderColor(validation, focus, hover, disabled),
      borderRadius: "var(--border-radius-small)",
      transition: "border-color var(--motion-productive-medium) ease-in",
      padding: iconName ? "var(--space-8) var(--space-12) var(--space-8) 32px" : "var(--space-8) var(--space-12)",
      textOverflow: "ellipsis",
      ...style
    }
  }, rest)), trailingIconName ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: trailingIconName,
    size: 16,
    color: "var(--icon-color)",
    style: {
      position: "absolute",
      insetInlineEnd: 10,
      top: "50%",
      transform: "translateY(-50%)"
    }
  }) : null));
}
function TextArea({
  value,
  onChange,
  placeholder = "",
  label,
  rows = 4,
  disabled = false,
  validation,
  subText,
  maxLength,
  style,
  ...rest
}) {
  const [focus, setFocus] = React.useState(false);
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement(FieldShell, {
    label: label,
    subText: maxLength ? `${String(value || "").length}/${maxLength}` : subText,
    validation: validation
  }, /*#__PURE__*/React.createElement("textarea", _extends({
    value: value,
    onChange: onChange,
    rows: rows,
    placeholder: placeholder,
    disabled: disabled,
    maxLength: maxLength,
    onFocus: () => setFocus(true),
    onBlur: () => setFocus(false),
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      width: "100%",
      outline: 0,
      resize: "vertical",
      font: "var(--font-text1-normal)",
      color: "var(--primary-text-color)",
      background: disabled ? "var(--disabled-background-color)" : "var(--secondary-background-color)",
      border: "1px solid",
      borderColor: borderColor(validation, focus, hover, disabled),
      borderRadius: "var(--border-radius-small)",
      padding: "var(--space-8) var(--space-12)",
      transition: "border-color var(--motion-productive-medium) ease-in",
      ...style
    }
  }, rest)));
}
function Search({
  value,
  onChange,
  placeholder = "Search",
  size = "medium",
  onClear,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      width: "100%",
      ...style
    }
  }, /*#__PURE__*/React.createElement(TextField, {
    value: value,
    onChange: onChange,
    placeholder: placeholder,
    size: size,
    iconName: "Search"
  }), value ? /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Clear search",
    onClick: onClear,
    style: {
      position: "absolute",
      insetInlineEnd: 6,
      top: "50%",
      transform: "translateY(-50%)",
      width: 24,
      height: 24,
      border: "none",
      background: "transparent",
      cursor: "pointer",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      color: "var(--icon-color)"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "CloseSmall",
    size: 16
  })) : null);
}
function NumberField({
  value = 0,
  onChange,
  label,
  min,
  max,
  step = 1,
  size = "medium",
  disabled = false,
  style
}) {
  const bump = d => {
    let next = Number(value) + d * step;
    if (typeof min === "number") next = Math.max(min, next);
    if (typeof max === "number") next = Math.min(max, next);
    if (onChange) onChange(next);
  };
  const h = H[size] || 40;
  return /*#__PURE__*/React.createElement(FieldShell, {
    label: label
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      height: h,
      ...style
    }
  }, /*#__PURE__*/React.createElement(TextField, {
    value: String(value),
    onChange: e => onChange && onChange(Number(e.target.value)),
    size: size,
    disabled: disabled,
    style: {
      paddingInlineEnd: 28
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      insetInlineEnd: 4,
      top: 4,
      bottom: 4,
      display: "grid",
      gridTemplateRows: "1fr 1fr"
    }
  }, [1, -1].map(d => /*#__PURE__*/React.createElement("button", {
    key: d,
    type: "button",
    "aria-label": d > 0 ? "Increase" : "Decrease",
    onClick: () => bump(d),
    style: {
      width: 20,
      border: "none",
      background: "transparent",
      color: "var(--icon-color)",
      cursor: "pointer",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      padding: 0
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: d > 0 ? "DropdownChevronDown" : "DropdownChevronDown",
    size: 12,
    style: d > 0 ? {
      transform: "rotate(180deg)"
    } : undefined
  }))))));
}
Object.assign(__ds_scope, { TextField, TextArea, Search, NumberField });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/TextField.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Menu.jsx
try { (() => {
function Menu({
  items = [],
  onSelect,
  style
}) {
  return /*#__PURE__*/React.createElement(__ds_scope.DialogContentContainer, {
    style: {
      minWidth: 200,
      ...style
    }
  }, items.map((it, i) => it.divider ? /*#__PURE__*/React.createElement("span", {
    key: `d${i}`,
    style: {
      display: "block",
      height: 1,
      background: "var(--ui-border-color)",
      margin: "var(--space-4) 0"
    }
  }) : it.title ? /*#__PURE__*/React.createElement("div", {
    key: `t${i}`,
    style: {
      padding: "var(--space-8) var(--space-8) var(--space-4)",
      font: "var(--font-text3-medium)",
      color: "var(--secondary-text-color)"
    }
  }, it.title) : /*#__PURE__*/React.createElement("button", {
    key: it.value || it.label,
    type: "button",
    disabled: it.disabled,
    onClick: () => onSelect && onSelect(it.value || it.label),
    style: {
      width: "100%",
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      height: 32,
      padding: "0 var(--space-8)",
      border: "none",
      borderRadius: "var(--border-radius-small)",
      background: "transparent",
      cursor: it.disabled ? "not-allowed" : "pointer",
      font: "var(--font-text2-normal)",
      color: it.disabled ? "var(--disabled-text-color)" : it.destructive ? "var(--negative-color)" : "var(--primary-text-color)",
      textAlign: "start"
    },
    onMouseEnter: e => {
      if (!it.disabled) e.currentTarget.style.background = "var(--primary-background-hover-color)";
    },
    onMouseLeave: e => e.currentTarget.style.background = "transparent"
  }, it.icon ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: it.icon,
    size: 16,
    color: "currentColor"
  }) : null, /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1
    }
  }, it.label), it.shortcut ? /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-text3-normal)",
      color: "var(--secondary-text-color)"
    }
  }, it.shortcut) : null, it.submenu ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "DropdownChevronRight",
    size: 14,
    color: "var(--icon-color)"
  }) : null)));
}
function MenuButton({
  items = [],
  onSelect,
  ariaLabel = "More actions",
  iconName = "MoreActions",
  size = "small",
  children,
  style
}) {
  const [open, setOpen] = React.useState(false);
  const ref = React.useRef(null);
  React.useEffect(() => {
    const close = e => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, []);
  const box = {
    xs: 24,
    small: 32,
    medium: 40
  }[size] || 32;
  return /*#__PURE__*/React.createElement("span", {
    ref: ref,
    style: {
      position: "relative",
      display: "inline-flex",
      ...style
    }
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": ariaLabel,
    onClick: () => setOpen(o => !o),
    style: {
      width: children ? undefined : box,
      height: box,
      padding: children ? "0 var(--space-12)" : 0,
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      gap: "var(--space-4)",
      border: "none",
      borderRadius: "var(--border-radius-small)",
      background: open ? "var(--primary-background-hover-color)" : "transparent",
      color: "var(--icon-color)",
      cursor: "pointer",
      font: "var(--font-text2-normal)"
    }
  }, children, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: iconName,
    size: 16
  })), open ? /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      top: "calc(100% + 4px)",
      insetInlineEnd: 0,
      zIndex: 60
    }
  }, /*#__PURE__*/React.createElement(Menu, {
    items: items,
    onSelect: v => {
      setOpen(false);
      onSelect && onSelect(v);
    }
  })) : null);
}
function Steps({
  steps = [],
  activeStep = 0,
  onChange,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-16)",
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      gap: "var(--space-4)"
    }
  }, steps.map((s, i) => /*#__PURE__*/React.createElement("span", {
    key: i,
    onClick: () => onChange && onChange(i),
    style: {
      width: i === activeStep ? 20 : 8,
      height: 8,
      borderRadius: 4,
      background: i === activeStep ? "var(--primary-color)" : "var(--ui-border-color)",
      cursor: onChange ? "pointer" : "default",
      transition: "width var(--motion-productive-long) var(--motion-timing-transition)"
    }
  }))), /*#__PURE__*/React.createElement("div", null, steps[activeStep]));
}
function MultiStepIndicator({
  steps = [],
  activeStep = 0,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "flex-start",
      ...style
    }
  }, steps.map((s, i) => {
    const state = i < activeStep ? "done" : i === activeStep ? "active" : "pending";
    const bg = state === "done" ? "var(--positive-color)" : state === "active" ? "var(--primary-color)" : "transparent";
    const fg = state === "pending" ? "var(--secondary-text-color)" : "var(--text-color-on-primary)";
    return /*#__PURE__*/React.createElement(React.Fragment, {
      key: s.titleText || i
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        gap: "var(--space-4)",
        minWidth: 96
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 24,
        height: 24,
        borderRadius: "50%",
        background: bg,
        border: state === "pending" ? "1px solid var(--ui-border-color)" : "none",
        color: fg,
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        font: "var(--font-text3-medium)"
      }
    }, state === "done" ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
      name: "Check",
      size: 14,
      color: "var(--text-color-on-primary)"
    }) : i + 1), /*#__PURE__*/React.createElement("span", {
      style: {
        font: state === "active" ? "var(--font-text2-medium)" : "var(--font-text2-normal)",
        color: state === "pending" ? "var(--secondary-text-color)" : "var(--primary-text-color)",
        textAlign: "center"
      }
    }, s.titleText), s.subtitleText ? /*#__PURE__*/React.createElement("span", {
      style: {
        font: "var(--font-text3-normal)",
        color: "var(--secondary-text-color)",
        textAlign: "center"
      }
    }, s.subtitleText) : null), i < steps.length - 1 ? /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 1,
        height: 1,
        background: i < activeStep ? "var(--positive-color)" : "var(--ui-border-color)",
        marginTop: 12
      }
    }) : null);
  }));
}
Object.assign(__ds_scope, { Menu, MenuButton, Steps, MultiStepIndicator });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Menu.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Tabs.jsx
try { (() => {
function Tabs({
  tabs = [],
  value,
  onChange,
  size = "medium",
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-4)",
      borderBottom: "1px solid var(--layout-border-color)",
      ...style
    }
  }, tabs.map(t => {
    const active = t.value === value;
    return /*#__PURE__*/React.createElement("button", {
      key: t.value,
      type: "button",
      onClick: () => !t.disabled && onChange && onChange(t.value),
      style: {
        display: "inline-flex",
        alignItems: "center",
        gap: "var(--space-8)",
        height: size === "small" ? 32 : 40,
        padding: "0 var(--space-12)",
        border: "none",
        background: "transparent",
        cursor: t.disabled ? "not-allowed" : "pointer",
        font: active ? "var(--font-text2-medium)" : "var(--font-text2-normal)",
        color: t.disabled ? "var(--disabled-text-color)" : active ? "var(--primary-color)" : "var(--secondary-text-color)",
        boxShadow: active ? "inset 0 -2px 0 var(--primary-color)" : "none",
        transition: "color var(--motion-productive-medium)"
      }
    }, t.icon ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
      name: t.icon,
      size: 16
    }) : null, t.label, typeof t.count === "number" ? /*#__PURE__*/React.createElement("span", {
      style: {
        minWidth: 18,
        padding: "0 6px",
        borderRadius: 30,
        background: active ? "var(--primary-selected-color)" : "var(--ui-background-color)",
        color: active ? "var(--primary-color)" : "var(--secondary-text-color)",
        font: "var(--font-text3-medium)"
      }
    }, t.count) : null);
  }));
}
function BreadcrumbsBar({
  items = [],
  style
}) {
  return /*#__PURE__*/React.createElement("nav", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-4)",
      font: "var(--font-text2-normal)",
      ...style
    }
  }, items.map((it, i) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: it.text
  }, i > 0 ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "NavigationChevronRight",
    size: 14,
    color: "var(--icon-color)"
  }) : null, /*#__PURE__*/React.createElement("span", {
    onClick: it.onClick,
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: "var(--space-4)",
      padding: "2px var(--space-4)",
      borderRadius: "var(--border-radius-small)",
      color: i === items.length - 1 ? "var(--primary-text-color)" : "var(--secondary-text-color)",
      cursor: it.onClick ? "pointer" : "default"
    }
  }, it.icon ? /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: it.icon,
    size: 14
  }) : null, it.text))));
}
function Divider({
  direction = "horizontal",
  style
}) {
  return direction === "vertical" ? /*#__PURE__*/React.createElement("span", {
    style: {
      width: 1,
      alignSelf: "stretch",
      background: "var(--ui-border-color)",
      ...style
    }
  }) : /*#__PURE__*/React.createElement("span", {
    style: {
      display: "block",
      height: 1,
      width: "100%",
      background: "var(--ui-border-color)",
      ...style
    }
  });
}
function Accordion({
  items = [],
  allowMultiple = false,
  style
}) {
  const [open, setOpen] = React.useState([]);
  const toggle = i => setOpen(prev => prev.includes(i) ? prev.filter(x => x !== i) : allowMultiple ? [...prev, i] : [i]);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      ...style
    }
  }, items.map((it, i) => {
    const isOpen = open.includes(i);
    return /*#__PURE__*/React.createElement("div", {
      key: it.title,
      style: {
        borderBottom: "1px solid var(--layout-border-color)"
      }
    }, /*#__PURE__*/React.createElement("button", {
      type: "button",
      onClick: () => toggle(i),
      style: {
        width: "100%",
        display: "flex",
        alignItems: "center",
        gap: "var(--space-8)",
        minHeight: 40,
        padding: "var(--space-8) var(--space-4)",
        border: "none",
        background: "transparent",
        cursor: "pointer",
        font: "var(--font-text2-medium)",
        color: "var(--primary-text-color)",
        textAlign: "start"
      }
    }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
      name: "DropdownChevronRight",
      size: 16,
      color: "var(--icon-color)",
      style: {
        transform: isOpen ? "rotate(90deg)" : "none",
        transition: "transform var(--motion-productive-medium) var(--motion-timing-transition)"
      }
    }), /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 1
      }
    }, it.title), it.adornment), isOpen ? /*#__PURE__*/React.createElement("div", {
      style: {
        padding: "0 var(--space-4) var(--space-12) 32px",
        font: "var(--font-text2-normal)",
        color: "var(--primary-text-color)"
      }
    }, it.content) : null);
  }));
}
function ExpandCollapse({
  title,
  children,
  defaultOpen = false,
  style
}) {
  const [open, setOpen] = React.useState(defaultOpen);
  return /*#__PURE__*/React.createElement("div", {
    style: style
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => setOpen(!open),
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      border: "none",
      background: "transparent",
      cursor: "pointer",
      font: "var(--font-text2-medium)",
      color: "var(--primary-text-color)",
      padding: "var(--space-4) 0"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "DropdownChevronRight",
    size: 16,
    color: "var(--icon-color)",
    style: {
      transform: open ? "rotate(90deg)" : "none",
      transition: "transform var(--motion-productive-medium)"
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1,
      minWidth: 0,
      whiteSpace: "nowrap",
      overflow: "hidden",
      textOverflow: "ellipsis",
      textAlign: "start"
    }
  }, title)), open ? /*#__PURE__*/React.createElement("div", {
    style: {
      paddingInlineStart: 24
    }
  }, children) : null);
}
Object.assign(__ds_scope, { Tabs, BreadcrumbsBar, Divider, Accordion, ExpandCollapse });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Tabs.jsx", error: String((e && e.message) || e) }); }

// components/overlays/Modal.jsx
try { (() => {
const WIDTHS = {
  small: 480,
  medium: 580,
  large: 840,
  fullView: "calc(100% - 80px)"
};
function Modal({
  open = true,
  onClose,
  title,
  description,
  children,
  footer,
  size = "medium",
  showCloseButton = true,
  style
}) {
  if (!open) return null;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: "fixed",
      inset: 0,
      zIndex: 10000
    }
  }, /*#__PURE__*/React.createElement("div", {
    onClick: onClose,
    style: {
      position: "absolute",
      inset: 0,
      background: "var(--backdrop-color)"
    }
  }), /*#__PURE__*/React.createElement("div", {
    role: "dialog",
    "aria-modal": "true",
    style: {
      position: "absolute",
      top: "50%",
      left: "50%",
      transform: "translate(-50%, -50%)",
      display: "flex",
      flexDirection: "column",
      width: WIDTHS[size] || 580,
      maxHeight: size === "small" ? "50%" : "80%",
      background: "var(--primary-background-color)",
      borderRadius: "var(--border-radius-big)",
      boxShadow: "var(--box-shadow-large)",
      overflow: "hidden",
      animation: "ozeeModalIn 150ms cubic-bezier(0,0,0.4,1)",
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "flex-start",
      gap: "var(--space-8)",
      padding: "var(--space-24) var(--space-32) var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, title ? /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: 0,
      font: "var(--font-h3-medium)",
      letterSpacing: "var(--letter-spacing-h3-bold)",
      color: "var(--primary-text-color)"
    }
  }, title) : null, description ? /*#__PURE__*/React.createElement("p", {
    style: {
      margin: "var(--space-4) 0 0",
      font: "var(--font-text2-normal)",
      color: "var(--secondary-text-color)"
    }
  }, description) : null), showCloseButton ? /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Close",
    onClick: onClose,
    style: {
      width: 32,
      height: 32,
      border: "none",
      borderRadius: "var(--border-radius-small)",
      background: "transparent",
      color: "var(--icon-color)",
      cursor: "pointer",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center"
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "Close",
    size: 20
  })) : null), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-8) var(--space-32)",
      overflow: "auto",
      flex: 1,
      font: "var(--font-text2-normal)",
      color: "var(--primary-text-color)"
    }
  }, children), footer ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      justifyContent: "flex-end",
      gap: "var(--space-8)",
      padding: "var(--space-16) var(--space-32) var(--space-24)"
    }
  }, footer) : null, /*#__PURE__*/React.createElement("style", null, "@keyframes ozeeModalIn{0%{opacity:0;transform:translate(-50%,-50%) scale(.8)}100%{opacity:1;transform:translate(-50%,-50%) scale(1)}}")));
}
Object.assign(__ds_scope, { Modal });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/overlays/Modal.jsx", error: String((e && e.message) || e) }); }

// components/typography/Heading.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const HEADING_FONTS = {
  h1: {
    bold: "var(--font-h1-bold)",
    medium: "var(--font-h1-medium)",
    normal: "var(--font-h1-normal)",
    light: "var(--font-h1-light)",
    ls: "var(--letter-spacing-h1-bold)"
  },
  h2: {
    bold: "var(--font-h2-bold)",
    medium: "var(--font-h2-medium)",
    normal: "var(--font-h2-normal)",
    light: "var(--font-h2-light)",
    ls: "var(--letter-spacing-h2-bold)"
  },
  h3: {
    bold: "var(--font-h3-bold)",
    medium: "var(--font-h3-medium)",
    normal: "var(--font-h3-normal)",
    light: "var(--font-h3-light)",
    ls: "var(--letter-spacing-h3-bold)"
  }
};
const COLORS = {
  primary: "var(--primary-text-color)",
  secondary: "var(--secondary-text-color)",
  onPrimary: "var(--text-color-on-primary)",
  onInverted: "var(--text-color-on-inverted)",
  fixedLight: "var(--fixed-light-color)",
  positive: "var(--positive-color)",
  negative: "var(--negative-color)"
};
function Heading({
  type = "h2",
  weight = "medium",
  color = "primary",
  align = "start",
  ellipsis = false,
  children,
  style,
  ...rest
}) {
  const Tag = type;
  const f = HEADING_FONTS[type] || HEADING_FONTS.h2;
  return /*#__PURE__*/React.createElement(Tag, _extends({
    style: {
      margin: 0,
      font: f[weight] || f.medium,
      letterSpacing: f.ls,
      color: COLORS[color] || color,
      textAlign: align,
      ...(ellipsis ? {
        overflow: "hidden",
        textOverflow: "ellipsis",
        whiteSpace: "nowrap"
      } : null),
      ...style
    }
  }, rest), children);
}
const TEXT_FONTS = {
  text1: {
    bold: "var(--font-text1-bold)",
    medium: "var(--font-text1-medium)",
    normal: "var(--font-text1-normal)"
  },
  text2: {
    bold: "var(--font-text2-bold)",
    medium: "var(--font-text2-medium)",
    normal: "var(--font-text2-normal)"
  },
  text3: {
    bold: "var(--font-text3-bold)",
    medium: "var(--font-text3-medium)",
    normal: "var(--font-text3-normal)"
  }
};
function Text({
  type = "text2",
  weight = "normal",
  color = "primary",
  element = "span",
  ellipsis = false,
  children,
  style,
  ...rest
}) {
  const Tag = element;
  const f = TEXT_FONTS[type] || TEXT_FONTS.text2;
  return /*#__PURE__*/React.createElement(Tag, _extends({
    style: {
      margin: 0,
      font: f[weight] || f.normal,
      color: COLORS[color] || color,
      ...(ellipsis ? {
        display: "block",
        overflow: "hidden",
        textOverflow: "ellipsis",
        whiteSpace: "nowrap"
      } : null),
      ...style
    }
  }, rest), children);
}
function TextWithHighlight({
  text = "",
  highlightTerm = "",
  type = "text2",
  style
}) {
  if (!highlightTerm) return /*#__PURE__*/React.createElement(Text, {
    type: type,
    style: style
  }, text);
  const parts = String(text).split(new RegExp(`(${highlightTerm.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`, "ig"));
  return /*#__PURE__*/React.createElement(Text, {
    type: type,
    style: style
  }, parts.map((p, i) => p.toLowerCase() === highlightTerm.toLowerCase() ? /*#__PURE__*/React.createElement("mark", {
    key: i,
    style: {
      background: "var(--primary-selected-color)",
      color: "inherit",
      borderRadius: 2
    }
  }, p) : /*#__PURE__*/React.createElement(React.Fragment, {
    key: i
  }, p)));
}
function FormattedNumber({
  value = 0,
  prefix = "",
  suffix = "",
  decimalPrecision = 0,
  local = "en-AU",
  style,
  ...rest
}) {
  const n = Number(value);
  const abbreviated = Math.abs(n) >= 1000000 ? {
    v: n / 1000000,
    u: "M"
  } : Math.abs(n) >= 1000 ? {
    v: n / 1000,
    u: "K"
  } : {
    v: n,
    u: ""
  };
  const shown = abbreviated.u ? abbreviated.v.toFixed(1).replace(/\.0$/, "") + abbreviated.u : n.toLocaleString(local, {
    minimumFractionDigits: decimalPrecision,
    maximumFractionDigits: decimalPrecision
  });
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      font: "var(--font-text1-medium)",
      color: "var(--primary-text-color)",
      ...style
    }
  }, rest), prefix, shown, suffix);
}
function EditableText({
  value = "",
  onChange,
  type = "text2",
  weight = "normal",
  placeholder = "Add text",
  style
}) {
  const [editing, setEditing] = React.useState(false);
  const [draft, setDraft] = React.useState(value);
  React.useEffect(() => setDraft(value), [value]);
  const commit = () => {
    setEditing(false);
    if (onChange && draft !== value) onChange(draft);
  };
  const font = (TEXT_FONTS[type] || TEXT_FONTS.text2)[weight] || "var(--font-text2-normal)";
  if (editing) {
    return /*#__PURE__*/React.createElement("input", {
      autoFocus: true,
      value: draft,
      placeholder: placeholder,
      onChange: e => setDraft(e.target.value),
      onBlur: commit,
      onKeyDown: e => {
        if (e.key === "Enter") commit();
        if (e.key === "Escape") {
          setDraft(value);
          setEditing(false);
        }
      },
      style: {
        font,
        color: "var(--primary-text-color)",
        padding: "2px var(--space-4)",
        border: "1px solid var(--primary-color)",
        borderRadius: "var(--border-radius-small)",
        background: "var(--secondary-background-color)",
        ...style
      }
    });
  }
  return /*#__PURE__*/React.createElement("span", {
    tabIndex: 0,
    onClick: () => setEditing(true),
    onKeyDown: e => {
      if (e.key === "Enter") setEditing(true);
    },
    style: {
      font,
      color: value ? "var(--primary-text-color)" : "var(--placeholder-color)",
      padding: "2px var(--space-4)",
      borderRadius: "var(--border-radius-small)",
      cursor: "text",
      display: "inline-block",
      ...style
    }
  }, value || placeholder);
}
function EditableHeading({
  value = "",
  onChange,
  type = "h2",
  placeholder = "Untitled",
  style
}) {
  const [editing, setEditing] = React.useState(false);
  const [draft, setDraft] = React.useState(value);
  React.useEffect(() => setDraft(value), [value]);
  const f = HEADING_FONTS[type] || HEADING_FONTS.h2;
  const commit = () => {
    setEditing(false);
    if (onChange && draft !== value) onChange(draft);
  };
  if (editing) {
    return /*#__PURE__*/React.createElement("input", {
      autoFocus: true,
      value: draft,
      onChange: e => setDraft(e.target.value),
      onBlur: commit,
      onKeyDown: e => {
        if (e.key === "Enter") commit();
      },
      style: {
        font: f.medium,
        letterSpacing: f.ls,
        color: "var(--primary-text-color)",
        border: "1px solid var(--primary-color)",
        borderRadius: "var(--border-radius-small)",
        padding: "0 var(--space-4)",
        ...style
      }
    });
  }
  return /*#__PURE__*/React.createElement(Heading, {
    type: type,
    onClick: () => setEditing(true),
    style: {
      cursor: "text",
      ...style
    }
  }, value || placeholder);
}
Object.assign(__ds_scope, { Heading, Text, TextWithHighlight, FormattedNumber, EditableText, EditableHeading });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/typography/Heading.jsx", error: String((e && e.message) || e) }); }

// ui_kits/crm/Approvals.jsx
try { (() => {
const {
  Button,
  IconButton,
  Icon,
  Text,
  Heading,
  Label,
  Avatar,
  Tabs,
  Search,
  Chips,
  EmptyState,
  Divider,
  TextArea,
  Tooltip,
  MenuButton
} = window.OZeeCRMDesignSystem_3d3bcd;
function ApprovalsScreen({
  onToast
}) {
  const d = window.CRM_DATA;
  const [tab, setTab] = React.useState("pending");
  const [selectedId, setSelectedId] = React.useState(d.emails[0].id);
  const [decided, setDecided] = React.useState({});
  const [note, setNote] = React.useState("");
  const list = d.emails.filter(e => tab === "pending" ? (decided[e.id] || e.status) === "Pending" : tab === "approved" ? (decided[e.id] || e.status) === "Approved" : (decided[e.id] || e.status) === "Rejected");
  const selected = d.emails.find(e => e.id === selectedId);
  const decide = verdict => {
    setDecided(p => ({
      ...p,
      [selectedId]: verdict
    }));
    setNote("");
    onToast(verdict === "Approved" ? "Email approved and sent" : "Email sent back to the sender", verdict === "Approved" ? "positive" : "negative");
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "380px 1fr",
      height: "100%",
      minHeight: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      borderInlineEnd: "1px solid var(--layout-border-color)",
      background: "var(--primary-background-color)",
      display: "flex",
      flexDirection: "column",
      minHeight: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-16) var(--space-16) var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Heading, {
    type: "h3"
  }, "Email approvals"), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary",
    element: "div",
    style: {
      marginTop: 2
    }
  }, "Client emails wait here until a manager approves them."), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: "var(--space-12)"
    }
  }, /*#__PURE__*/React.createElement(Search, {
    size: "small",
    placeholder: "Search approvals"
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "0 var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Tabs, {
    size: "small",
    value: tab,
    onChange: setTab,
    tabs: [{
      value: "pending",
      label: "Pending",
      count: d.emails.filter(e => (decided[e.id] || e.status) === "Pending").length
    }, {
      value: "approved",
      label: "Approved"
    }, {
      value: "rejected",
      label: "Rejected"
    }]
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      overflow: "auto",
      flex: 1,
      padding: "var(--space-8)"
    }
  }, list.length === 0 ? /*#__PURE__*/React.createElement(EmptyState, {
    iconName: "Completed",
    title: "Nothing here",
    description: "All caught up.",
    style: {
      padding: "var(--space-32) var(--space-16)"
    }
  }) : list.map(e => {
    const on = e.id === selectedId;
    return /*#__PURE__*/React.createElement("div", {
      key: e.id,
      onClick: () => setSelectedId(e.id),
      style: {
        display: "flex",
        gap: "var(--space-8)",
        padding: "var(--space-12)",
        borderRadius: "var(--border-radius-small)",
        cursor: "pointer",
        background: on ? "var(--primary-selected-color)" : "transparent",
        marginBottom: 2
      },
      onMouseEnter: ev => {
        if (!on) ev.currentTarget.style.background = "var(--primary-background-hover-color)";
      },
      onMouseLeave: ev => {
        if (!on) ev.currentTarget.style.background = "transparent";
      }
    }, /*#__PURE__*/React.createElement(Avatar, {
      text: e.sender,
      size: "medium"
    }), /*#__PURE__*/React.createElement("div", {
      style: {
        flex: 1,
        minWidth: 0
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        gap: "var(--space-8)"
      }
    }, /*#__PURE__*/React.createElement(Text, {
      type: "text2",
      weight: "medium",
      style: {
        flex: 1
      },
      ellipsis: true
    }, e.client), /*#__PURE__*/React.createElement(Text, {
      type: "text3",
      color: "secondary"
    }, e.when)), /*#__PURE__*/React.createElement(Text, {
      type: "text2",
      element: "div",
      ellipsis: true
    }, e.subject), /*#__PURE__*/React.createElement(Text, {
      type: "text3",
      color: "secondary",
      element: "div",
      ellipsis: true
    }, "from ", e.sender)));
  }))), selected ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      minHeight: 0,
      background: "var(--allgrey-background-color)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      borderBottom: "1px solid var(--layout-border-color)",
      padding: "var(--space-16) var(--space-24)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "flex-start",
      gap: "var(--space-12)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Heading, {
    type: "h3",
    ellipsis: true,
    style: {
      minWidth: 0
    }
  }, selected.subject), /*#__PURE__*/React.createElement(Label, {
    text: decided[selected.id] || selected.status,
    color: (decided[selected.id] || selected.status) === "Approved" ? "positive" : (decided[selected.id] || selected.status) === "Rejected" ? "negative" : "primary",
    size: "small"
  })), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary",
    element: "div",
    style: {
      marginTop: 4
    }
  }, "To ", selected.to, " \xB7 drafted by ", selected.sender, " \xB7 ", selected.when)), /*#__PURE__*/React.createElement(Tooltip, {
    content: "Reply in thread"
  }, /*#__PURE__*/React.createElement(IconButton, {
    name: "Reply",
    size: "small",
    ariaLabel: "Reply"
  })), /*#__PURE__*/React.createElement(MenuButton, {
    items: [{
      label: "Edit draft",
      icon: "Edit"
    }, {
      label: "Reassign",
      icon: "Person"
    }, {
      divider: true
    }, {
      label: "Discard",
      icon: "Delete",
      destructive: true
    }]
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      overflow: "auto",
      padding: "var(--space-24)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "var(--space-24)",
      maxWidth: 720
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: "var(--space-8)",
      marginBottom: "var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement(Chips, {
    label: selected.client,
    color: "primary",
    size: "small"
  }), /*#__PURE__*/React.createElement(Chips, {
    label: "Client email",
    color: "neutral",
    size: "small"
  })), /*#__PURE__*/React.createElement("pre", {
    style: {
      margin: 0,
      font: "var(--font-text1-normal)",
      color: "var(--primary-text-color)",
      whiteSpace: "pre-wrap",
      fontFamily: "var(--font-family)"
    }
  }, selected.body), /*#__PURE__*/React.createElement(Divider, {
    style: {
      margin: "var(--space-24) 0 var(--space-16)"
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/ozee-logo-sm.png",
    alt: "",
    style: {
      height: 22
    }
  }), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary"
  }, "OZeeWeb.com.au \xB7 +61 456 639 389 \xB7 Thornlie, WA 6108"))), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 720,
      marginTop: "var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement(TextArea, {
    label: "Note to the sender (optional)",
    rows: 2,
    value: note,
    onChange: e => setNote(e.target.value),
    placeholder: "Ask for a change before this goes out\u2026"
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      borderTop: "1px solid var(--layout-border-color)",
      padding: "var(--space-12) var(--space-24)",
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary",
    style: {
      flex: 1
    }
  }, "Approving sends the email immediately from the sender's account."), /*#__PURE__*/React.createElement(Button, {
    kind: "secondary",
    color: "negative",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Clear",
      size: 16
    }),
    onClick: () => decide("Rejected")
  }, "Send back"), /*#__PURE__*/React.createElement(Button, {
    color: "positive",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Check",
      size: 16
    }),
    onClick: () => decide("Approved")
  }, "Approve & send"))) : null);
}
Object.assign(window, {
  ApprovalsScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/crm/Approvals.jsx", error: String((e && e.message) || e) }); }

// ui_kits/crm/Dashboard.jsx
try { (() => {
const {
  Button,
  IconButton,
  MenuButton,
  Icon,
  Text,
  Heading,
  Table,
  Label,
  Avatar,
  AvatarGroup,
  Chips,
  ProgressBar,
  AttentionBox,
  EmptyState,
  Counter,
  Tooltip,
  Checkbox
} = window.OZeeCRMDesignSystem_3d3bcd;
function Card({
  title,
  action,
  children,
  span = 1,
  style
}) {
  return /*#__PURE__*/React.createElement("section", {
    style: {
      gridColumn: `span ${span}`,
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      display: "flex",
      flexDirection: "column",
      ...style
    }
  }, title ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      padding: "var(--space-12) var(--space-16)",
      borderBottom: "1px solid var(--layout-border-color)"
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    weight: "medium",
    style: {
      flex: 1
    }
  }, title), action) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-16)",
      flex: 1
    }
  }, children));
}
function StatTile({
  value,
  label,
  tone = "var(--primary-color)",
  icon
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-12)"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 40,
      height: 40,
      borderRadius: "var(--border-radius-medium)",
      background: "var(--allgrey-background-color)",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: icon,
    size: 20,
    color: tone
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-h3-bold)",
      color: "var(--primary-text-color)"
    }
  }, value), /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-text3-normal)",
      color: "var(--secondary-text-color)"
    }
  }, label)));
}
function DashboardScreen({
  onOpenTask,
  onOpenProject,
  onNewTask
}) {
  const d = window.CRM_DATA;
  const [done, setDone] = React.useState([16]);
  const toggle = id => setDone(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  const projects = d.groups.flatMap(g => g.projects);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-24)",
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement(AttentionBox, {
    type: "danger",
    title: "3 tasks need attention today"
  }, "One is overdue on Coast Cafe \u2014 online ordering."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(4, 1fr)",
      gap: "var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement(StatTile, {
    value: "6",
    label: "Active projects",
    icon: "Board"
  })), /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement(StatTile, {
    value: "18",
    label: "Open tasks",
    icon: "CheckList",
    tone: "var(--color-working-orange)"
  })), /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement(StatTile, {
    value: "3",
    label: "Emails awaiting approval",
    icon: "Email",
    tone: "var(--color-stuck-red)"
  })), /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement(StatTile, {
    value: "92%",
    label: "On-time delivery (Aug)",
    icon: "Chart",
    tone: "var(--positive-color)"
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "2fr 1fr",
      gap: "var(--space-16)",
      alignItems: "start"
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "My tasks",
    action: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Chips, {
      label: "Due today \xB7 2",
      color: "warning",
      size: "small"
    }), /*#__PURE__*/React.createElement(Button, {
      size: "xs",
      kind: "tertiary",
      leftIcon: /*#__PURE__*/React.createElement(Icon, {
        name: "Add",
        size: 14
      }),
      onClick: onNewTask
    }, "Add")),
    style: {
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement(Table, {
    border: false,
    columns: [{
      id: "check",
      title: "",
      width: "32px",
      render: r => /*#__PURE__*/React.createElement(Checkbox, {
        checked: done.includes(r.id),
        onChange: () => toggle(r.id)
      })
    }, {
      id: "name",
      title: "Task",
      width: "2.2fr",
      render: r => /*#__PURE__*/React.createElement("span", {
        onClick: () => onOpenTask(r),
        style: {
          cursor: "pointer",
          textDecoration: done.includes(r.id) ? "line-through" : "none",
          color: done.includes(r.id) ? "var(--secondary-text-color)" : "var(--primary-text-color)",
          overflow: "hidden",
          textOverflow: "ellipsis",
          whiteSpace: "nowrap"
        }
      }, r.name)
    }, {
      id: "project",
      title: "Project",
      width: "1.2fr",
      render: r => /*#__PURE__*/React.createElement(Text, {
        type: "text2",
        color: "secondary",
        ellipsis: true
      }, r.project)
    }, {
      id: "status",
      title: "Status",
      width: "1.1fr",
      render: r => /*#__PURE__*/React.createElement("span", {
        style: {
          display: "inline-flex",
          alignItems: "center",
          gap: 6
        }
      }, /*#__PURE__*/React.createElement("span", {
        style: {
          width: 10,
          height: 10,
          borderRadius: "50%",
          background: r.tone
        }
      }), r.status)
    }, {
      id: "owner",
      title: "Owner",
      width: "80px",
      render: r => /*#__PURE__*/React.createElement(Tooltip, {
        content: r.owner
      }, /*#__PURE__*/React.createElement(Avatar, {
        text: r.owner,
        size: "small"
      }))
    }, {
      id: "due",
      title: "Due",
      width: "90px",
      align: "end",
      render: r => /*#__PURE__*/React.createElement(Text, {
        type: "text2",
        color: r.due === "Overdue" ? "negative" : "secondary"
      }, r.due)
    }, {
      id: "more",
      title: "",
      width: "40px",
      render: () => /*#__PURE__*/React.createElement(MenuButton, {
        size: "xs",
        items: [{
          label: "Edit",
          icon: "Edit"
        }, {
          label: "Duplicate",
          icon: "Duplicate"
        }, {
          divider: true
        }, {
          label: "Delete",
          icon: "Delete",
          destructive: true
        }]
      })
    }],
    rows: d.tasks
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Upcoming meetings",
    action: /*#__PURE__*/React.createElement(IconButton, {
      name: "Calendar",
      size: "xs",
      ariaLabel: "Open calendar"
    })
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-12)"
    }
  }, d.meetings.map(m => /*#__PURE__*/React.createElement("div", {
    key: m.title,
    style: {
      display: "flex",
      gap: "var(--space-8)",
      alignItems: "flex-start"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "Event",
    size: 18,
    color: "var(--icon-color)",
    style: {
      marginTop: 2
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    weight: "medium",
    ellipsis: true
  }, m.title), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary"
  }, m.when)), /*#__PURE__*/React.createElement(AvatarGroup, {
    people: m.people.map(id => d.team.find(t => t.id === id)),
    size: "xs",
    max: 3
  }))))), /*#__PURE__*/React.createElement(Card, {
    title: "Notice board",
    action: /*#__PURE__*/React.createElement(Counter, {
      count: 2,
      color: "light"
    })
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-12)"
    }
  }, d.notices.map(n => /*#__PURE__*/React.createElement("div", {
    key: n.title
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    weight: "medium"
  }, n.title), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary",
    element: "div"
  }, n.body))))), /*#__PURE__*/React.createElement(Card, {
    title: "Weekly availability",
    action: /*#__PURE__*/React.createElement(Button, {
      size: "xs",
      kind: "tertiary"
    }, "Update")
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 6
    }
  }, ["M", "T", "W", "T", "F"].map((day, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      flex: 1,
      textAlign: "center"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      height: 32,
      borderRadius: "var(--border-radius-small)",
      background: i === 3 ? "var(--negative-color-selected)" : "var(--positive-color-selected)"
    }
  }), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary"
  }, day))))))), /*#__PURE__*/React.createElement(Card, {
    title: "Projects I own",
    action: /*#__PURE__*/React.createElement(Button, {
      size: "xs",
      kind: "tertiary",
      onClick: () => onOpenProject(projects[0].id)
    }, "View all")
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(3, 1fr)",
      gap: "var(--space-16)"
    }
  }, projects.slice(0, 3).map(p => /*#__PURE__*/React.createElement("div", {
    key: p.id,
    onClick: () => onOpenProject(p.id),
    style: {
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "var(--space-12)",
      cursor: "pointer",
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    weight: "medium",
    style: {
      flex: 1
    },
    ellipsis: true
  }, p.client), /*#__PURE__*/React.createElement(Label, {
    text: p.tier,
    kind: "line",
    size: "small"
  })), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary",
    ellipsis: true
  }, p.name), /*#__PURE__*/React.createElement(ProgressBar, {
    value: p.progress,
    color: p.progress === 100 ? "positive" : "primary",
    size: "small",
    showLabel: true
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-4)"
    }
  }, p.tags.map(t => /*#__PURE__*/React.createElement(Chips, {
    key: t,
    label: t,
    color: "neutral",
    size: "small"
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      marginInlineStart: "auto"
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    text: p.owner,
    size: "small"
  }))))))));
}
Object.assign(window, {
  DashboardScreen,
  Card,
  StatTile
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/crm/Dashboard.jsx", error: String((e && e.message) || e) }); }

// ui_kits/crm/Login.jsx
try { (() => {
const {
  Button,
  TextField,
  Checkbox,
  Text,
  Heading,
  Link,
  AttentionBox
} = window.OZeeCRMDesignSystem_3d3bcd;
function LoginScreen({
  onLogin
}) {
  const [email, setEmail] = React.useState("sarah@ozeeweb.com.au");
  const [pw, setPw] = React.useState("••••••••••");
  const [remember, setRemember] = React.useState(true);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      height: "100%",
      display: "grid",
      gridTemplateColumns: "1fr 1fr",
      background: "var(--primary-background-color)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      justifyContent: "center",
      padding: "var(--space-64)",
      maxWidth: 520,
      margin: "0 auto",
      width: "100%"
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/ozee-logo.png",
    alt: "OZee Web & Digital",
    style: {
      width: 190,
      marginBottom: "var(--space-40)"
    }
  }), /*#__PURE__*/React.createElement(Heading, {
    type: "h2"
  }, "Sign in"), /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    color: "secondary",
    style: {
      marginTop: "var(--space-4)",
      marginBottom: "var(--space-24)"
    }
  }, "Use your OZee Web & Digital work account."), /*#__PURE__*/React.createElement("form", {
    onSubmit: e => {
      e.preventDefault();
      onLogin();
    },
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement(TextField, {
    label: "Email",
    value: email,
    onChange: e => setEmail(e.target.value),
    iconName: "Email",
    size: "large"
  }), /*#__PURE__*/React.createElement(TextField, {
    label: "Password",
    type: "password",
    value: pw,
    onChange: e => setPw(e.target.value),
    size: "large"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between"
    }
  }, /*#__PURE__*/React.createElement(Checkbox, {
    label: "Keep me signed in",
    checked: remember,
    onChange: e => setRemember(e.target.checked)
  }), /*#__PURE__*/React.createElement(Link, {
    text: "Forgot password?",
    href: "#"
  })), /*#__PURE__*/React.createElement(Button, {
    size: "large",
    fullWidth: true,
    type: "submit"
  }, "Sign in")), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: "var(--space-24)"
    }
  }, /*#__PURE__*/React.createElement(AttentionBox, {
    type: "dark",
    withIcon: true,
    iconName: "Security"
  }, "Client emails sent from the CRM go through manager approval before they leave."))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--ozee-blue)",
      display: "flex",
      flexDirection: "column",
      justifyContent: "center",
      padding: "var(--space-64)",
      color: "#fff"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-h1-bold)",
      letterSpacing: "var(--letter-spacing-h1-bold)",
      maxWidth: 420
    }
  }, "Every project, task and client email in one place."), /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-text1-normal)",
      marginTop: "var(--space-16)",
      maxWidth: 420,
      color: "rgba(255,255,255,0.85)"
    }
  }, "Your website and social media are like your home \u2014 first impressions matter."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: "var(--space-32)",
      marginTop: "var(--space-48)"
    }
  }, [["6", "active projects"], ["18", "open tasks"], ["3", "emails awaiting approval"]].map(([n, l]) => /*#__PURE__*/React.createElement("div", {
    key: l
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-h2-bold)"
    }
  }, n), /*#__PURE__*/React.createElement("div", {
    style: {
      font: "var(--font-text3-normal)",
      color: "rgba(255,255,255,0.75)"
    }
  }, l)))), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: "auto",
      display: "flex",
      gap: "var(--space-12)",
      alignItems: "center",
      paddingTop: "var(--space-48)"
    }
  }, ["facebook", "instagram", "linkedin", "x"].map(s => /*#__PURE__*/React.createElement("img", {
    key: s,
    src: `../../assets/social-${s}.svg`,
    alt: s,
    style: {
      width: 20,
      height: 20,
      filter: "brightness(0) invert(1)",
      opacity: 0.8
    }
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-text3-normal)",
      color: "rgba(255,255,255,0.75)",
      marginInlineStart: "var(--space-8)"
    }
  }, "OZeeWeb.com.au \xB7 Thornlie, WA"))));
}
Object.assign(window, {
  LoginScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/crm/Login.jsx", error: String((e && e.message) || e) }); }

// ui_kits/crm/ProjectDetail.jsx
try { (() => {
const {
  Button,
  IconButton,
  MenuButton,
  Icon,
  Text,
  Heading,
  Table,
  Label,
  Avatar,
  AvatarGroup,
  Chips,
  ProgressBar,
  Tabs,
  BreadcrumbsBar,
  Accordion,
  Checkbox,
  Tooltip,
  Divider,
  EditableHeading,
  AttentionBox
} = window.OZeeCRMDesignSystem_3d3bcd;
function ProjectDetailScreen({
  projectId,
  onBack,
  onOpenTask
}) {
  const d = window.CRM_DATA;
  const project = d.groups.flatMap(g => g.projects).find(p => p.id === projectId) || d.groups[0].projects[0];
  const [tab, setTab] = React.useState("tasks");
  const [name, setName] = React.useState(project.name);
  const [done, setDone] = React.useState([]);
  React.useEffect(() => setName(project.name), [project.id]);
  const tasks = d.tasks.filter(t => t.project === project.client).concat(d.tasks.slice(0, 3));
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-16) var(--space-24) 0",
      background: "var(--primary-background-color)",
      borderBottom: "1px solid var(--layout-border-color)"
    }
  }, /*#__PURE__*/React.createElement(BreadcrumbsBar, {
    items: [{
      text: "Projects",
      icon: "Board",
      onClick: onBack
    }, {
      text: project.client
    }]
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "flex-start",
      gap: "var(--space-16)",
      marginTop: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement(EditableHeading, {
    type: "h2",
    value: name,
    onChange: setName
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      marginTop: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Label, {
    text: project.status,
    color: project.status === "Done" ? "positive" : project.status === "Stuck" ? "negative" : "primary"
  }), /*#__PURE__*/React.createElement(Label, {
    text: project.tier,
    kind: "line"
  }), project.tags.map(t => /*#__PURE__*/React.createElement(Chips, {
    key: t,
    label: t,
    color: "neutral",
    size: "small"
  })), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary"
  }, "Due ", project.due))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(AvatarGroup, {
    people: d.team,
    size: "medium",
    max: 4
  }), /*#__PURE__*/React.createElement(Button, {
    size: "small",
    kind: "secondary",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Invite",
      size: 16
    })
  }, "Invite"), /*#__PURE__*/React.createElement(Button, {
    size: "small",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Add",
      size: 16
    })
  }, "New task"), /*#__PURE__*/React.createElement(MenuButton, {
    items: [{
      label: "Project settings",
      icon: "Settings"
    }, {
      label: "Export CSV",
      icon: "Download"
    }, {
      divider: true
    }, {
      label: "Archive project",
      icon: "Archive",
      destructive: true
    }]
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: "var(--space-12)"
    }
  }, /*#__PURE__*/React.createElement(Tabs, {
    value: tab,
    onChange: setTab,
    tabs: [{
      value: "tasks",
      label: "Tasks",
      count: tasks.length
    }, {
      value: "milestones",
      label: "Milestones",
      icon: "Recurring"
    }, {
      value: "emails",
      label: "Emails",
      icon: "Email",
      count: 2
    }, {
      value: "docs",
      label: "Documents",
      icon: "Doc"
    }, {
      value: "activity",
      label: "Activity",
      icon: "Activity"
    }]
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-16) var(--space-24) var(--space-32)",
      display: "grid",
      gridTemplateColumns: "1fr 300px",
      gap: "var(--space-16)",
      alignItems: "start"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      minWidth: 0,
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-16)"
    }
  }, project.status === "Stuck" ? /*#__PURE__*/React.createElement(AttentionBox, {
    type: "danger",
    title: "Blocked"
  }, "Waiting on the client's ABN documents before the payment gateway can be approved.") : null, tab === "tasks" ? /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      overflow: "hidden"
    }
  }, /*#__PURE__*/React.createElement(Table, {
    border: false,
    columns: [{
      id: "check",
      title: "",
      width: "32px",
      render: r => /*#__PURE__*/React.createElement(Checkbox, {
        checked: done.includes(r.id),
        onChange: () => setDone(p => p.includes(r.id) ? p.filter(x => x !== r.id) : [...p, r.id])
      })
    }, {
      id: "name",
      title: "Task",
      width: "2.4fr",
      render: r => /*#__PURE__*/React.createElement("span", {
        onClick: () => onOpenTask(r),
        style: {
          cursor: "pointer"
        }
      }, /*#__PURE__*/React.createElement(Text, {
        type: "text2",
        ellipsis: true
      }, r.name))
    }, {
      id: "type",
      title: "Type",
      width: "1fr",
      render: r => /*#__PURE__*/React.createElement(Chips, {
        label: r.type,
        color: "neutral",
        size: "small"
      })
    }, {
      id: "owner",
      title: "Owner",
      width: "70px",
      render: r => /*#__PURE__*/React.createElement(Tooltip, {
        content: r.owner
      }, /*#__PURE__*/React.createElement(Avatar, {
        text: r.owner,
        size: "small"
      }))
    }, {
      id: "status",
      title: "Status",
      width: "1.2fr",
      render: r => /*#__PURE__*/React.createElement("span", {
        style: {
          display: "inline-flex",
          alignItems: "center",
          gap: 6
        }
      }, /*#__PURE__*/React.createElement("span", {
        style: {
          width: 10,
          height: 10,
          borderRadius: "50%",
          background: r.tone
        }
      }), r.status)
    }, {
      id: "due",
      title: "Due",
      width: "90px",
      align: "end",
      render: r => /*#__PURE__*/React.createElement(Text, {
        type: "text2",
        color: r.due === "Overdue" ? "negative" : "secondary"
      }, r.due)
    }, {
      id: "more",
      title: "",
      width: "40px",
      render: () => /*#__PURE__*/React.createElement(MenuButton, {
        size: "xs",
        items: [{
          label: "Edit",
          icon: "Edit"
        }, {
          label: "Convert to subtask",
          icon: "Item"
        }, {
          divider: true
        }, {
          label: "Delete",
          icon: "Delete",
          destructive: true
        }]
      })
    }],
    rows: tasks
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Button, {
    size: "small",
    kind: "tertiary",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Add",
      size: 16
    })
  }, "Add task"))) : tab === "milestones" ? /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "0 var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement(Accordion, {
    allowMultiple: true,
    items: [{
      title: "Discovery & content",
      adornment: /*#__PURE__*/React.createElement(Label, {
        text: "Done",
        color: "positive",
        size: "small"
      }),
      content: /*#__PURE__*/React.createElement(Text, {
        type: "text2",
        color: "secondary"
      }, "Sitemap approved, copy deck signed off 4 Aug.")
    }, {
      title: "Design",
      adornment: /*#__PURE__*/React.createElement(Label, {
        text: "Done",
        color: "positive",
        size: "small"
      }),
      content: /*#__PURE__*/React.createElement(Text, {
        type: "text2",
        color: "secondary"
      }, "Home, services and booking screens approved.")
    }, {
      title: "Build",
      adornment: /*#__PURE__*/React.createElement(Label, {
        text: "In progress",
        size: "small"
      }),
      content: /*#__PURE__*/React.createElement(Text, {
        type: "text2",
        color: "secondary"
      }, "Booking form rebuild in progress \xB7 3 of 8 deliverables complete.")
    }, {
      title: "Launch",
      adornment: /*#__PURE__*/React.createElement(Label, {
        text: "Not started",
        kind: "line",
        size: "small"
      }),
      content: /*#__PURE__*/React.createElement(Text, {
        type: "text2",
        color: "secondary"
      }, "Scheduled for 29 Aug pending client sign-off.")
    }]
  })) : tab === "emails" ? /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "var(--space-16)",
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-12)"
    }
  }, d.emails.filter(e => e.client === project.client).concat(d.emails.slice(0, 1)).map((e, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: "flex",
      gap: "var(--space-12)",
      alignItems: "flex-start"
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    text: e.sender,
    size: "medium"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: "var(--space-8)",
      alignItems: "center"
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    weight: "medium"
  }, e.subject), /*#__PURE__*/React.createElement(Label, {
    text: e.status,
    color: e.status === "Approved" ? "positive" : "primary",
    size: "small"
  }), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary",
    style: {
      marginInlineStart: "auto"
    }
  }, e.when)), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary",
    element: "div",
    style: {
      marginTop: 2
    }
  }, "To ", e.to, " \xB7 from ", e.sender))))) : tab === "docs" ? /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "var(--space-16)",
      display: "grid",
      gridTemplateColumns: "repeat(3,1fr)",
      gap: "var(--space-12)"
    }
  }, [["Copy deck v3", "Doc"], ["Brand assets", "Folder"], ["Hosting details", "Security"], ["Launch checklist", "CheckList"], ["Invoice 1042", "File"], ["Analytics export", "Chart"]].map(([t, ic]) => /*#__PURE__*/React.createElement("div", {
    key: t,
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-small)",
      padding: "var(--space-12)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: ic,
    size: 20,
    color: "var(--icon-color)"
  }), /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    ellipsis: true
  }, t)))) : /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "var(--space-16)",
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-12)"
    }
  }, [["Sarah Bennett", "moved Rebuild the booking form to Working on it", "10 min ago"], ["Mia Lu", "submitted an email for approval", "1 hour ago"], ["Aisha Khan", "added a standup note", "Yesterday"], ["James Doyle", "completed the Design milestone", "2 days ago"]].map(([who, what, when], i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: "flex",
      gap: "var(--space-8)",
      alignItems: "flex-start"
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    text: who,
    size: "small"
  }), /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("strong", {
    style: {
      font: "var(--font-text2-medium)"
    }
  }, who), " ", what), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary"
  }, when))))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "var(--space-16)",
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-12)"
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    weight: "medium"
  }, "Progress"), /*#__PURE__*/React.createElement(ProgressBar, {
    value: project.progress,
    showLabel: true,
    color: project.progress === 100 ? "positive" : "primary"
  }), /*#__PURE__*/React.createElement(Divider, null), [["Client", project.client], ["Owner", project.owner], ["Tier", project.tier], ["Due date", project.due], ["Started", "28 Jul"]].map(([k, v]) => /*#__PURE__*/React.createElement("div", {
    key: k,
    style: {
      display: "flex",
      justifyContent: "space-between",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    color: "secondary"
  }, k), /*#__PURE__*/React.createElement(Text, {
    type: "text2"
  }, v)))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "var(--space-16)",
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    weight: "medium"
  }, "Quick actions"), [["Add standup note", "Note"], ["Log a meeting", "Event"], ["Give kudos", "Health"], ["Share a resource", "Share"]].map(([t, ic]) => /*#__PURE__*/React.createElement(Button, {
    key: t,
    kind: "tertiary",
    size: "small",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: ic,
      size: 16
    }),
    style: {
      justifyContent: "flex-start"
    }
  }, t))))));
}
Object.assign(window, {
  ProjectDetailScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/crm/ProjectDetail.jsx", error: String((e && e.message) || e) }); }

// ui_kits/crm/Projects.jsx
try { (() => {
const {
  Button,
  IconButton,
  ButtonGroup,
  MenuButton,
  Icon,
  Text,
  Table,
  Label,
  Avatar,
  AvatarGroup,
  Chips,
  ProgressBar,
  Dropdown,
  Search,
  Checkbox,
  Tooltip,
  EmptyState,
  Divider
} = window.OZeeCRMDesignSystem_3d3bcd;
const STATUSES = [{
  value: "todo",
  label: "To Do",
  color: "var(--color-explosive)"
}, {
  value: "working",
  label: "Working on it",
  color: "var(--color-working-orange)"
}, {
  value: "done",
  label: "Done",
  color: "var(--color-done-green)"
}, {
  value: "stuck",
  label: "Stuck",
  color: "var(--color-stuck-red)"
}];
function StatusCell({
  status,
  tone
}) {
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      height: 28,
      minWidth: 108,
      padding: "0 var(--space-8)",
      borderRadius: "var(--border-radius-small)",
      background: tone,
      color: "#fff",
      font: "var(--font-text2-normal)"
    }
  }, status);
}
function GroupHeader({
  group,
  count,
  open,
  onToggle
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      padding: "var(--space-12) var(--space-8) var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(IconButton, {
    name: "DropdownChevronRight",
    size: "xs",
    ariaLabel: "Toggle group",
    onClick: onToggle,
    style: {
      transform: open ? "rotate(90deg)" : "none",
      color: group.color
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-text1-medium)",
      color: group.color
    }
  }, group.name), /*#__PURE__*/React.createElement(Text, {
    type: "text3",
    color: "secondary"
  }, count, " projects"), /*#__PURE__*/React.createElement("span", {
    style: {
      marginInlineStart: "auto"
    }
  }, /*#__PURE__*/React.createElement(MenuButton, {
    size: "xs",
    items: [{
      label: "Rename group",
      icon: "Edit"
    }, {
      label: "Change colour",
      icon: "Wand"
    }, {
      divider: true
    }, {
      label: "Delete group",
      icon: "Delete",
      destructive: true
    }]
  })));
}
function ProjectsScreen({
  onOpenProject
}) {
  const d = window.CRM_DATA;
  const [view, setView] = React.useState("table");
  const [q, setQ] = React.useState("");
  const [owner, setOwner] = React.useState(null);
  const [closed, setClosed] = React.useState([]);
  const toggleGroup = name => setClosed(p => p.includes(name) ? p.filter(x => x !== name) : [...p, name]);
  const match = p => (!q || p.name.toLowerCase().includes(q.toLowerCase()) || p.client.toLowerCase().includes(q.toLowerCase())) && (!owner || p.owner === owner);
  const columns = [{
    id: "check",
    title: "",
    width: "32px",
    render: () => /*#__PURE__*/React.createElement(Checkbox, null)
  }, {
    id: "name",
    title: "Project",
    width: "2.4fr",
    render: r => /*#__PURE__*/React.createElement("span", {
      onClick: () => onOpenProject(r.id),
      style: {
        cursor: "pointer",
        display: "flex",
        flexDirection: "column",
        minWidth: 0
      }
    }, /*#__PURE__*/React.createElement(Text, {
      type: "text2",
      weight: "medium",
      ellipsis: true
    }, r.client), /*#__PURE__*/React.createElement(Text, {
      type: "text3",
      color: "secondary",
      ellipsis: true
    }, r.name))
  }, {
    id: "owner",
    title: "Owner",
    width: "0.7fr",
    render: r => /*#__PURE__*/React.createElement(Tooltip, {
      content: r.owner
    }, /*#__PURE__*/React.createElement(Avatar, {
      text: r.owner,
      size: "small"
    }))
  }, {
    id: "team",
    title: "Team",
    width: "1fr",
    render: () => /*#__PURE__*/React.createElement(AvatarGroup, {
      people: d.team,
      size: "small",
      max: 3
    })
  }, {
    id: "status",
    title: "Status",
    width: "1.3fr",
    render: r => /*#__PURE__*/React.createElement(StatusCell, {
      status: r.status,
      tone: r.tone
    })
  }, {
    id: "progress",
    title: "Progress",
    width: "1.2fr",
    render: r => /*#__PURE__*/React.createElement(ProgressBar, {
      value: r.progress,
      size: "small",
      color: r.progress === 100 ? "positive" : "primary",
      showLabel: true
    })
  }, {
    id: "tags",
    title: "Tags",
    width: "1.2fr",
    render: r => /*#__PURE__*/React.createElement("span", {
      style: {
        display: "flex",
        gap: 4,
        overflow: "hidden"
      }
    }, r.tags.map(t => /*#__PURE__*/React.createElement(Chips, {
      key: t,
      label: t,
      color: "neutral",
      size: "small"
    })))
  }, {
    id: "due",
    title: "Due date",
    width: "0.9fr",
    align: "end",
    render: r => /*#__PURE__*/React.createElement(Text, {
      type: "text2",
      color: r.due === "Overdue" ? "negative" : "secondary"
    }, r.due)
  }, {
    id: "more",
    title: "",
    width: "40px",
    render: () => /*#__PURE__*/React.createElement(MenuButton, {
      size: "xs",
      items: [{
        label: "Open project",
        icon: "Board"
      }, {
        label: "Duplicate",
        icon: "Duplicate"
      }, {
        label: "Archive",
        icon: "Archive"
      }, {
        divider: true
      }, {
        label: "Delete",
        icon: "Delete",
        destructive: true
      }]
    })
  }];
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)",
      padding: "var(--space-12) var(--space-24)",
      background: "var(--primary-background-color)",
      borderBottom: "1px solid var(--layout-border-color)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 260
    }
  }, /*#__PURE__*/React.createElement(Search, {
    value: q,
    onChange: e => setQ(e.target.value),
    onClear: () => setQ(""),
    size: "small",
    placeholder: "Search this board"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      width: 180
    }
  }, /*#__PURE__*/React.createElement(Dropdown, {
    size: "small",
    options: d.team.map(t => ({
      value: t.name,
      label: t.name
    })),
    value: owner,
    onChange: setOwner,
    placeholder: "Owner",
    clearable: true
  })), /*#__PURE__*/React.createElement(Button, {
    size: "small",
    kind: "tertiary",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Filter",
      size: 16
    })
  }, "Filter"), /*#__PURE__*/React.createElement(Button, {
    size: "small",
    kind: "tertiary",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Sort",
      size: 16
    })
  }, "Sort"), /*#__PURE__*/React.createElement(Button, {
    size: "small",
    kind: "tertiary",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Hide",
      size: 16
    })
  }, "Hide"), /*#__PURE__*/React.createElement("span", {
    style: {
      marginInlineStart: "auto",
      display: "flex",
      gap: "var(--space-8)",
      alignItems: "center"
    }
  }, /*#__PURE__*/React.createElement(ButtonGroup, {
    options: [{
      value: "table",
      icon: "Table",
      text: "Table"
    }, {
      value: "board",
      icon: "Board",
      text: "Board"
    }, {
      value: "gantt",
      icon: "Gantt",
      text: "Timeline"
    }],
    value: view,
    onChange: setView
  }))), view === "table" ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-8) var(--space-24) var(--space-32)"
    }
  }, d.groups.map(g => {
    const rows = g.projects.filter(match);
    const open = !closed.includes(g.name);
    if (!rows.length) return null;
    return /*#__PURE__*/React.createElement("div", {
      key: g.name
    }, /*#__PURE__*/React.createElement(GroupHeader, {
      group: g,
      count: rows.length,
      open: open,
      onToggle: () => toggleGroup(g.name)
    }), open ? /*#__PURE__*/React.createElement("div", {
      style: {
        borderInlineStart: `3px solid ${g.color}`,
        borderRadius: "var(--border-radius-small)",
        overflow: "hidden"
      }
    }, /*#__PURE__*/React.createElement(Table, {
      columns: columns,
      rows: rows,
      onRowClick: null
    })) : null);
  }), d.groups.every(g => g.projects.filter(match).length === 0) ? /*#__PURE__*/React.createElement(EmptyState, {
    iconName: "Search",
    title: "No projects match",
    description: "Try clearing the owner filter or the search term.",
    action: /*#__PURE__*/React.createElement(Button, {
      size: "small",
      kind: "secondary",
      onClick: () => {
        setQ("");
        setOwner(null);
      }
    }, "Clear filters")
  }) : null) : view === "board" ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-16) var(--space-24) var(--space-32)",
      display: "grid",
      gridTemplateColumns: "repeat(4, minmax(0,1fr))",
      gap: "var(--space-16)",
      alignItems: "start"
    }
  }, STATUSES.map(s => {
    const rows = d.groups.flatMap(g => g.projects).filter(p => p.status === s.label && match(p));
    return /*#__PURE__*/React.createElement("div", {
      key: s.value,
      style: {
        background: "var(--allgrey-background-color)",
        borderRadius: "var(--border-radius-medium)",
        padding: "var(--space-8)"
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        alignItems: "center",
        gap: "var(--space-8)",
        padding: "var(--space-4) var(--space-8) var(--space-8)"
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 8,
        height: 8,
        borderRadius: "50%",
        background: s.color
      }
    }), /*#__PURE__*/React.createElement(Text, {
      type: "text2",
      weight: "medium",
      style: {
        flex: 1
      }
    }, s.label), /*#__PURE__*/React.createElement(Text, {
      type: "text3",
      color: "secondary"
    }, rows.length)), /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        flexDirection: "column",
        gap: "var(--space-8)"
      }
    }, rows.map(p => /*#__PURE__*/React.createElement("div", {
      key: p.id,
      onClick: () => onOpenProject(p.id),
      style: {
        background: "var(--primary-background-color)",
        border: "1px solid var(--layout-border-color)",
        borderRadius: "var(--border-radius-medium)",
        padding: "var(--space-12)",
        cursor: "pointer",
        display: "flex",
        flexDirection: "column",
        gap: "var(--space-8)",
        boxShadow: "var(--box-shadow-xs)"
      }
    }, /*#__PURE__*/React.createElement(Text, {
      type: "text2",
      weight: "medium",
      ellipsis: true
    }, p.client), /*#__PURE__*/React.createElement(Text, {
      type: "text3",
      color: "secondary",
      ellipsis: true
    }, p.name), /*#__PURE__*/React.createElement(ProgressBar, {
      value: p.progress,
      size: "small",
      color: p.progress === 100 ? "positive" : "primary"
    }), /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        alignItems: "center",
        gap: 4
      }
    }, p.tags.slice(0, 2).map(t => /*#__PURE__*/React.createElement(Chips, {
      key: t,
      label: t,
      color: "neutral",
      size: "small"
    })), /*#__PURE__*/React.createElement("span", {
      style: {
        marginInlineStart: "auto",
        display: "inline-flex",
        alignItems: "center",
        gap: 6
      }
    }, /*#__PURE__*/React.createElement(Text, {
      type: "text3",
      color: p.due === "Overdue" ? "negative" : "secondary"
    }, p.due), /*#__PURE__*/React.createElement(Avatar, {
      text: p.owner,
      size: "small"
    }))))), /*#__PURE__*/React.createElement(Button, {
      size: "small",
      kind: "tertiary",
      leftIcon: /*#__PURE__*/React.createElement(Icon, {
        name: "Add",
        size: 16
      }),
      style: {
        justifyContent: "flex-start"
      }
    }, "Add project")));
  })) : /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-16) var(--space-24) var(--space-32)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: "var(--primary-background-color)",
      border: "1px solid var(--layout-border-color)",
      borderRadius: "var(--border-radius-medium)",
      padding: "var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "220px 1fr",
      gap: "var(--space-8)",
      alignItems: "center"
    }
  }, /*#__PURE__*/React.createElement("span", null), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(6,1fr)",
      font: "var(--font-text3-normal)",
      color: "var(--secondary-text-color)"
    }
  }, ["Aug 4", "Aug 11", "Aug 18", "Aug 25", "Sep 1", "Sep 8"].map(w => /*#__PURE__*/React.createElement("span", {
    key: w
  }, w))), d.groups.flatMap(g => g.projects).filter(match).map((p, i) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: p.id
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    ellipsis: true
  }, p.client), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      height: 28,
      background: "var(--allgrey-background-color)",
      borderRadius: "var(--border-radius-small)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      top: 4,
      bottom: 4,
      left: `${8 + i * 9}%`,
      width: `${28 + i % 3 * 12}%`,
      background: p.tone,
      borderRadius: "var(--border-radius-small)",
      display: "flex",
      alignItems: "center",
      padding: "0 8px",
      color: "#fff",
      font: "var(--font-text3-medium)",
      overflow: "hidden"
    }
  }, p.name))))))));
}
Object.assign(window, {
  ProjectsScreen,
  StatusCell,
  STATUSES
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/crm/Projects.jsx", error: String((e && e.message) || e) }); }

// ui_kits/crm/Shell.jsx
try { (() => {
const {
  Icon,
  IconButton,
  Avatar,
  Counter,
  Badge,
  Text,
  Search,
  Button,
  ExpandCollapse,
  Tooltip
} = window.OZeeCRMDesignSystem_3d3bcd;
function TopBar({
  onSearch,
  query,
  onOpenNotifications,
  onNewTask
}) {
  return /*#__PURE__*/React.createElement("header", {
    style: {
      height: "var(--shell-topbar-height)",
      flex: "none",
      display: "flex",
      alignItems: "center",
      gap: "var(--space-16)",
      padding: "0 var(--space-16)",
      background: "var(--primary-background-color)",
      borderBottom: "1px solid var(--layout-border-color)"
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/ozee-logo-sm.png",
    alt: "OZee Web & Digital",
    style: {
      height: 26
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-text2-medium)",
      color: "var(--secondary-text-color)",
      paddingInlineStart: "var(--space-4)"
    }
  }, "CRM"), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      maxWidth: 420
    }
  }, /*#__PURE__*/React.createElement(Search, {
    value: query,
    onChange: e => onSearch(e.target.value),
    onClear: () => onSearch(""),
    size: "small",
    placeholder: "Search projects, tasks, clients"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      marginInlineStart: "auto",
      display: "flex",
      alignItems: "center",
      gap: "var(--space-4)"
    }
  }, /*#__PURE__*/React.createElement(Button, {
    size: "small",
    leftIcon: /*#__PURE__*/React.createElement(Icon, {
      name: "Add",
      size: 16
    }),
    onClick: onNewTask
  }, "New task"), /*#__PURE__*/React.createElement(Tooltip, {
    content: "Notifications"
  }, /*#__PURE__*/React.createElement(Badge, {
    count: 4
  }, /*#__PURE__*/React.createElement(IconButton, {
    name: "Notifications",
    size: "small",
    ariaLabel: "Notifications",
    onClick: onOpenNotifications
  }))), /*#__PURE__*/React.createElement(Tooltip, {
    content: "Invite a teammate"
  }, /*#__PURE__*/React.createElement(IconButton, {
    name: "Invite",
    size: "small",
    ariaLabel: "Invite"
  })), /*#__PURE__*/React.createElement(Tooltip, {
    content: "Help"
  }, /*#__PURE__*/React.createElement(IconButton, {
    name: "Help",
    size: "small",
    ariaLabel: "Help"
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      width: 1,
      height: 24,
      background: "var(--ui-border-color)",
      margin: "0 var(--space-4)"
    }
  }), /*#__PURE__*/React.createElement(Avatar, {
    text: window.CRM_DATA.user.name,
    size: "medium",
    bottomRightBadge: "var(--color-done-green)"
  })));
}
function NavRail({
  active,
  onNavigate
}) {
  return /*#__PURE__*/React.createElement("nav", {
    style: {
      width: 64,
      flex: "none",
      background: "var(--primary-background-color)",
      borderInlineEnd: "1px solid var(--layout-border-color)",
      display: "flex",
      flexDirection: "column",
      alignItems: "center",
      paddingTop: "var(--space-8)",
      gap: 2
    }
  }, window.CRM_DATA.nav.map(n => {
    const on = n.id === active;
    return /*#__PURE__*/React.createElement(Tooltip, {
      key: n.id,
      content: n.label,
      position: "right"
    }, /*#__PURE__*/React.createElement("button", {
      type: "button",
      onClick: () => onNavigate(n.id),
      style: {
        width: 48,
        height: 44,
        border: "none",
        borderRadius: "var(--border-radius-small)",
        background: on ? "var(--primary-selected-color)" : "transparent",
        color: on ? "var(--primary-color)" : "var(--icon-color)",
        cursor: "pointer",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        gap: 2,
        position: "relative"
      },
      onMouseEnter: e => {
        if (!on) e.currentTarget.style.background = "var(--primary-background-hover-color)";
      },
      onMouseLeave: e => {
        if (!on) e.currentTarget.style.background = "transparent";
      }
    }, /*#__PURE__*/React.createElement(Icon, {
      name: n.icon,
      size: 20,
      color: "currentColor"
    }), n.count ? /*#__PURE__*/React.createElement("span", {
      style: {
        position: "absolute",
        top: 4,
        insetInlineEnd: 6
      }
    }, /*#__PURE__*/React.createElement(Counter, {
      count: n.count,
      color: "negative",
      size: "xs"
    })) : null));
  }));
}
function ProjectSidebar({
  collapsed,
  onToggle,
  activeProjectId,
  onSelectProject,
  filter,
  onFilter
}) {
  const groups = window.CRM_DATA.groups;
  const match = p => !filter || p.name.toLowerCase().includes(filter.toLowerCase()) || p.client.toLowerCase().includes(filter.toLowerCase());
  if (collapsed) {
    return /*#__PURE__*/React.createElement("aside", {
      style: {
        width: "var(--shell-sidebar-collapsed-width)",
        flex: "none",
        background: "var(--primary-background-color)",
        borderInlineEnd: "1px solid var(--layout-border-color)",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        paddingTop: "var(--space-12)",
        gap: "var(--space-8)"
      }
    }, /*#__PURE__*/React.createElement(IconButton, {
      name: "NavigationDoubleChevronLeft",
      size: "small",
      ariaLabel: "Expand sidebar",
      onClick: onToggle,
      style: {
        transform: "rotate(180deg)"
      }
    }), groups.flatMap(g => g.projects).map(p => /*#__PURE__*/React.createElement(Tooltip, {
      key: p.id,
      content: p.name,
      position: "right"
    }, /*#__PURE__*/React.createElement("button", {
      type: "button",
      onClick: () => onSelectProject(p.id),
      style: {
        width: 36,
        height: 36,
        borderRadius: "var(--border-radius-small)",
        border: "none",
        background: p.id === activeProjectId ? "var(--primary-color)" : "var(--allgrey-background-color)",
        color: p.id === activeProjectId ? "#fff" : "var(--secondary-text-color)",
        font: "var(--font-text2-medium)",
        cursor: "pointer"
      }
    }, p.client.charAt(0)))));
  }
  return /*#__PURE__*/React.createElement("aside", {
    style: {
      width: "var(--shell-sidebar-width)",
      flex: "none",
      background: "var(--primary-background-color)",
      borderInlineEnd: "1px solid var(--layout-border-color)",
      display: "flex",
      flexDirection: "column",
      overflow: "hidden"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-4)",
      padding: "var(--space-12) var(--space-8) var(--space-8) var(--space-16)"
    }
  }, /*#__PURE__*/React.createElement(Text, {
    type: "text2",
    weight: "medium",
    style: {
      flex: 1
    }
  }, "Projects"), /*#__PURE__*/React.createElement(IconButton, {
    name: "Add",
    size: "xs",
    ariaLabel: "New project"
  }), /*#__PURE__*/React.createElement(IconButton, {
    name: "NavigationDoubleChevronLeft",
    size: "xs",
    ariaLabel: "Collapse sidebar",
    onClick: onToggle
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "0 var(--space-12) var(--space-8)"
    }
  }, /*#__PURE__*/React.createElement(Search, {
    value: filter,
    onChange: e => onFilter(e.target.value),
    onClear: () => onFilter(""),
    size: "small",
    placeholder: "Search all projects"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      overflow: "auto",
      padding: "0 var(--space-8) var(--space-16)"
    }
  }, groups.map(g => {
    const items = g.projects.filter(match);
    if (!items.length) return null;
    return /*#__PURE__*/React.createElement(ExpandCollapse, {
      key: g.name,
      defaultOpen: true,
      title: /*#__PURE__*/React.createElement("span", {
        style: {
          display: "inline-flex",
          alignItems: "center",
          gap: "var(--space-8)"
        }
      }, /*#__PURE__*/React.createElement("span", {
        style: {
          width: 8,
          height: 8,
          borderRadius: 2,
          background: g.color
        }
      }), g.name)
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        flexDirection: "column",
        gap: 2,
        paddingBottom: "var(--space-8)"
      }
    }, items.map(p => {
      const on = p.id === activeProjectId;
      return /*#__PURE__*/React.createElement("button", {
        key: p.id,
        type: "button",
        onClick: () => onSelectProject(p.id),
        style: {
          textAlign: "start",
          border: "none",
          background: on ? "var(--primary-selected-color)" : "transparent",
          color: on ? "var(--primary-color)" : "var(--primary-text-color)",
          font: on ? "var(--font-text2-medium)" : "var(--font-text2-normal)",
          padding: "6px var(--space-8)",
          borderRadius: "var(--border-radius-small)",
          cursor: "pointer",
          overflow: "hidden",
          textOverflow: "ellipsis",
          whiteSpace: "nowrap"
        },
        onMouseEnter: e => {
          if (!on) e.currentTarget.style.background = "var(--primary-background-hover-color)";
        },
        onMouseLeave: e => {
          if (!on) e.currentTarget.style.background = "transparent";
        }
      }, p.client);
    })));
  })));
}
function PageHeader({
  title,
  subtitle,
  breadcrumbs,
  actions,
  tabs
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-20) var(--space-24) 0",
      background: "var(--primary-background-color)",
      borderBottom: "1px solid var(--layout-border-color)"
    }
  }, breadcrumbs, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "flex-start",
      gap: "var(--space-16)",
      marginTop: breadcrumbs ? "var(--space-8)" : 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      font: "var(--font-h2-medium)",
      letterSpacing: "var(--letter-spacing-h2-bold)",
      color: "var(--primary-text-color)"
    }
  }, title), subtitle ? /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 2,
      font: "var(--font-text2-normal)",
      color: "var(--secondary-text-color)"
    }
  }, subtitle) : null), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "var(--space-8)"
    }
  }, actions)), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: "var(--space-12)"
    }
  }, tabs));
}
Object.assign(window, {
  TopBar,
  NavRail,
  ProjectSidebar,
  PageHeader
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/crm/Shell.jsx", error: String((e && e.message) || e) }); }

// ui_kits/crm/data.js
try { (() => {
window.CRM_DATA = {
  user: {
    name: "Sarah Bennett",
    role: "Project Manager",
    initials: "SB"
  },
  nav: [{
    id: "dashboard",
    icon: "Home",
    label: "My work"
  }, {
    id: "projects",
    icon: "Board",
    label: "Projects"
  }, {
    id: "tasks",
    icon: "CheckList",
    label: "Tasks"
  }, {
    id: "approvals",
    icon: "Email",
    label: "Approvals",
    count: 3
  }, {
    id: "reports",
    icon: "Chart",
    label: "Reports"
  }, {
    id: "automations",
    icon: "Bolt",
    label: "Automations"
  }, {
    id: "team",
    icon: "Team",
    label: "Team"
  }, {
    id: "admin",
    icon: "Settings",
    label: "Admin"
  }],
  team: [{
    id: "sb",
    name: "Sarah Bennett",
    text: "Sarah Bennett"
  }, {
    id: "jd",
    name: "James Doyle",
    text: "James Doyle"
  }, {
    id: "ak",
    name: "Aisha Khan",
    text: "Aisha Khan"
  }, {
    id: "tr",
    name: "Tom Reid",
    text: "Tom Reid"
  }, {
    id: "ml",
    name: "Mia Lu",
    text: "Mia Lu"
  }],
  groups: [{
    name: "Website projects",
    color: "var(--color-bright-blue)",
    projects: [{
      id: 1,
      name: "Northshore Dental — site refresh",
      client: "Northshore Dental",
      status: "Working on it",
      tone: "var(--color-working-orange)",
      due: "22 Aug",
      owner: "Sarah Bennett",
      progress: 58,
      tags: ["Website", "Copy"],
      tier: "Tier 2"
    }, {
      id: 2,
      name: "Perth Plumbing — new brochure site",
      client: "Perth Plumbing",
      status: "Done",
      tone: "var(--color-done-green)",
      due: "12 Aug",
      owner: "James Doyle",
      progress: 100,
      tags: ["Website"],
      tier: "Tier 1"
    }, {
      id: 3,
      name: "Coast Cafe — online ordering",
      client: "Coast Cafe",
      status: "Stuck",
      tone: "var(--color-stuck-red)",
      due: "Overdue",
      owner: "Aisha Khan",
      progress: 34,
      tags: ["Website", "Integration"],
      tier: "Tier 3"
    }]
  }, {
    name: "SEO retainers",
    color: "var(--color-done-green)",
    projects: [{
      id: 4,
      name: "Bayside Legal — SEO retainer",
      client: "Bayside Legal",
      status: "Working on it",
      tone: "var(--color-working-orange)",
      due: "31 Aug",
      owner: "Mia Lu",
      progress: 70,
      tags: ["SEO"],
      tier: "Tier 2"
    }, {
      id: 5,
      name: "Hills Physio — local SEO",
      client: "Hills Physio",
      status: "To Do",
      tone: "var(--color-explosive)",
      due: "5 Sep",
      owner: "Tom Reid",
      progress: 8,
      tags: ["SEO", "GBP"],
      tier: "Tier 1"
    }]
  }, {
    name: "Paid ads",
    color: "var(--color-purple)",
    projects: [{
      id: 6,
      name: "Perth Plumbing — Google Ads",
      client: "Perth Plumbing",
      status: "Working on it",
      tone: "var(--color-working-orange)",
      due: "26 Aug",
      owner: "James Doyle",
      progress: 45,
      tags: ["Ads"],
      tier: "Tier 2"
    }]
  }],
  tasks: [{
    id: 11,
    name: "Rebuild the booking form",
    project: "Northshore Dental",
    status: "Working on it",
    tone: "var(--color-working-orange)",
    due: "Today",
    owner: "Sarah Bennett",
    type: "Development"
  }, {
    id: 12,
    name: "Write service page copy (3 pages)",
    project: "Northshore Dental",
    status: "To Do",
    tone: "var(--color-explosive)",
    due: "Today",
    owner: "Mia Lu",
    type: "Content"
  }, {
    id: 13,
    name: "Fix mobile nav overlap",
    project: "Coast Cafe",
    status: "Stuck",
    tone: "var(--color-stuck-red)",
    due: "Overdue",
    owner: "Aisha Khan",
    type: "Bug"
  }, {
    id: 14,
    name: "August keyword report",
    project: "Bayside Legal",
    status: "Working on it",
    tone: "var(--color-working-orange)",
    due: "Tomorrow",
    owner: "Mia Lu",
    type: "Reporting"
  }, {
    id: 15,
    name: "Approve new ad creatives",
    project: "Perth Plumbing",
    status: "To Do",
    tone: "var(--color-explosive)",
    due: "22 Aug",
    owner: "James Doyle",
    type: "Review"
  }, {
    id: 16,
    name: "Hand over launch checklist",
    project: "Perth Plumbing",
    status: "Done",
    tone: "var(--color-done-green)",
    due: "12 Aug",
    owner: "Sarah Bennett",
    type: "Admin"
  }],
  emails: [{
    id: 21,
    to: "hello@northshoredental.com.au",
    client: "Northshore Dental",
    subject: "Booking form — staging link for review",
    sender: "Mia Lu",
    when: "9:12 am",
    status: "Pending",
    body: "Hi Dr. Nguyen,\n\nThe rebuilt booking form is on staging and ready for your review. Two things worth checking:\n\n1. The appointment type list now matches your reception sheet.\n2. Confirmation emails go to both the patient and reception.\n\nHappy to walk through it on a quick call this week.\n\nKind regards,\nMia — OZee Web & Digital"
  }, {
    id: 22,
    to: "accounts@baysidelegal.com.au",
    client: "Bayside Legal",
    subject: "August SEO summary + next month's focus",
    sender: "Mia Lu",
    when: "8:40 am",
    status: "Pending",
    body: "Hi Grant,\n\nAugust in short: organic sessions up 14%, three new first-page keywords, and the family-law page is now ranking on page two.\n\nNext month we'll focus on the wills and estates cluster.\n\nKind regards,\nMia — OZee Web & Digital"
  }, {
    id: 23,
    to: "kate@coastcafe.com.au",
    client: "Coast Cafe",
    subject: "Online ordering — blocked on payment gateway",
    sender: "Aisha Khan",
    when: "Yesterday",
    status: "Pending",
    body: "Hi Kate,\n\nWe're blocked on the payment gateway approval — the provider needs your ABN documents before we can go live.\n\nOnce those are through we need about three days to finish testing.\n\nKind regards,\nAisha — OZee Web & Digital"
  }, {
    id: 24,
    to: "james@perthplumbing.com.au",
    client: "Perth Plumbing",
    subject: "Site is live 🎉",
    sender: "James Doyle",
    when: "12 Aug",
    status: "Approved",
    body: "Hi James,\n\nYour new site is live. Everything's been checked on mobile and desktop, and the enquiry form is landing in your inbox.\n\nKind regards,\nJames — OZee Web & Digital"
  }],
  notices: [{
    title: "Team lunch Friday",
    body: "Thornlie office, 12:30. RSVP in the thread."
  }, {
    title: "New client onboarding checklist",
    body: "Use the updated template for every new retainer."
  }],
  meetings: [{
    title: "Northshore Dental — staging walkthrough",
    when: "Today · 2:00 pm",
    people: ["sb", "ml"]
  }, {
    title: "Weekly team standup",
    when: "Tomorrow · 9:00 am",
    people: ["sb", "jd", "ak", "tr", "ml"]
  }]
};
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/crm/data.js", error: String((e && e.message) || e) }); }

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Icon = __ds_scope.Icon;

__ds_ns.IconButton = __ds_scope.IconButton;

__ds_ns.ButtonGroup = __ds_scope.ButtonGroup;

__ds_ns.SplitButton = __ds_scope.SplitButton;

__ds_ns.Link = __ds_scope.Link;

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.AvatarGroup = __ds_scope.AvatarGroup;

__ds_ns.Chips = __ds_scope.Chips;

__ds_ns.Label = __ds_scope.Label;

__ds_ns.Counter = __ds_scope.Counter;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Table = __ds_scope.Table;

__ds_ns.List = __ds_scope.List;

__ds_ns.ListTitle = __ds_scope.ListTitle;

__ds_ns.ListItem = __ds_scope.ListItem;

__ds_ns.Toast = __ds_scope.Toast;

__ds_ns.AlertBanner = __ds_scope.AlertBanner;

__ds_ns.AttentionBox = __ds_scope.AttentionBox;

__ds_ns.Tipseen = __ds_scope.Tipseen;

__ds_ns.Tooltip = __ds_scope.Tooltip;

__ds_ns.Info = __ds_scope.Info;

__ds_ns.Loader = __ds_scope.Loader;

__ds_ns.Skeleton = __ds_scope.Skeleton;

__ds_ns.EmptyState = __ds_scope.EmptyState;

__ds_ns.Checkbox = __ds_scope.Checkbox;

__ds_ns.RadioButton = __ds_scope.RadioButton;

__ds_ns.Toggle = __ds_scope.Toggle;

__ds_ns.DatePicker = __ds_scope.DatePicker;

__ds_ns.DialogContentContainer = __ds_scope.DialogContentContainer;

__ds_ns.Dropdown = __ds_scope.Dropdown;

__ds_ns.Combobox = __ds_scope.Combobox;

__ds_ns.Slider = __ds_scope.Slider;

__ds_ns.ProgressBar = __ds_scope.ProgressBar;

__ds_ns.ColorPicker = __ds_scope.ColorPicker;

__ds_ns.TextField = __ds_scope.TextField;

__ds_ns.TextArea = __ds_scope.TextArea;

__ds_ns.Search = __ds_scope.Search;

__ds_ns.NumberField = __ds_scope.NumberField;

__ds_ns.Menu = __ds_scope.Menu;

__ds_ns.MenuButton = __ds_scope.MenuButton;

__ds_ns.Steps = __ds_scope.Steps;

__ds_ns.MultiStepIndicator = __ds_scope.MultiStepIndicator;

__ds_ns.Tabs = __ds_scope.Tabs;

__ds_ns.BreadcrumbsBar = __ds_scope.BreadcrumbsBar;

__ds_ns.Divider = __ds_scope.Divider;

__ds_ns.Accordion = __ds_scope.Accordion;

__ds_ns.ExpandCollapse = __ds_scope.ExpandCollapse;

__ds_ns.Modal = __ds_scope.Modal;

__ds_ns.Heading = __ds_scope.Heading;

__ds_ns.Text = __ds_scope.Text;

__ds_ns.TextWithHighlight = __ds_scope.TextWithHighlight;

__ds_ns.FormattedNumber = __ds_scope.FormattedNumber;

__ds_ns.EditableText = __ds_scope.EditableText;

__ds_ns.EditableHeading = __ds_scope.EditableHeading;

})();
