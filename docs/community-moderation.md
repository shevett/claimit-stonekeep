# Community Moderation Architecture

## Overview

Moderation is a per-community, opt-in feature. A community owner enables it via
a `moderated` flag; when enabled, the owner also chooses whether new listings
start hidden by default. Moderators (the owner plus any users granted the role)
can then approve or hide individual item listings within that community. There
is no global/site-wide moderation queue — moderation state and controls are
scoped entirely to a single community at a time.

This document describes the *current, implemented* behavior as of this
codebase — not a proposal. It supersedes the "moderator function doesn't do
anything" note previously in `docs/current-status.md`, which was accurate at
an earlier point in the project but is now stale.

## Data model

| Table | Column | Purpose |
|---|---|---|
| `communities` | `moderated` (bool) | Turns moderation on/off for the community. When off, all new items are immediately visible. |
| `communities` | `hide_new_items_by_default` (bool) | Only consulted when `moderated = 1`. If true, new items start `hidden`; if false, they start `online` even though the community is moderated (moderators can still hide them manually after the fact). |
| `items_communities` | `status` (`'online'` \| `'hidden'`) | Per-(item, community) visibility state. An item can be `online` in one community and `hidden` in another simultaneously. |
| `community_moderators` | `(user_id, community_id)` | Grants moderator role. The community **owner** is an implicit moderator and does not need a row here. |
| `community_allowlist` | `(user_id, community_id)` | Users whose new listings are made visible immediately even when the community hides new listings by default. |
| `community_denylist` | `(user_id, community_id)` | Users whose new listings always start hidden in a moderated community, regardless of the hide-by-default setting. |

## Item submission flow

The hidden/online decision is centralized in **`determineInitialItemStatus($communityId, $userId)`**
(`includes/communities.php`), the single place to add new moderation rules going
forward:

```php
function determineInitialItemStatus($communityId, $userId)
{
    // ... looks up communities.moderated + hide_new_items_by_default ...
    if (!$community || !(bool)$community['moderated']) {
        return 'online';
    }
    if (isUserOnCommunityDenylist($userId, $communityId)) {
        return 'hidden';
    }
    if ((bool)$community['hide_new_items_by_default']) {
        if (isUserOnCommunityAllowlist($userId, $communityId)) {
            return 'online';
        }
        return 'hidden';
    }
    return 'online';
}
```

Rule order: not moderated → always `online`; moderated + denylisted → always
`hidden` (denylist wins regardless of the hide-by-default setting); moderated +
hide-by-default → `hidden` unless allowlisted, in which case `online`;
moderated, not hide-by-default, not denylisted → `online`.

It is called from both places that create `items_communities` rows, both of
which now also thread through the posting user's id:

1. **`createItemInDb()`** (`includes/items.php`) — initial item submission.
   Inserts the `items` row, then for each target community calls
   `determineInitialItemStatus($communityId, $itemData['user_id'])` and
   inserts the `items_communities` row with the resulting status.
   Slack/Discord notifications are sent **only** for communities where the
   item landed `online` — a pending item does not announce itself as live in
   a moderated community.
2. **`setItemCommunities($itemId, $communityIds, $userId)`**
   (`includes/communities.php`) — used by the `edit_item` AJAX action when a
   user changes which communities an item belongs to. The caller
   (`public/index.php`, `edit_item` handler) passes the **item's owner**
   (`$item['user_id']`, fetched before the permission check), not the editing
   session user, so an admin editing someone else's listing doesn't
   accidentally apply their own allow/deny status.

There is no separate "submit for approval" step or workflow state beyond this single `online`/`hidden` flag per community.

## Visibility / query rules

`getAllItemsFromDb()` (`includes/items.php:460-521`) governs what shows up in any item listing:

- **Single-community view with `$includeHidden = true`**: skips the `status = 'online'` filter entirely, returning both online and hidden items (with `community_status` exposed in the result). This is the only path that can surface hidden items.
- **Every other case** — the home feed, multi-community aggregate views, or any view where the viewer isn't a moderator — always filters `AND ic.status = 'online'`. Hidden items never leak into aggregate views regardless of role.

`templates/community.php:25-37` computes `$isModerator` for the current user/community and passes it straight through as `$includeHidden`:
```php
getAllItemsEfficiently($showGoneItems, $communityId, $isModerator)
```
So a moderator viewing their community's page sees hidden items **inline in the same feed**, distinguished by an `is_pending_approval` flag — not via a dedicated queue page.

## Permission model

`isCommunityModerator($userId, $communityId)` (`includes/communities.php:431-450`) is the single source of truth: returns true if the user owns the community (`isCommunityOwner`) or has a row in `community_moderators`. It is enforced at:

