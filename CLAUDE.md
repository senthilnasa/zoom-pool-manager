# Agent instructions — Zoom Pool Manager

1. Read `docs/SPEC.md` (the master prompt) before doing anything. It is the source of truth.
2. Read `docs/zoom-verification.md` before touching any Zoom code. Only use Zoom endpoints, fields, scopes and webhook formats whose row is marked `VERIFIED` or `VERIFIED WITH CHANGES`.
   - If the file still says "NOT YET VERIFIED", or a row you need is `UNVERIFIED`: stop, do not write Zoom code, and tell the owner that milestone M0 must be completed first.
   - Code that does not call Zoom (installer, auth, RBAC, settings) may be built before M0 is finished.
3. Work only on the milestone named in the session request (see SPEC Part J). Do not start the next milestone.
4. No stubs, no TODOs, no fake Zoom behavior. If something cannot be done, follow SPEC Part A1 rule 2 and record it in `docs/zoom-limitations.md`.
5. Before finishing: run `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, and the full test suite. All must pass.
6. End every session with: what was built, how to test it manually, test results, known limitations, files changed.
7. Record any design decision not covered by the spec in `docs/decisions/NNNN-title.md` (copy `0000-template.md`).
8. Single organization only. Never add tenant or organization_id columns.
