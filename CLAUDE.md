# Laravel Translation Manager (fork)

A Laravel package: a web page for editing translation files. This is our fork
of highsolutions/laravel-translation-manager. See readme.md, "Working on this fork".

## Start of every session

- If `docs/local/fork-context.md` exists, read it first. It says what we are
  working on and where the plan is.

## Rules

- **Public repo.** Never put secrets, keys, real user data, texts from the
  applications that use this package, people's names or internal host names in
  code, tests, fixtures or commit messages. Read the staged diff for these
  before every commit.
- **Ask first** before: a push, a tag, writing or running a migration.
- **Plan first.** When asked for a plan or analysis, deliver it and wait.
- **Branches.** `master` is ours. `upstream/master` is the original, read-only.
  Never open a pull request against highsolutions.
- **Keep the diff against the original small.** New behaviour goes in new
  files where possible. Keep the namespace `HighSolutions\TranslationManager`.

## Code

- PHP ^8.3, Laravel 12 and 13.
- Follow the style of the file you are in.
- Comments: one line. Docblocks may say more.
- Check how the package already does something before adding a new way.

## Tests

- Run: `vendor/bin/phpunit` (Orchestra Testbench, MySQL database
  `translation_manager_test`; see readme.md, "Run the tests").
- Every new behaviour gets a test, few and focused, on the critical logic only.
- Each test has a docblock: what it checks, where, why it matters.
- Run the suite before starting and after finishing a change.

## Commits

- `type: summary` (`feat:`, `fix:`, `docs:`, `chore:`), the why in the body.
- Commit messages stand alone: no references to local notes or plans.

## After finishing a piece of work

- Run `/code-review` and `/security-review` on the diff.
- Update the status in `docs/local/fork-context.md`.