| Location | Gates |
|---|---|
| `public/index.php:824` | `toggle_item_visibility` AJAX action — the approve/hide toggle itself |
| `public/index.php:1180-1194` | Community actions `get`, `update`, `get_moderators`, `add_moderator`, `remove_moderator`, plus `test_slack`/`test_discord` |
| `templates/community.php:25` | Whether hidden items and moderation controls appear at all in a community's feed |
| `templates/community-edit.php:27` | Access to the community edit page (where moderation settings and the moderator list live) |

Site admins (`isAdmin()`) bypass these checks everywhere they're used alongside `isCommunityModerator()`.

### Approve/hide toggle

There are no separate "approve" and "hide" actions — `toggleItemCommunityStatus($itemId, $communityId)` (`includes/items.php:409-437`) simply flips the current `items_communities.status` between `'online'` and `'hidden'`. The function itself performs **no** authorization check; its docblock explicitly states that's the caller's responsibility. The only caller is the `toggle_item_visibility` handler in `public/index.php`, which does check `isCommunityModerator()` before calling it — so today there's a single authorized entry point, but the function is not intrinsically safe if called from elsewhere in the future.

### Moderator management functions (`includes/communities.php`)

| Function | Line | Behavior |
|---|---|---|
| `isCommunityModerated($communityId)` | 332 | Reads `communities.moderated`. |
| `getCommunityModerators($communityId)` | 354 | Lists moderators (excludes owner, who is implicit). |
| `addCommunityModerator($userId, $communityId)` | 382 | Idempotent insert (`ON DUPLICATE KEY UPDATE user_id = user_id`). |
| `removeCommunityModerator($userId, $communityId)` | 408 | Deletes the grant. |
| `isCommunityModerator($userId, $communityId)` | 431 | The permission check described above. |

## Allowlist / Denylist

Two per-community lists, functionally parallel to the Moderators feature and
maintained by the same set of people (community moderators or a site admin):

| Function | Behavior |
|---|---|
| `getCommunityAllowlist($communityId)` / `getCommunityDenylist($communityId)` | List entries, joined to `users` for display. |
| `addCommunityAllowlistEntry($userId, $communityId)` / `addCommunityDenylistEntry(...)` | Idempotent insert. |
| `removeCommunityAllowlistEntry($userId, $communityId)` / `removeCommunityDenylistEntry(...)` | Deletes the entry. |
| `isUserOnCommunityAllowlist($userId, $communityId)` / `isUserOnCommunityDenylist(...)` | Boolean checks consumed by `determineInitialItemStatus()`. Unlike `isCommunityModerator()`, these have no owner-implies-membership shortcut — membership is strictly by table row. |

AJAX actions `get_allowlist`/`add_allowlist`/`remove_allowlist` and the
denylist equivalents are gated by the same `$ownerOrModeratorActions`
permission check as the Moderators actions (`public/index.php`,
`isCommunityModerator($currentUser['id'], $requestCommunityId)` or site
admin).

## UI surfaces

- **`templates/community-edit.php`** — "Moderators" tab: lists current moderators, an email-based "add moderator" form (resolves email → user via `getUserByEmail`, refuses to add someone who already owns the community), and a remove button per row. Also hosts the `moderated` and `hide_new_items_by_default` checkboxes with inline help text explaining the effect. "Allowlist" and "Denylist" tabs follow the identical layout/interaction pattern (add by email, remove via trashcan button), reusing the same CSS classes.
- **`templates/item-card.php:161-173`** — for moderators only, renders either "✅ Approve / Make Visible" (if pending) or "🚫 Hide from Community" (if online), both calling the same `toggleItemVisibility(itemId, communityId)` JS function against the `toggle_item_visibility` AJAX action. A "Not Visible" badge marks pending items in the feed.

## Known gaps (candidates for future tuning)

1. **No cross-community moderation inbox.** A moderator of several communities must visit each community's page individually to find pending items — there's no unified "pending across all my communities" view.
2. **No audit trail.** Approving or hiding an item doesn't record who did it or when (`items_communities` has no `updated_by`/`updated_at` for this transition). The same applies to allow/deny list changes.
3. **No submitter notification.** A user isn't notified when their item is approved or hidden by a moderator, or when they're added to/removed from an allow/deny list.
4. **`toggleItemCommunityStatus()` has no built-in authorization** — safe today because it has exactly one, already-gated caller, but that invariant lives in `public/index.php`, not the function itself.
5. **Binary state only.** There's no "rejected" (vs. "hidden") status, no reason/note field, and hiding an already-online item is indistinguishable in storage from an item that never got approved.
6. ~~`setItemCommunities()` bypassed moderation when adding an item to a community post-creation~~ — **fixed**; both insertion paths now go through `determineInitialItemStatus()`.
7. **No cross-list validation.** A user can be on both a community's allowlist and denylist simultaneously (denylist wins, per the rule order above); nothing warns an admin about the conflict when adding to either list.
