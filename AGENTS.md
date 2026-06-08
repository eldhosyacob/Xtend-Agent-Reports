# PHP Project Rules

## Project Structure
- All CSS files must be placed in the `/style` folder.
- Do not use inline CSS (`style=""`) unless explicitly requested.
- Reuse existing CSS classes whenever possible.

## API Development
- All API files must be created inside the `/api` folder.
- Keep API logic separate from UI and page files.
- APIs should return consistent JSON responses.

## Error Handling
- Do not add unnecessary `try-catch` blocks in API files.
- Use `try-catch` only when handling exceptions that can be meaningfully recovered from or logged.
- Prefer validation and conditional checks over wrapping entire APIs in `try-catch`.

## Database
- Use prepared statements for all database queries.
- Use parameter binding to prevent SQL injection.
- Do not modify database schema without approval.

## Code Style
- Follow the existing project structure and coding style.
- Do not create duplicate functions.
- Keep functions modular and reusable.
- Add comments only for complex business logic.

## Frontend
- Keep HTML, CSS, and JavaScript separated.
- Place JavaScript in dedicated JS files when possible.
- Do not use inline JavaScript unless explicitly requested.

## General
- Preserve existing functionality when making changes.
- Minimize changes to unrelated files.
- Ask before introducing new dependencies or frameworks.