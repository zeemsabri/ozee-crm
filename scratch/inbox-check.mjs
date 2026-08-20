/**
 * Static check for the redesigned inbox's frontend.
 *
 * Vite cannot run on this machine (node_modules holds macOS-native rollup binaries), so
 * this parses every file with @babel/parser — which is pure JS and platform-independent —
 * and then walks the relative import graph verifying that every named import actually
 * exists as an export in the file it points at. That catches the two failure modes a
 * syntax check alone misses: a typo'd component name, and an import of something that was
 * never exported.
 */
import { parse } from '@babel/parser';
import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { dirname, resolve, extname, join } from 'node:path';

// All React code, not just the inbox — the shared ds/ and app/ modules are imported by
// the portal too, so a change there has to be checked against every consumer.
const ROOTS = [
  'resources/js/ReactPages',
  'resources/js/ReactComponents',
];

const walk = (dir) =>
  readdirSync(dir).flatMap((name) => {
    const full = join(dir, name);
    return statSync(full).isDirectory() ? walk(full) : [full];
  });

const files = ROOTS.flatMap(walk).filter((f) => /\.(js|jsx)$/.test(f));

const parsed = new Map();
const errors = [];

for (const file of files) {
  try {
    parsed.set(
      resolve(file),
      parse(readFileSync(file, 'utf8'), {
        sourceType: 'module',
        plugins: ['jsx', 'classProperties', 'optionalChaining', 'nullishCoalescingOperator'],
      })
    );
  } catch (e) {
    errors.push(`SYNTAX  ${file}:${e.loc?.line ?? '?'}  ${e.message}`);
  }
}

/** Named exports declared by a file, following `export … from` re-exports one hop. */
function exportsOf(absPath, seen = new Set()) {
  if (seen.has(absPath)) return new Set();
  seen.add(absPath);

  const ast = parsed.get(absPath);
  if (!ast) return null;

  const names = new Set();

  for (const node of ast.program.body) {
    if (node.type === 'ExportNamedDeclaration') {
      if (node.declaration) {
        if (node.declaration.declarations) {
          node.declaration.declarations.forEach((d) => names.add(d.id.name));
        } else if (node.declaration.id) {
          names.add(node.declaration.id.name);
        }
      }
      for (const spec of node.specifiers || []) {
        names.add(spec.exported.name);
      }
      if (node.source) {
        const target = resolveImport(absPath, node.source.value);
        if (target) (exportsOf(target, seen) || []).forEach?.((n) => names.add(n));
      }
    }
    if (node.type === 'ExportDefaultDeclaration') names.add('default');
  }

  return names;
}

function resolveImport(fromFile, spec) {
  if (!spec.startsWith('.')) return null;
  const base = resolve(dirname(fromFile), spec);
  const candidates = extname(base)
    ? [base]
    : [`${base}.jsx`, `${base}.js`, join(base, 'index.jsx'), join(base, 'index.js')];
  return candidates.find((c) => existsSync(c)) || null;
}

for (const [absPath, ast] of parsed) {
  const rel = absPath.replace(process.cwd() + '/', '');

  for (const node of ast.program.body) {
    if (node.type !== 'ImportDeclaration') continue;
    const spec = node.source.value;

    if (!spec.startsWith('.')) continue;

    const target = resolveImport(absPath, spec);
    if (!target) {
      errors.push(`MISSING ${rel}  ->  ${spec}  (no such file)`);
      continue;
    }
    if (/\.css$/.test(target)) continue;

    const available = exportsOf(target);
    if (!available) continue; // outside the checked roots

    for (const s of node.specifiers) {
      const wanted =
        s.type === 'ImportDefaultSpecifier'
          ? 'default'
          : s.type === 'ImportSpecifier'
            ? s.imported.name
            : null;
      if (wanted && !available.has(wanted)) {
        errors.push(`EXPORT  ${rel}  imports { ${wanted} } from '${spec}' — not exported there`);
      }
    }
  }
}

/**
 * Bare (package) imports must actually be installed.
 *
 * Vite cannot run here to tell us, and a missing dependency is the one class of failure a
 * parse-and-resolve check would otherwise sail straight past.
 */
