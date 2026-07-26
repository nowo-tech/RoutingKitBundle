# FrankenPHP demo

## Start

```bash
make -C demo up-symfony8
# or
make -C demo/symfony8 up
```

Demo URL: **http://localhost:8058**

| Path | Purpose |
| --- | --- |
| `/` | Home |
| `/about` | Sample `#[Routable]` page |
| `/blog/{slug}` | Sample with required param |
| `/_routing` | Path CRUD panel |
| `/health` | Health check |

## Notes

- Bundle is mounted at `/var/routing-kit-bundle` (path repository).
- Path storage: `var/routing_kit/paths.json` inside the demo app.
- Import order in `config/routes.yaml`: panel → app controllers → `type: nowo_routing_kit` last.
