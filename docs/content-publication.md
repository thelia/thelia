# Publication of contents and folders

A content or a folder is in one of four states, read from `visible` and two dates, `publish_at` and `unpublish_at`,
at the moment it is asked. The state is global to the row, not per language: a scheduled content goes online in
every language at once.

| State | `visible` | `publish_at` | `unpublish_at` |
| --- | --- | --- | --- |
| Published | 1 | when it went online | NULL, or a future withdrawal |
| Unpublished | 0 | anything | past or now |
| Scheduled | 0 | set (overdue once past) | NULL or future |
| Draft | 0 | NULL | NULL or future |

`visible` stays the public truth: a published row is a visible one, whatever its dates say, so every list that
filters on `visible = 1` (loops, Flexy, slots, the front API) is right without reading the dates. The dates only tell
a hidden row apart. `Thelia\Domain\CMS\Publication\PublicationStatus::of()` holds the rule, `isOverdue()` says
whether a date has come and nothing acted on it, `pendingDate()` names the date the state waits for.

The update script gives every row of an existing shop a state once: a visible row is published since its creation,
a hidden row is unpublished since its last change. No row becomes a draft, and a replay leaves alone what was written
since.

## Changing the state

`Thelia\Domain\CMS\Publication\PublicationService::apply()` is the one place a row changes state. It takes a
`PublicationRequest`, built by name so a gesture cannot be paired with a date it does not take:

| Gesture | Effect |
| --- | --- |
| `PublicationRequest::publish(?unpublishAt)` | online now; a withdrawal still ahead is kept, a given one must be in the future |
| `PublicationRequest::schedule(publishAt, ?unpublishAt)` | hidden until a future date, with an optional later withdrawal |
| `PublicationRequest::cancel()` | a scheduled row becomes a draft; a published row forgets its withdrawal date |
| `PublicationRequest::unpublish()` | offline now |
| `PublicationRequest::scheduleUnpublication(?unpublishAt)` | sets, or clears with null, the withdrawal date of a published row |
| `PublicationRequest::fromStrings(action, ?publishAt, ?unpublishAt)` | the same from the strings a form carries |

A back office or a module goes through the events, so the shared cache and the API cache are purged like after any
other change of a content:

```php
use Thelia\Core\Event\Content\ContentUpdatePublicationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\CMS\Publication\Exception\PublicationException;
use Thelia\Domain\CMS\Publication\PublicationRequest;

try {
    $event = new ContentUpdatePublicationEvent($content, PublicationRequest::schedule($publishAt, $unpublishAt));
    $dispatcher->dispatch($event, TheliaEvents::CONTENT_UPDATE_PUBLICATION);
    $status = $event->getStatus(); // PublicationStatus, the state reached
} catch (PublicationException $exception) {
    // Nothing was written; the message is meant for the person who made the gesture.
}
```

`FolderUpdatePublicationEvent` and `TheliaEvents::FOLDER_UPDATE_PUBLICATION` do the same for a folder;
`ContentFacade::updatePublication($contentId, $request)` wraps the content event. A refused gesture throws a
`PublicationException` and writes nothing:

- `InvalidPublicationDatesException`: a withdrawal before the publication, a date in the past where a future one is
  expected, a missing or unreadable date;
- `InvalidPublicationTransitionException`: a withdrawal scheduled on a row that is not online;
- `EmptyContentPublicationException`: see below.

## The body guard

A content cannot go online with nothing to show in the default language of the shop. The guard sits in
`Content::preSave()` and fires whenever an existing row goes from hidden to visible, whichever path writes the flag:
the publication events, the old update and toggle events, the facade, the admin API and the scheduled command. A
content created visible is not checked, nor are the other languages; a back office may warn about them.

Whichever path writes `visible` on an existing row, the dates are kept in step with it (`VisibilityDatesAligner`,
also asked from `preSave()`): a row taken offline by the old toggle or update events, the admin API or a module gets
`unpublish_at = now` unless a schedule carries it, a row turned on gets its `publish_at` and forgets a past withdrawal.
The state read from the row is therefore always the one the flag says, and the scheduled command never puts back
online a row somebody hid.

