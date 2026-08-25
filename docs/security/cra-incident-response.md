# CRA incident response process

This document describes how OpenStudio, acting as the open-source software steward of
Thelia, handles the notification obligations of the EU Cyber Resilience Act
([Regulation (EU) 2024/2847](https://eur-lex.europa.eu/eli/reg/2024/2847/oj), "CRA").
The reporting obligations apply from **11 September 2026**.

## Role and obligations

Thelia is made available for free as open source software. OpenStudio qualifies as an
**open-source software steward** (CRA Article 24), not as a manufacturer: no CE marking
and no conformity assessment are required. The steward obligations are:

- maintain a coordinated vulnerability disclosure policy — see
  [SECURITY.md](../../SECURITY.md),
- cooperate with market surveillance authorities on request,
- notify actively exploited vulnerabilities and severe incidents (Article 24(3),
  applying Article 14 insofar as the steward is involved in the development of the
  product).

## What triggers a notification

A notification is due when the Thelia security team becomes aware of either:

1. An **actively exploited vulnerability** in Thelia — the core, an official module or
   an official theme — meaning exploitation observed in the wild, not a theoretical
   report;
2. A **severe incident having an impact on the security** of Thelia's development or
   distribution infrastructure — for example a compromise of the GitHub organizations
   (`thelia`, `thelia-modules`, `thelia-templates`), of the release pipeline, or of the
   packages published on Packagist.

Record the exact time of awareness: every deadline below counts from it.

## Who notifies

The OpenStudio security lead (reachable at [contact@thelia.net](mailto:contact@thelia.net))
owns the notification. If unavailable, any OpenStudio maintainer with release rights on
`thelia/thelia` takes over. One person drives the notification timeline; the fix work
happens in parallel and never waits for the notifications.

## Where to notify

Notifications are submitted through the **single reporting platform** established by
ENISA (CRA Article 16). The platform dispatches each notification to the CSIRT
designated as coordinator of the relevant Member State — for France, **CERT-FR** — and
to ENISA.

- ENISA — single reporting platform entry point: <https://www.enisa.europa.eu/>
- CERT-FR (French coordinator CSIRT): <https://www.cert.ssi.gouv.fr/>

## Timeline (Article 14)

For an **actively exploited vulnerability**:

| Deadline | Notification | Content |
|----------|--------------|---------|
| 24 hours after awareness | Early warning | The vulnerability is actively exploited; the Member States where affected users are located, if known. |
| 72 hours after awareness | Vulnerability notification | General information about the product, the nature of the exploit, severity and impact, corrective or mitigating measures taken or available, and how users can respond. |
| 14 days after a corrective or mitigating measure is available | Final report | Description of the vulnerability, its severity and impact; where available, information about the malicious actor; the corrective measure and how it reached users. |

For a **severe incident**: the same 24-hour early warning and 72-hour incident
notification, then a final report **one month** after the incident notification.

## Checklist

1. **Assess, immediately** — confirm the active exploitation or the severe incident and
   record the time of awareness.
2. **T+24 h** — submit the early warning on the reporting platform.
3. **T+72 h** — submit the full notification on the platform.
4. **Fix** — develop, review and release the correction following
   [SECURITY.md](../../SECURITY.md); publish a GitHub Security Advisory and request a
   CVE.
5. **T+14 days after the fix is available** — submit the final report on the platform.
6. **Inform users** — GitHub Security Advisory on the affected repository, release
   notes of the fixed versions on every supported series, announcement on the Thelia
   channels.

## References

- [Regulation (EU) 2024/2847](https://eur-lex.europa.eu/eli/reg/2024/2847/oj) —
  Articles 14 (notification), 16 (single reporting platform) and 24 (stewards).
- [ENISA](https://www.enisa.europa.eu/) — operator of the single reporting platform.
- [CERT-FR](https://www.cert.ssi.gouv.fr/) — French coordinator CSIRT.
- [SECURITY.md](../../SECURITY.md) — coordinated vulnerability disclosure policy.
