# Frozen task — Posts API

> This file is the contract. It does not change between runs or arms. Any edit
> invalidates every prior result. Pin the commit SHA of this file in the report.

## Prompt given to the agent (verbatim)

> Add a **Posts** REST resource to the application with these endpoints:
>
> - `POST   /posts`        — create a post
> - `GET    /posts`        — list posts
> - `GET    /posts/{id}`   — fetch one post by id
> - `PUT    /posts/{id}`   — update a post
> - `DELETE /posts/{id}`   — delete a post
>
> A post has: `id` (string, server-assigned), `title` (string, 1–120 chars,
> required), `body` (string, required), `published` (boolean, default false),
> `createdAt` (ISO-8601 timestamp, server-assigned).
>
> Requirements:
> 1. Input validation: `title` and `body` required on create; `title` length
>    1–120; validation failure returns HTTP 422 with per-field errors.
> 2. Persistence: posts are stored in a database (entity + migration +
>    repository). Use the project's standard persistence approach.
> 3. An OpenAPI 3.1 description of all five endpoints.
> 4. A typed **TypeScript** client for the resource.
> 5. Tests covering: create happy-path, create validation failure (422),
>    get-by-id found + not-found (404), and list.
>
> Stop when the acceptance suite passes.

## Acceptance criteria (checked by the harness, not the agent)

A run is **complete** only when an external, frozen suite passes. The suite is
identical for both arms and asserts behavior through the HTTP layer, so
implementation details are free to differ:

- [ ] `POST /posts` with valid body → `201`, returns the created post incl.
      `id` and `createdAt`.
- [ ] `POST /posts` missing `title` → `422`, body has `errors.title`.
- [ ] `POST /posts` with 121-char `title` → `422`.
- [ ] `GET /posts` → `200`, array including created posts.
- [ ] `GET /posts/{id}` for an existing id → `200`, the post.
- [ ] `GET /posts/{id}` for an unknown id → `404`.
- [ ] `PUT /posts/{id}` updates fields → `200`, reflects changes.
- [ ] `DELETE /posts/{id}` → `204`; subsequent `GET` → `404`.
- [ ] The emitted OpenAPI document is valid 3.1 and contains all five operations.
- [ ] The emitted TypeScript client **compiles** under `tsc --strict` with zero
      errors.
- [ ] The project's own test suite for the feature passes.

## Out of scope (do not build, either arm)

Auth, pagination, sorting, soft-deletes, rate-limiting, a Python client. Keep the
task identical and minimal so the measurement is about *plumbing cost*, not
scope creep.