for (const [absPath, ast] of parsed) {
  const rel = absPath.replace(process.cwd() + '/', '');

  for (const node of ast.program.body) {
    if (node.type !== 'ImportDeclaration') continue;
    const spec = node.source.value;
    if (spec.startsWith('.') || spec.startsWith('/')) continue;

    // Strip any subpath: '@inertiajs/react' from '@inertiajs/react/whatever'.
    const parts = spec.split('/');
    const pkg = spec.startsWith('@') ? parts.slice(0, 2).join('/') : parts[0];

    if (!existsSync(resolve('node_modules', pkg))) {
      errors.push(`NOPKG   ${rel}  imports '${spec}' but node_modules/${pkg} is not installed`);
    }
  }
}

/**
 * Hooks must not sit after an early return.
 *
 * A cheap structural stand-in for react-hooks/rules-of-hooks, which is not runnable here.
 * Catches the specific mistake this codebase is prone to: adding a `if (!thread) return`
 * guard above existing hooks while editing a component.
 *
 * Only returns in the component's OWN body count — a `return` inside a callback passed to
 * useEffect is not an early return from the component, so the walk stops at every nested
 * function boundary.
 */
const FN_TYPES = new Set([
  'FunctionDeclaration',
  'FunctionExpression',
  'ArrowFunctionExpression',
  'ObjectMethod',
  'ClassMethod',
]);

for (const [absPath, ast] of parsed) {
  const rel = absPath.replace(process.cwd() + '/', '');

  /** Walk one component body, never descending into a nested function. */
  const scan = (node, state) => {
    if (!node || typeof node.type !== 'string') return;

    if (node.type === 'ReturnStatement') {
      state.returned = true;
      return;
    }

    if (
      node.type === 'CallExpression' &&
      node.callee?.type === 'Identifier' &&
      /^use[A-Z]/.test(node.callee.name) &&
      state.returned
    ) {
      errors.push(`HOOK    ${rel}  ${node.callee.name}() is called after an early return in ${state.fn}`);
    }

    for (const key of Object.keys(node)) {
      if (key === 'loc' || key === 'leadingComments' || key === 'trailingComments') continue;
      const child = node[key];
      const kids = Array.isArray(child) ? child : [child];

      for (const kid of kids) {
        if (!kid || typeof kid.type !== 'string') continue;
        // A hook cannot legally be inside a nested function anyway, and a return there
        // belongs to that function, not the component.
        if (FN_TYPES.has(kid.type)) continue;
        scan(kid, state);
      }
    }
  };

  const visitFns = (node) => {
    if (!node || typeof node.type !== 'string') return;

    const named =
      node.type === 'FunctionDeclaration' || node.type === 'FunctionExpression'
        ? node.id?.name
        : null;

    if (named && /^[A-Z]/.test(named)) {
      const state = { returned: false, fn: named };
      (node.body?.body || []).forEach((stmt) => scan(stmt, state));
    }

    for (const key of Object.keys(node)) {
      if (key === 'loc') continue;
      const child = node[key];
      if (Array.isArray(child)) child.forEach(visitFns);
      else if (child && typeof child.type === 'string') visitFns(child);
    }
  };

  visitFns(ast.program);
}

/** Unused imports: noise, and usually a sign something was left half-refactored. */
for (const [absPath, ast] of parsed) {
  const rel = absPath.replace(process.cwd() + '/', '');
  const source = readFileSync(absPath, 'utf8');
  for (const node of ast.program.body) {
    if (node.type !== 'ImportDeclaration') continue;
    for (const s of node.specifiers) {
      const name = s.local.name;
      const uses = source.split(new RegExp(`\\b${name}\\b`, 'g')).length - 1;
      if (uses <= 1) errors.push(`UNUSED  ${rel}  imports ${name} but never uses it`);
    }
  }
}

console.log(`checked ${parsed.size} files`);
if (errors.length) {
  console.log(`\n${errors.length} problem(s):`);
  errors.forEach((e) => console.log('  ' + e));
  process.exit(1);
}
console.log('OK — parses clean, imports resolve (relative + packages), named imports exist, no hooks after an early return');
