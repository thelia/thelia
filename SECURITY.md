# Security Policy

Thelia is free and open source software stewarded by [OpenStudio](https://www.openstudio.fr).
This policy explains how to report a vulnerability, what happens after a report, and
which versions receive security fixes.

## Reporting a vulnerability

**Never report a security problem through a public issue, discussion or pull request.**

Report it privately, in one of two ways:

- Preferred: open a private report from this repository's
  [Security tab](https://github.com/thelia/thelia/security/advisories/new)
  ("Report a vulnerability").
- Or email [contact@thelia.net](mailto:contact@thelia.net) with `[SECURITY]` in the
  subject line.

A useful report includes:

- the affected component and version (or branch and commit),
- a description of the vulnerability and its impact,
- steps to reproduce, or a proof of concept,
- a suggested fix or mitigation, if you have one.

We practice coordinated disclosure: please keep the details confidential until a fixed
release is available. We credit reporters in the advisory unless they prefer to stay
anonymous.

## Scope

In scope:

- the Thelia core: this repository and the packages published from it
  (`thelia/thelia`, `thelia/core`, `thelia/setup`, `thelia/config`),
- the official modules published under [thelia-modules](https://github.com/thelia-modules),
- the official themes published under [thelia-templates](https://github.com/thelia-templates).

Out of scope:

- third-party modules and themes — report them to their maintainers,
- vulnerabilities in upstream dependencies — report them upstream; we ship a fixed
  release once a patched version is available,
- issues specific to a website running Thelia — report them to the site owner.

## What to expect

| Step | Target |
|------|--------|
| Acknowledgement of your report | 72 hours |
| Triage and first assessment | 7 days |
| Fix for a confirmed vulnerability | 90 days |
| Public disclosure | Coordinated with you, once a fixed release is available |

These targets can shift for particularly complex issues; if they do, we keep you
informed.

## Advisories and CVEs

Confirmed vulnerabilities are published as
[GitHub Security Advisories](https://github.com/thelia/thelia/security/advisories) on
the affected repository once a fixed release is available. We request CVE identifiers
through GitHub for vulnerabilities affecting released versions.

## Supported versions

| Series | Branch | Supported | End of support |
|--------|--------|-----------|----------------|
| 3.1 | `main` | Yes — active development | — |
| 3.0 | — | No — update to 3.1 | 16 September 2026, with the 3.1.0 release |
| 2.6 | `2.6` | Yes — security fixes | To be announced |
| 2.5 and older | — | No | Ended |

Thelia 3 is developed on a single trunk: a security fix ships in the next 3.x release, and
every 3.x release updates a 3.0.0 shop in place (see `UPDATE.md`). A maintenance branch is
only opened the day a fix has to ship without the changes waiting on `main`. Security fixes
are therefore released for the latest 3.x and 2.6 versions only: keep your installation on
the most recent release of its series.

## Published advisories

Advisories fixed in a release are listed in the release notes and on the
[Security tab](https://github.com/thelia/thelia/security/advisories). The 3.1.0 release
fixes GHSA-59cp-795h-6wgx, GHSA-m887-7g6m-w83g, GHSA-r63g-6wfg-v5v9 and, through
TwigEngine 1.0.9, GHSA-8ffm-2g9j-m8pp.
