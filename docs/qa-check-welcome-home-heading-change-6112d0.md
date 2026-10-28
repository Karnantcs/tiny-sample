# QA check: "Welcome Home" heading change

_Owen, QA & Review Engineer · 2026-10-28_

Marcus, I checked Devon's change (commit 5b2e833). The text is correct and the build passes. The test file doesn't run, though, so `npm test` doesn't verify the heading.

## Blocking issues

None for the text change itself.

## Defect to fix (not blocking the text change)

1. **`src/App.test.jsx:1-2` and `package.json` "test" script: the test never runs.**
   - `App.test.jsx` imports `@testing-library/react` and `vitest`. Neither is in `package.json` or `package-lock.json` (a search for `vitest|testing-library|jsdom` only matches the test file itself).
   - `package.json` sets `"test": "vite build"`, so `npm test` only builds the app. The heading test is never executed, and "tests pass" only means "build passes".
   - How to see it: run `npx vitest run` after `npm install`. It will fail on the missing imports.
   - Fix (for Devon): add `vitest`, `jsdom`, `@testing-library/react` and `@testing-library/jest-dom` as devDependencies, add a `vitest` config with `environment: 'jsdom'`, and change `"test"` to `"vitest run"`. Keep `vite build` as a separate script.

## Optional

- Add a build step check to CI-style scripts, so `npm run build` stays separate from the test script.

## What I ran and checked

- `git_log`: confirmed Devon's commit 5b2e833 and the earlier commits.
- `git_diff` (stat and `src/App.jsx`): `App.jsx` renders `<h1>Welcome Home</h1>`.
- `search` for `hello|world` (case-insensitive): no matches, so no "Hello World" text is left in the repo.
- `run_tests`: `npm test && npm run build` passed. This is the `vite build` check only, not a real test run.

## Not checked

- I didn't run Vitest, because it isn't installed and the sandbox has no internet.
- I didn't open the page in a browser. The rendered heading is confirmed only from the source.

**Verdict:** the text change is correct and builds. The test suite isn't real yet, so I wouldn't call the heading "tested" until Devon fixes the test setup above.
