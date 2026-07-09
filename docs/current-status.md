# Current Status

## Known Issues

- Community moderation is implemented and enforced (see docs/community-moderation.md) but has no cross-community pending queue, no audit trail, and no submitter notifications
- Uploading multiple images on initial creation isn't possible
- Rotation of images sometimes collides with cache busting so the rotation isn't accurate

## Technical Debt

- public/index.php is ~2100 lines
- routing is manual

## Next Priorities

1. Tune community moderation: add a cross-community pending queue, audit logging, and submitter notifications (see docs/community-moderation.md § Known gaps)
