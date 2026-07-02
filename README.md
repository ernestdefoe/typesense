# Typesense Search for Flarum

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](./LICENSE.md)

A free, drop-in [Typesense](https://typesense.org) search driver for **Flarum 2** —
fast, typo-tolerant full-text search across **discussions, users and posts** that anyone
can set up in a few minutes.

Flarum 2 shipped a pluggable search architecture; this extension implements a Typesense
driver for it. Text searches are answered by Typesense; everything else (filtering,
browsing, permissions) stays exactly as Flarum does it. You choose per resource which
searches Typesense answers.

## Why it's safe by design

Typesense is **only ever asked for candidate discussion IDs and their ranking**. Which
results a given user may actually see is decided entirely by Flarum's own
`whereVisibleTo` (including tag restrictions) — never by Typesense. The search server
holds no authorization logic, and the browser never talks to it. Your API key is stored
server-side and is never exposed to visitors.

## Requirements

- Flarum `^2.0`
- A running Typesense server (self-hosted is a single binary or Docker container, or use
  Typesense Cloud)

## Install

```bash
composer require ernestdefoe/typesense
```

### Run Typesense (self-hosted example)

```bash
docker run -d --name typesense -p 8108:8108 \
  -v typesense-data:/data typesense/typesense:27.1 \
  --data-dir /data --api-key='CHANGE_ME' --enable-cors
```

## Set up

1. **Admin → Typesense Search.** Enter the host, port, protocol and API key, then **Save**.
2. Click **Test connection** to confirm Flarum can reach the server.
3. Click **Rebuild index** (or run `php flarum typesense:index`) to populate the index
   from your existing discussions.
4. Turn on **Use Typesense for discussion search** and save.

That's it — searches for discussions are now served by Typesense.

### Console

```bash
php flarum typesense:index          # build / refresh the index
php flarum typesense:index --flush  # delete the index
```

## How indexing works

- Each discussion is one Typesense document: its title plus the concatenated text of its
  visible comment posts.
- Creating, editing or deleting a discussion or post re-indexes the affected discussion
  automatically through Flarum's queued indexing pipeline.
- **Configure a real queue driver** (`redis`, `database`, …) in production so indexing and
  rebuilds run in the background rather than inline on a web request.

Several forums can share one Typesense server safely — collections are namespaced by a
prefix (set one, or it's derived from your forum URL).

## What's indexed

Everything Flarum can full-text search, each in its own namespaced collection:

- **Discussions** — title + the concatenated text of visible comment posts.
- **Users** — username and display name.
- **Posts** — individual comment post text (post-scoped search).

Each is toggled independently in the admin, so you can send discussion search to
Typesense while leaving users or posts on the database, or run all three through it.
(Groups and access tokens have no full-text search in Flarum, so they always stay on the
database driver.)

## License

[MIT](./LICENSE.md) © Ernest Defoe
