## What and why

<!-- The problem, the change, and the issue it closes (Closes #...). -->

## Checklist

- [ ] Backward compatibility: no break of the surface listed in [BC-PROMISE.md](https://github.com/thelia/thelia/blob/main/BC-PROMISE.md), or the old way is deprecated and still works
- [ ] Database: a change to `local/config/schema.xml` comes with `setup/thelia.sql` and its `setup/update/sql/<version>.sql` migration
- [ ] Changelog: a deprecation or behaviour change has its line in `CHANGELOG.md` (and `UPGRADE-3.x.md` for a deprecation)
- [ ] Tests: each new test was seen failing without the change (the evidence report of the pull request checks it)