`EmptyBodyDetector` decides: tags, `<style>` and `<script>` blocks, entities, non-breaking and zero-width spaces are
nothing to show; an image, a video, an embedded frame on its own is a body. The exception is translated for the
person who asked and never quotes the body. The row in memory is reloaded from the database on a refusal, so a later
save of the same instance does not put it online after all.

## What the shop shows

- The page of a content (`content` view) answers 404 unless the content is visible and its default folder is online:
  a content whose default folder is a draft or is still scheduled is not on the shop. A folder taken offline keeps
  the behaviour an invisible folder always had, its contents stay reachable by their own url.
  `ContentFolderPublicationRule::outsideAnUnreleasedFolderClause()` is the SQL clause both readers share.
- `/api/front/contents` and `/api/front/folders` serve published rows only, under the same folder rule, whatever
  `visible` filter the caller passes; `/api/front/content_folders` serves the links of those contents only. The admin endpoints read every state: `publicationState` (read only) and
  `publishAt`, `unpublishAt` (writable) are on both resources; a withdrawal before the publication, or an empty
  content put online, answers 422.

## Preview

`GET /preview/{type}/{id}` (`content` or `folder`, route `publication_preview`) renders the page whatever the state.
A signed-in administrator reaches it as it is. Anyone else needs the signed link the back office hands out:

```php
use Thelia\Domain\CMS\Publication\Preview\PublicationPreview;
use Thelia\Domain\CMS\Publication\Preview\PublicationPreviewLinkGenerator;

$link = $generator->generate(PublicationPreview::of('content', $contentId, 'fr_FR'));
$link->url;       // absolute, signed, carrying its expiry and the language
$link->expiresAt; // seven days by default: parameter thelia.publication.preview_lifetime_days
```

The third argument names the language the page is previewed in: it travels as the `locale` query parameter, signed
with the rest of the link, and the kernel applies it to the session as it does for any front url carrying `?locale=`
(`LangService::handleLang()`); a link naming a language the shop does not have answers 404. Without it the page renders in the language of the session, as the public page does.

A link with no signature answers 404, so the route gives nothing away; an altered or expired link answers the same
403; signatures are tried under the `publication_preview_per_client` limit (60 an hour per address), which an
administrator does not spend. A row that does not exist answers 404 once the caller is allowed to ask.

The request carries the attribute `_publication_preview` (`['type' => 'content', 'id' => 12, 'locale' => 'fr_FR']`,
`locale` null when the link named none), which a theme reads
with `app.request.attributes.get('_publication_preview')` to show its banner. The visibility check lets the previewed
row through (and only it), and the answer is `private`, `no-store` and `X-Robots-Tag: noindex, nofollow`, so neither
a proxy nor a search engine keeps it.

## The scheduled command

```
php bin/console content:publish-scheduled              # acts on the dates that have come
php bin/console content:publish-scheduled --dry-run    # reports what would change
php bin/console content:publish-scheduled --batch-size=500
```

It puts online the rows scheduled for a moment now past (the date waited for is the publication date) and takes
offline the published rows whose withdrawal date is past, contents and folders alike. A window that closed while
nothing ran is over, not opened. Each row goes through its publication event, so the caches are purged and a module
listening sees the change. A content the body guard refuses stays scheduled, is reported (by a dry run as well, which asks the guard itself),
logged as an error, and makes the command exit with 1 so the scheduler can alert; the pass goes on with the others. A second pass finds nothing left to
do. Two runs at once are kept apart by a lock (`framework.lock`, `LOCK_DSN`).

Nothing runs it automatically: schedule it every few minutes alongside `sale:check-activation`. A shop that does not
run it sees its overdue rows (`PublicationStatus::isOverdue()`), which the back office reports on its dashboard;
nothing catches them up on a visit.
