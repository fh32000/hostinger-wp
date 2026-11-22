#!/usr/bin/env node
/**
 * Automated Safari / WebKit CSS & HTML Audit Tool
 * Scans Vue, CSS, SCSS files or git diff for known Safari / iOS WebKit anti-patterns.
 */
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const RULES = [
  {
    id: 'vh-without-dvh',
    name: '100vh used without dvh fallback',
    severity: 'warning',
    check: (content, line) => {
      return (line.includes('100vh') || line.includes('h-screen')) &&
        !line.includes('100dvh') && !line.includes('h-dvh') && !content.includes('100dvh');
    },
    message: 'iOS Safari dynamic toolbar causes content overflow with 100vh. Use 100dvh or add dvh fallback.',
    fix: 'Add height: 100dvh or use h-dvh / min-h-dvh'
  },
  {
    id: 'backdrop-filter-prefix',
    name: 'backdrop-filter missing -webkit- prefix',
    severity: 'error',
    check: (content, line, lines, index) => {
      if (!line.includes('backdrop-filter:')) return false;
      if (line.includes('-webkit-backdrop-filter')) return false;
      const prevLine = lines[index - 1] || '';
      const nextLine = lines[index + 1] || '';
      return !prevLine.includes('-webkit-backdrop-filter') && !nextLine.includes('-webkit-backdrop-filter');
    },
    message: 'Safari requires -webkit-backdrop-filter for glassmorphism / blur effects.',
    fix: 'Add -webkit-backdrop-filter: ... alongside backdrop-filter: ...'
  },
  {
    id: 'video-webkit-playsinline',
    name: 'playsinline missing webkit-playsinline',
    severity: 'error',
    check: (content, line) => {
      if (!line.includes('playsinline')) return false;
      return !line.includes('webkit-playsinline') && !content.includes('webkit-playsinline');
    },
    message: 'iOS Safari may force full-screen player unless webkit-playsinline is explicitly present.',
    fix: 'Add webkit-playsinline attribute to <video>'
  },
  {
    id: 'inset-m-auto-centering',
    name: 'absolute inset-0 m-auto on flex container',
    severity: 'warning',
    check: (content, line) => {
      return line.includes('absolute') && line.includes('inset-0') && line.includes('m-auto');
    },
    message: 'WebKit flex layout calculations can misalign or shift inset-0 m-auto during transforms.',
    fix: 'Prefer top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 for bulletproof centering'
  }
];

function scanContent(filePath, content) {
  const issues = [];
  const lines = content.split('\n');

  lines.forEach((line, idx) => {
    for (const rule of RULES) {
      if (rule.check(content, line, lines, idx)) {
        issues.push({
          file: filePath,
          line: idx + 1,
          code: line.trim(),
          rule: rule.id,
          severity: rule.severity,
          message: rule.message,
          fix: rule.fix
        });
      }
    }
  });

  return issues;
}

function scanFile(filePath) {
  if (!fs.existsSync(filePath)) return [];
  const stat = fs.statSync(filePath);
  if (stat.isDirectory()) {
    let results = [];
    const entries = fs.readdirSync(filePath);
    for (const entry of entries) {
      if (['node_modules', '.git', '.nuxt', '.output', 'dist'].includes(entry)) continue;
      results = results.concat(scanFile(path.join(filePath, entry)));
    }
    return results;
  }

  const ext = path.extname(filePath);
  if (!['.vue', '.css', '.scss', '.sass'].includes(ext)) return [];

  const content = fs.readFileSync(filePath, 'utf8');
  return scanContent(filePath, content);
}

function main() {
  const target = process.argv[2] || 'safia-frontend/components/courses/SubscriptionVideoPlayer.vue';
  console.log(`🔍 Scanning for Safari (WebKit) compatibility issues in: ${target}\n`);

  const issues = scanFile(path.resolve(process.cwd(), target));

  if (issues.length === 0) {
    console.log('✅ No Safari compatibility issues found! All checks passed.');
    process.exit(0);
  }

  console.log(`⚠️ Found ${issues.length} potential Safari compatibility issue(s):\n`);
  for (const issue of issues) {
    const icon = issue.severity === 'error' ? '❌' : '⚠️';
    console.log(`${icon} [${issue.severity.toUpperCase()}] ${issue.file}:${issue.line}`);
    console.log(`   Snippet : ${issue.code}`);
    console.log(`   Issue   : ${issue.message}`);
    console.log(`   Fix     : ${issue.fix}\n`);
  }

  const errorCount = issues.filter(i => i.severity === 'error').length;
  if (errorCount > 0) {
    console.log(`Audit failed with ${errorCount} error(s).`);
    process.exit(1);
  } else {
    console.log(`Audit passed with warnings.`);
    process.exit(0);
  }
}

main();
