/**
 * Does every identifier a file uses exist somewhere in that file, or come in through an
 * import?
 *
 * The third leg of the static check, alongside inbox-check.mjs (named imports resolve) and
 * jsx-scope-check.mjs (JSX tags are in scope). This one catches the plain-JavaScript
 * version of the same mistake: calling `plural(...)` or `presetRange(...)` in a file that
 * never imported or defined it.
 *
 * Vite cannot run here (node_modules holds macOS-native rollup and esbuild binaries and
 * the registry is blocked from this machine), so these three scripts are the only compile
 * signal available. They are not a substitute for `npm run build` — run that before
 * trusting a deploy.
 *
 * Deliberately over-permissive about SCOPE: every declaration anywhere in the file counts
 * as declared everywhere in it. Proper scoping would need a real resolver, and the failure
 * it would additionally catch — using a name that exists but not here — is far rarer than
 * the one this does catch, a name that exists nowhere.
 */
import { parse } from '@babel/parser';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ROOTS = ['resources/js/ReactPages', 'resources/js/ReactComponents'];

const GLOBALS = new Set([
  'window', 'document', 'console', 'navigator', 'localStorage', 'sessionStorage', 'location',
  'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'requestAnimationFrame',
  'fetch', 'FormData', 'URL', 'URLSearchParams', 'Blob', 'File', 'FileReader', 'Image',
  'Promise', 'Math', 'JSON', 'Date', 'Number', 'String', 'Boolean', 'Array', 'Object',
  'Set', 'Map', 'WeakMap', 'Symbol', 'Error', 'TypeError', 'RegExp', 'Intl', 'Infinity',
  'NaN', 'undefined', 'globalThis', 'process', 'React', 'AbortController', 'CustomEvent',
  'Event', 'IntersectionObserver', 'ResizeObserver', 'MutationObserver', 'DataTransfer',
  'HTMLElement', 'Node', 'structuredClone', 'queueMicrotask', 'atob', 'btoa', 'crypto',
  'performance', 'alert', 'confirm', 'prompt', 'history', 'screen', 'matchMedia',
  'encodeURIComponent', 'decodeURIComponent', 'encodeURI', 'decodeURI', 'parseInt',
  'parseFloat', 'isNaN', 'isFinite', 'Function', 'Proxy', 'Reflect', 'BigInt',
]);

const walk = (dir) =>
  readdirSync(dir).flatMap((name) => {
    const full = join(dir, name);
    return statSync(full).isDirectory() ? walk(full) : [full];
  });

const files = ROOTS.flatMap(walk).filter((f) => /\.(js|jsx)$/.test(f));
const problems = [];

for (const file of files) {
  const ast = parse(readFileSync(file, 'utf8'), {
    sourceType: 'module',
    plugins: ['jsx', 'classProperties', 'optionalChaining', 'nullishCoalescingOperator'],
  });

  const declared = new Set();
  const used = new Map();

  const addPattern = (node) => {
    if (!node) return;
    switch (node.type) {
      case 'Identifier':
        declared.add(node.name);
        break;
      case 'ObjectPattern':
        node.properties.forEach((p) =>
          addPattern(p.type === 'RestElement' ? p.argument : p.value)
        );
        break;
      case 'ArrayPattern':
        node.elements.forEach(addPattern);
        break;
      case 'AssignmentPattern':
        addPattern(node.left);
        break;
      case 'RestElement':
        addPattern(node.argument);
        break;
      default:
        break;
    }
  };

  const declare = (node) => {
    if (node.type === 'ImportDeclaration') node.specifiers.forEach((s) => declared.add(s.local.name));
    if (node.type === 'VariableDeclarator') addPattern(node.id);
    if ((node.type === 'FunctionDeclaration' || node.type === 'ClassDeclaration') && node.id) {
      declared.add(node.id.name);
    }
    if (node.type === 'FunctionDeclaration' || node.type === 'FunctionExpression' ||
        node.type === 'ArrowFunctionExpression' || node.type === 'ObjectMethod' ||
        node.type === 'ClassMethod') {
      (node.params || []).forEach(addPattern);
    }
    if (node.type === 'CatchClause') addPattern(node.param);
    if (node.type === 'ForOfStatement' || node.type === 'ForInStatement') {
      if (node.left?.type === 'Identifier') declared.add(node.left.name);
    }
  };

  const visit = (node, parent, key) => {
    if (!node || typeof node.type !== 'string') return;

    declare(node);

    if (node.type === 'Identifier') {
      // `a.b` and `a?.b` are different node types, and forgetting the optional one is
      // what turned every `thread?.ai` in this codebase into a false positive.
      const isMemberProperty =
        (parent?.type === 'MemberExpression' || parent?.type === 'OptionalMemberExpression') &&
        key === 'property' &&
        !parent.computed;
      const isObjectKey =
        (parent?.type === 'ObjectProperty' || parent?.type === 'ObjectMethod') &&
        key === 'key' && !parent.computed;
      const isDeclarationName =
        (parent?.type === 'VariableDeclarator' && key === 'id') ||
        ((parent?.type === 'FunctionDeclaration' || parent?.type === 'ClassDeclaration') && key === 'id');
      const isLabel = parent?.type === 'LabeledStatement' || parent?.type === 'BreakStatement';
      const isJsxAttrName = parent?.type === 'JSXAttribute';
      // `export { Icon } from './Icon'` re-exports a name this file never binds.
      const isExportSpecifier =
        parent?.type === 'ExportSpecifier' || parent?.type === 'ImportSpecifier';

      if (
        !isMemberProperty && !isObjectKey && !isDeclarationName && !isLabel &&
        !isJsxAttrName && !isExportSpecifier
      ) {
        if (!used.has(node.name)) used.set(node.name, node.loc.start.line);
      }
    }

    for (const childKey of Object.keys(node)) {
      if (childKey === 'loc' || childKey === 'leadingComments' || childKey === 'trailingComments') continue;
      const value = node[childKey];
      if (Array.isArray(value)) value.forEach((c) => visit(c, node, childKey));
      else if (value && typeof value.type === 'string') visit(value, node, childKey);
    }
  };

  visit(ast.program, null, null);

  for (const [name, line] of used) {
    if (!declared.has(name) && !GLOBALS.has(name)) {
      problems.push(`${file}:${line}  '${name}' is used but never declared or imported`);
    }
  }
}

if (problems.length) {
  problems.forEach((p) => console.error(p));
  console.error(`\n${problems.length} problem(s)`);
  process.exit(1);
}

console.log(`checked ${files.length} files — no undeclared identifiers`);
