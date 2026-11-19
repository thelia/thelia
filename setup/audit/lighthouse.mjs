#!/usr/bin/env node
// Storefront performance audit, around Lighthouse CI (see setup/audit/lighthouserc.default.yaml).
//
//   node setup/audit/lighthouse.mjs config --origin https://shop.test --preset desktop [--output file]
//       Writes the effective Lighthouse CI configuration from the template.
//   node setup/audit/lighthouse.mjs assertions --preset desktop [--budget file] [--output file]
//       Writes the Lighthouse CI assertions of a preset from setup/audit/lighthouse-budget.json.
//   node setup/audit/lighthouse.mjs summary --preset desktop --results .lighthouseci [--markdown file] [--json file]
//       [--date d] [--version v] [--commit c] [--theme t] [--machine m]: what the figures are of, for the record
//       Checks that every page of the template was measured as itself, and prints the medians.
//
// Run `lhci collect` and `lhci assert` from the directory that holds the generated files: Lighthouse CI
// reads and writes `.lighthouseci` in the current directory, and loads the sign-in script relative to it.
// No dependency beyond Node: the YAML template is only read through its placeholders and its URL list.

import { mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const AUDIT_DIR = dirname(fileURLToPath(import.meta.url));
const TEMPLATE = resolve(AUDIT_DIR, 'lighthouserc.default.yaml');
const BUDGET = resolve(AUDIT_DIR, 'lighthouse-budget.json');
const SIGN_IN_SCRIPT = resolve(AUDIT_DIR, 'sign-in.cjs');
const GREENIT = resolve(AUDIT_DIR, 'greenit.default.yaml');
const PRESETS = ['desktop', 'mobile'];

// What is compared and published for each page. `better` says which way a change is a regression.
export const METRICS = [
  { key: 'performance', label: 'Performance', unit: 'score', better: 'higher', assertion: 'categories:performance' },
  { key: 'accessibility', label: 'Accessibility', unit: 'score', better: 'higher', assertion: 'categories:accessibility' },
  { key: 'best-practices', label: 'Best practices', unit: 'score', better: 'higher', assertion: 'categories:best-practices' },
  { key: 'seo', label: 'SEO', unit: 'score', better: 'higher', assertion: 'categories:seo' },
  { key: 'first-contentful-paint', label: 'FCP', unit: 'ms', better: 'lower', assertion: 'first-contentful-paint' },
  { key: 'largest-contentful-paint', label: 'LCP', unit: 'ms', better: 'lower', assertion: 'largest-contentful-paint' },
  { key: 'cumulative-layout-shift', label: 'CLS', unit: 'unitless', better: 'lower', assertion: 'cumulative-layout-shift' },
  { key: 'total-blocking-time', label: 'TBT', unit: 'ms', better: 'lower', assertion: 'total-blocking-time' },
  { key: 'total-byte-weight', label: 'Transferred', unit: 'bytes', better: 'lower', assertion: 'total-byte-weight' },
  { key: 'requests', label: 'Requests', unit: 'count', better: 'lower', assertion: 'resource-summary:total:count' },
];

function fail(message) {
  console.error(`lighthouse.mjs: ${message}`);
  process.exit(1);
}

function parseArguments(argv) {
  const [command, ...rest] = argv;
  const options = {};
  for (let index = 0; index < rest.length; index++) {
    const name = rest[index];
    if (!name.startsWith('--')) fail(`unexpected argument "${name}"`);
    const value = rest[index + 1];
    if (value === undefined || value.startsWith('--')) fail(`${name} needs a value`);
    options[name.slice(2)] = value;
    index++;
  }

  return { command, options };
}

function presetOf(options) {
  const preset = options.preset;
  if (!PRESETS.includes(preset)) fail(`--preset must be one of ${PRESETS.join(', ')}`);

  return preset;
}

function write(file, content) {
  mkdirSync(dirname(resolve(file)), { recursive: true });
  writeFileSync(file, content);
  console.log(`lighthouse.mjs: wrote ${file}`);
}

/** Paths of the pages of the template, in its order ("/" for the home page). */
export function templatePaths(template = readFileSync(TEMPLATE, 'utf8')) {
  const paths = [...template.matchAll(/^\s*-\s*"https?:\/\/__DOMAIN__([^"]*)"/gm)].map((match) => match[1] || '/');
  if (paths.length === 0) fail(`no URL found in ${TEMPLATE}`);

  return paths;
}

/** The eco-design audit measures the same pages: its list follows this one. */
function checkGreenitList(paths) {
  const greenit = [...readFileSync(GREENIT, 'utf8').matchAll(/^\s*url:\s*"https?:\/\/__DOMAIN__([^"]*)"/gm)].map((match) => match[1] || '/');
  const differences = [
    ...paths.filter((path) => !greenit.includes(path)).map((path) => `${path} is missing`),
    ...greenit.filter((path) => !paths.includes(path)).map((path) => `${path} is not in the Lighthouse list`),
  ];
  if (differences.length) fail(`${GREENIT} no longer lists the pages of ${TEMPLATE}: ${differences.join(', ')}`);
}

function templateRuns(template) {
  const match = template.match(/^\s*numberOfRuns:\s*(\d+)/m);

  return match ? Number(match[1]) : 3;
}

/** The effective configuration: the template with its placeholders replaced. */
export function effectiveConfig(template, { origin, preset, outputDirectory }) {
  const url = new URL(origin);
  if (url.pathname !== '/' || url.search || url.hash) fail('--origin is a scheme and a host, without a path');
  const base = url.origin;

  let config = template.replaceAll(/https?:\/\/__DOMAIN__/g, base);
  // Lighthouse CI requires the script relative to the directory it runs from.
  config = config.replaceAll('__SIGN_IN_SCRIPT__', relative(outputDirectory, SIGN_IN_SCRIPT) || 'sign-in.cjs');
  // Mobile is what Lighthouse measures without a preset: it has no "mobile" preset to name.
  config = 'mobile' === preset
    ? config.replace(/^\s*preset:\s*"__PRESET__"\s*\n/m, '')
    : config.replaceAll('__PRESET__', preset);
  const leftover = config.split('\n').find((line) => !line.trimStart().startsWith('#') && /__[A-Z_]+__/.test(line));
  if (leftover) fail(`a placeholder of the template was left unreplaced: ${leftover.trim()}`);

  return config;
}

function readBudget(file = BUDGET) {
  return { ...JSON.parse(readFileSync(file, 'utf8')), file };
}

/** Threshold of a metric: the reference value moved by the tolerance, in the direction of a regression. */
export function threshold(metric, reference, tolerance) {
  const { relative: share = 0, absolute = 0 } = tolerance;
  const margin = Math.abs(reference) * share + absolute;

  return 'higher' === metric.better ? reference - margin : reference + margin;
}

/** Lighthouse CI assertions of a preset: one entry per page, matched on the exact URL. */
export function assertionsFor(budget, preset) {
  const reference = budget.reference?.[preset];
  if (!reference?.pages) {
    return null;
  }

  const paths = templatePaths();
  const missing = paths.filter((path) => !reference.pages[path]);
  if (missing.length) fail(`no ${preset} reference for ${missing.join(', ')} in ${budget.file}`);

  const assertMatrix = paths.map((path) => {
    const assertions = {};
    for (const metric of METRICS) {
      const value = reference.pages[path][metric.key];
      if (typeof value !== 'number') fail(`no ${preset} reference of ${metric.key} for ${path} in ${budget.file}`);
      // A page can carry its own tolerance for a metric, with the reason written next to it.
      const tolerance = budget.pageTolerance?.[preset]?.[path]?.[metric.key] ?? budget.tolerance?.[preset]?.[metric.key];
      if (!tolerance) fail(`no ${preset} tolerance of ${metric.key} in ${budget.file}`);
      const limit = threshold(metric, value, tolerance);
      assertions[metric.assertion] = 'score' === metric.unit
        ? ['error', { minScore: Math.max(0, Math.min(1, limit / 100)), aggregationMethod: 'median' }]
        : ['error', { maxNumericValue: limit, aggregationMethod: 'median' }];
    }
    // The final URL of a run, so a page that redirects matches nothing: `summary` refuses it.
    const escaped = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return { matchingUrlPattern: `^https?://[^/]+${'/' === path ? '/?' : escaped}$`, assertions };
  });

  return { ci: { assert: { assertMatrix } } };
}

function valueOf(lhr, metric) {
  if ('score' === metric.unit) {
    const score = lhr.categories[metric.key]?.score;

    return typeof score === 'number' ? Math.round(score * 100) : null;
  }
  if ('requests' === metric.key) {
    const total = lhr.audits['resource-summary']?.details?.items?.find((item) => 'total' === item.resourceType);

    return total ? total.requestCount : null;
  }
  const value = lhr.audits[metric.key]?.numericValue;

  return typeof value === 'number' ? value : null;
}

function median(values) {
  const sorted = values.filter((value) => typeof value === 'number').sort((a, b) => a - b);
  if (!sorted.length) return null;
  const middle = Math.floor(sorted.length / 2);

  return sorted.length % 2 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
}

function pathOf(url) {
  const { pathname, search } = new URL(url);

  return pathname + search;
}

/** Medians of each page, refusing a page that was not measured as itself or not measured enough. */
export function summarize(lhrs, paths, runs) {
  const problems = [];
  const pages = {};
  for (const path of paths) {
    const measured = lhrs.filter((lhr) => pathOf(lhr.requestedUrl) === path);
    if (measured.length < runs) problems.push(`${path}: ${measured.length} run(s) instead of ${runs}`);
    for (const lhr of measured) {
      if (lhr.runtimeError) problems.push(`${path}: ${lhr.runtimeError.code} ${lhr.runtimeError.message}`);
      // An /account page measured signed out is the sign-in form under another name.
      const final = pathOf(lhr.finalDisplayedUrl ?? lhr.finalUrl);
      if (final !== path) problems.push(`${path}: measured ${final} instead (redirect)`);
    }
    pages[path] = Object.fromEntries(METRICS.map((metric) => [metric.key, median(measured.map((lhr) => valueOf(lhr, metric)))]));
    for (const metric of METRICS) {
      if (null === pages[path][metric.key] && measured.length) problems.push(`${path}: no value for ${metric.key}`);
    }
  }

  return { pages, problems };
}

function format(metric, value) {
  if (null === value) return 'n/a';
  switch (metric.unit) {
    case 'ms': return `${(value / 1000).toFixed(2)} s`;
    case 'unitless': return value.toFixed(3);
    case 'bytes': return `${Math.round(value / 1024)} KiB`;
    default: return String(Math.round(value));
  }
}

function markdown(preset, environment, pages) {
  const header = ['Page', ...METRICS.map((metric) => metric.label)];
  const lines = [
    `### ${preset}`,
    '',
    `Median of ${environment.runs} runs per page, Lighthouse ${environment.lighthouse}, ${environment.date}, ${environment.version}, commit ${environment.commit}, theme ${environment.theme}.`,
    '',
    `Machine: ${environment.machine}.`,
    '',
    `| ${header.join(' | ')} |`,
    `| ${header.map(() => '---').join(' | ')} |`,
  ];
  for (const [path, values] of Object.entries(pages)) {
    lines.push(`| \`${path}\` | ${METRICS.map((metric) => format(metric, values[metric.key])).join(' | ')} |`);
  }

  return `${lines.join('\n')}\n`;
}

function summary(options) {
  const preset = presetOf(options);
  const directory = options.results ?? '.lighthouseci';
  const template = readFileSync(TEMPLATE, 'utf8');
  const runs = templateRuns(template);
  const lhrs = readdirSync(directory)
    .filter((file) => /^lhr-.*\.json$/.test(file))
    .map((file) => JSON.parse(readFileSync(resolve(directory, file), 'utf8')));
  if (!lhrs.length) fail(`no Lighthouse result in ${directory}`);

  const { pages, problems } = summarize(lhrs, templatePaths(template), runs);
  const first = lhrs[0];
  const environment = {
    preset,
    runs,
    date: options.date ?? new Date().toISOString().slice(0, 10),
    version: options.version ?? 'unreleased',
    commit: options.commit ?? 'unknown',
    machine: options.machine ?? 'unknown',
    theme: options.theme ?? 'unknown',
    lighthouse: first.lighthouseVersion,
    browser: first.environment?.hostUserAgent ?? 'unknown',
    benchmarkIndex: median(lhrs.map((lhr) => lhr.environment?.benchmarkIndex)),
  };

  const document = { ...environment, pages };
  if (options.json) write(options.json, `${JSON.stringify(document, null, 2)}\n`);
  if (options.markdown) write(options.markdown, markdown(preset, environment, pages));
  console.log(markdown(preset, environment, pages));
  // The block to paste in lighthouse-budget.json when a better result is accepted as the new reference.
  console.log(`Reference values of this run (${preset}):\n${JSON.stringify({ measured: { date: environment.date, commit: environment.commit, theme: environment.theme, machine: environment.machine, lighthouse: environment.lighthouse, runs }, pages }, null, 2)}`);

  if (problems.length) fail(`the ${preset} measure is not usable:\n- ${[...new Set(problems)].join('\n- ')}`);
}

function main() {
  const { command, options } = parseArguments(process.argv.slice(2));
  switch (command) {
    case 'config': {
      if (!options.origin) fail('--origin is required');
      const output = options.output ?? resolve(AUDIT_DIR, 'lighthouserc.yaml');
      checkGreenitList(templatePaths());
      const config = effectiveConfig(readFileSync(TEMPLATE, 'utf8'), {
        origin: options.origin,
        preset: presetOf(options),
        outputDirectory: dirname(resolve(output)),
      });
      write(output, config);
      break;
    }
    case 'assertions': {
      const preset = presetOf(options);
      const budget = readBudget(options.budget);
      const assertions = assertionsFor(budget, preset);
      if (!assertions) fail(`${budget.file} has no ${preset} reference yet: measure, then write it`);
      write(options.output ?? resolve(AUDIT_DIR, `lighthouse-assert.${preset}.json`), `${JSON.stringify(assertions, null, 2)}\n`);
      break;
    }
    case 'summary':
      summary(options);
      break;
    default:
      fail('usage: lighthouse.mjs config|assertions|summary [options] (see the head of this file)');
  }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main();
}
