/**
 * Every capitalised JSX tag in a file must actually be in scope there.
 *
 * inbox-check.mjs proves that what a file IMPORTS exists. This proves the other
 * direction: that what a file RENDERS was imported. The two together catch the pair of
 * mistakes a syntax parse cannot — importing something that is not exported, and using
 * something that was never imported. Neither shows up until the page is opened, and on a
 * layout that only appears under 860px that can be a while.
 */
import { parse } from '@babel/parser';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ROOTS = ['resources/js/ReactPages', 'resources/js/ReactComponents'];

const walk = (dir) =>
  readdirSync(dir).flatMap((name) => {
    const full = join(dir, name);
    return statSync(full).isDirectory() ? walk(full) : [full];
  });

const files = ROOTS.flatMap(walk).filter((f) => /\.jsx$/.test(f));
const problems = [];

for (const file of files) {
  const ast = parse(readFileSync(file, 'utf8'), {
    sourceType: 'module',
    plugins: ['jsx', 'classProperties', 'optionalChaining', 'nullishCoalescingOperator'],
  });

  const declared = new Set(['React', 'Fragment']);
  const used = new Map();

  const addPattern = (node) => {
    if (!node) return;
    if (node.type === 'Identifier') declared.add(node.name);
    else if (node.type === 'ObjectPattern') node.properties.forEach((p) => addPattern(p.value || p.argument));
    else if (node.type === 'ArrayPattern') node.elements.forEach(addPattern);
    else if (node.type === 'AssignmentPattern') addPattern(node.left);
    else if (node.type === 'RestElement') addPattern(node.argument);
  };

  const visit = (node, parent) => {
    if (!node || typeof node.type !== 'string') return;

    if (node.type === 'ImportDeclaration') node.specifiers.forEach((s) => declared.add(s.local.name));
    if (node.type === 'VariableDeclarator') addPattern(node.id);
    if (node.type === 'FunctionDeclaration' && node.id) declared.add(node.id.name);
    if (node.type === 'ClassDeclaration' && node.id) declared.add(node.id.name);
    if (node.type === 'JSXOpeningElement') {
      let name = node.name;
      while (name.type === 'JSXMemberExpression') name = name.object;
      if (name.type === 'JSXIdentifier' && /^[A-Z]/.test(name.name)) {
        used.set(name.name, node.loc.start.line);
      }
    }

    for (const key of Object.keys(node)) {
      if (key === 'loc' || key === 'leadingComments' || key === 'trailingComments') continue;
      const value = node[key];
      if (Array.isArray(value)) value.forEach((c) => visit(c, node));
      else if (value && typeof value.type === 'string') visit(value, node);
    }
  };

  visit(ast.program, null);

  for (const [name, line] of used) {
    if (!declared.has(name)) problems.push(`${file}:${line}  <${name}> is not in scope`);
  }
}

if (problems.length) {
  problems.forEach((p) => console.error(p));
  console.error(`\n${problems.length} problem(s)`);
  process.exit(1);
}

console.log(`checked ${files.length} .jsx files — every JSX component tag is in scope`);
