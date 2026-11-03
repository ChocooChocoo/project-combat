## Project

Rebuild of the Combat Sports Training Center Platform. Analysis and roadmap: `docs/ANALYSIS - COMBAT SPORTS PLATFORM/`.
`backend/` is the Laravel 13 API with MySQL `combat_platform` (Laragon). `frontend/` is React + Vite + coss UI.

## Commands

```bash
# Backend (from backend/)
php artisan dev                # API at http://localhost:8000/api + email worker, together
php artisan test               # backend tests
php artisan migrate            # apply database changes
php artisan app:make-admin you@example.com   # the only way to make a platform admin

# Frontend (from frontend/)
pnpm dev                       # app at http://localhost:5173
pnpm build                     # type-check and build
```

Emails go out through Gmail SMTP (settings in `backend/.env`). The email worker must be running for codes to be sent.

## Rules

For any task bigger than a small fix:

1. Understand the goal and read the code it touches.
2. Write a short plan: what changes, which files, which skills or plugins are needed.
3. Check that the plan is not over-engineered.
4. Implement. Keep the code as simple as the plan.

### 2. Keep it simple and focused

- Do only what the user asked. The latest instruction wins.
- Prefer the smallest change that correctly solves the task.
- Reuse existing code and project patterns before writing new code.
- Do not add abstractions, layers, features, or libraries without a clear need.
- Do not change, clean up, or restructure unrelated code.
- Keep existing working behavior unless the task requires a change.

### 3. Skills and plugins

Use a skill or plugin only when it directly helps the current task. Do not load one just because it is available.

### 5. Git

- Never commit unless the user asks.
- Commit messages contain only the approved message. No `Co-Authored-By`, no Claude or AI author lines, no other author changes.

### 6. Check work before finishing

- Review the changes for mistakes.
- Run the checks that match the change.
- Say clearly what was not checked.
- Never claim something works without checking it when checking was possible.

### 7. Plain language

Use simple, clear words. Keep explanations short. Explain any technical term that is needed.