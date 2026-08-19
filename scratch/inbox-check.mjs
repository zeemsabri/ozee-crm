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

const ROOTS = [
  'resources/js/ReactPages/Inbox',
  'resources/js/ReactComponents/inbox',
  'resources/js/ReactComponents/app',
  'resources/js/ReactComponents/ds',
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
console.log('OK — parses clean, every relative import resolves, every named import exists');
