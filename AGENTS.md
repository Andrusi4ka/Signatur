# Signatures Plugin Guidelines

## Main Idea

This is a WordPress plugin for creating reusable email signature templates. A user will eventually be able to create a signature in WordPress, copy it, and paste it into email clients such as Gmail and Outlook with the same visual result.

The plugin should prioritize reliable HTML email output, predictable rendering, simple editing, and compatibility with common email clients.

## Current Scope

The first version provides a WordPress admin menu named `Signatures` with:

- `All signatures` page
- `Add signature` page
- An `Add signature` button beside the `All signatures` title
- Translations for English and Norwegian Bokmal (`nb_NO`) only (Ukrainian and Russian removed in 1.9.0 at the user's request). In Norwegian, "Padding" and "Margin" stay as these words, untranslated.

The plugin entry point is `signatur.php`. Translation files are stored in `languages/` and use the `signatur` text domain.

## Development Rules

- Do not write, edit, delete, or automatically refactor project code without the user's explicit approval in the current conversation.
- Before proposing a code change, first explain what will be changed, why it is needed, and which files will be affected.
- Wait for a clear confirmation such as `так`, `підтверджую`, or an equivalent explicit approval before making code changes.
- It is allowed to inspect files, explain options, identify problems, and prepare a proposed plan without approval.
- Keep implementation steps small and focused. Do not implement future functionality prematurely.
- Preserve existing WordPress conventions and APIs.
- Use the `signatur` text domain for every user-facing plugin string.
- Keep translations synchronized when user-facing strings are added or changed.
- Validate PHP syntax after approved PHP changes using the PHP executable bundled with OSPanel.

## Product Direction

Future work may include:

- Creating and editing signature records
- A signature editor with personal and company information
- HTML email-safe templates
- Preview and copy-to-clipboard functionality
- Testing output in Gmail and Outlook
- Managing multiple signatures

Each feature should be discussed and approved separately before implementation.
