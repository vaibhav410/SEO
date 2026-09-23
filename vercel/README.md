# Vercel front door

This folder is the only thing deployed to Vercel. It contains no application code: `vercel.json` forwards
every request (pages, forms, admin, API, assets) to the PHP + MySQL backend running on Render, so visitors use the
Vercel URL and CDN while PHP keeps rendering the HTML server-side.

```bash
cd vercel
vercel deploy --prod
```

The backend's `APP_URL` environment variable is set to the Vercel URL, so canonical links, the sitemap and
robots.txt all point to the Vercel domain.

`public/` is intentionally empty (Vercel requires an output directory); it must never contain PHP files.
