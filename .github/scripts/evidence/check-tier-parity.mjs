// Checks that .github/labeler.yml and .github/scripts/evidence/classify.php
// give the same path tier, with the glob library of actions/labeler.
//
// Two corpora:
//   tier-fixtures.txt  one change per line: the expected tier, then the paths
//                      it changes, separated by tabs; both classifiers must
//                      give the expected tier;
//   git ls-files       every tracked file, alone: both classifiers must agree.
//
// A change carrying both labels fails too: T2 wins over T0.
//
// Usage: node check-tier-parity.mjs <node_modules directory with minimatch and js-yaml>

import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
import {readFileSync, writeFileSync, mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';

const modules = process.argv[2];
if (!modules) {
  console.error('Usage: node check-tier-parity.mjs <node_modules directory>');
  process.exit(2);
}
const require = createRequire(join(modules, 'noop.js'));
const {Minimatch} = require('minimatch');
const yaml = require('js-yaml');

const here = new URL('.', import.meta.url).pathname;
const root = join(here, '../../..');

// The subset of the labeler configuration this repository uses, read the way
// actions/labeler v7 reads it (src/api/get-label-configs.ts, src/labeler.ts,
// src/changedFiles.ts). Anything else is an error rather than a guess.
const matchers = {
  'any-glob-to-any-file': (files, globs) => globs.some(g => files.some(f => g.match(f))),
  'any-glob-to-all-files': (files, globs) => globs.some(g => files.every(f => g.match(f))),
  'all-globs-to-any-file': (files, globs) => files.some(f => globs.every(g => g.match(f))),
  'all-globs-to-all-files': (files, globs) => files.every(f => globs.every(g => g.match(f))),
};

function changedFilesConditions(entry) {
  const keys = Object.keys(entry);
  if (keys.length !== 1 || keys[0] !== 'changed-files') {
    throw new Error(`unsupported labeler condition: ${JSON.stringify(entry)}`);
  }
  return entry['changed-files'].flatMap(block => Object.entries(block).map(([kind, globs]) => {
    if (!matchers[kind]) {
      throw new Error(`unsupported changed-files option: ${kind}`);
    }
    const compiled = [].concat(globs).map(g => new Minimatch(g, {dot: true}));
    return files => matchers[kind](files, compiled);
  }));
}

function compileLabel(entries) {
  // Each entry is an "all" block, an "any" block, or bare conditions that join
  // the "any" block. Every block must match.
  const blocks = [];
  let any = null;
  for (const entry of entries) {
    for (const [key, value] of Object.entries(entry)) {
      if (key === 'all' || key === 'any') {
        blocks.push({key, conditions: value.flatMap(changedFilesConditions)});
      } else if (key === 'changed-files') {
        if (!any) {
          any = {key: 'any', conditions: []};
          blocks.push(any);
        }
        any.conditions.push(...changedFilesConditions({[key]: value}));
      } else {
        throw new Error(`unsupported labeler key: ${key}`);
      }
    }
  }
  return files => blocks.every(({key, conditions}) =>
    key === 'all' ? conditions.every(c => c(files)) : conditions.some(c => c(files)));
}

const config = yaml.load(readFileSync(join(root, '.github/labeler.yml'), 'utf8'));
const t0 = compileLabel(config['tier:T0']);
const t2 = compileLabel(config['tier:T2']);

function labelerTier(files) {
  const zero = t0(files);
  const two = t2(files);
  if (zero && two) {
    return 'T0+T2';
  }
  return two ? 'T2' : zero ? 'T0' : 'T1';
}

const changes = [];
for (const line of readFileSync(join(here, 'tier-fixtures.txt'), 'utf8').split('\n')) {
  if (line.trim() === '' || line.startsWith('#')) {
    continue;
  }
  const [expected, ...files] = line.split('\t');
  changes.push({expected, files});
}
const fixtureCount = changes.length;
for (const file of execFileSync('git', ['ls-files'], {cwd: root, encoding: 'utf8'}).split('\n').filter(Boolean)) {
  changes.push({expected: null, files: [file]});
}

const input = join(mkdtempSync(join(tmpdir(), 'tier-parity-')), 'changes.txt');
writeFileSync(input, changes.map(c => c.files.join('\t')).join('\n') + '\n');
const classified = execFileSync('php', [join(here, 'classify.php'), '--paths', input], {encoding: 'utf8'})
  .split('\n').filter(Boolean).map(line => line.split('\t').pop());

let failures = 0;
changes.forEach((change, index) => {
  const labeler = labelerTier(change.files);
  const classify = classified[index];
  const expected = change.expected ?? classify;
  if (labeler !== classify || classify !== expected) {
    failures++;
    console.log(`::error title=Tier rules disagree::${change.files.join(', ')}: labeler.yml ${labeler}, classify.php ${classify}` +
      (change.expected ? `, expected ${change.expected}` : ''));
  }
});

console.log(`${fixtureCount} fixture change(s) and ${changes.length - fixtureCount} tracked file(s) checked, ${failures} disagreement(s).`);
process.exit(failures ? 1 : 0);
