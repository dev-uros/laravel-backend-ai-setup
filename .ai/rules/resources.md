---
paths:
  - '{resources/**,public/**,routes/web.php}'
---

# Resources

## No frontend build step — plain CSS, no npm
Vite, Tailwind, npm and node_modules were deliberately removed. There is no package.json, no build, no Node container. The application frontend is a separate project.

What lives here is one Blade page at `/` (`resources/views/dashboard.blade.php`): an operations overview linking to API docs, Horizon and Pulse. Styling is hand-written CSS at `public/css/app.css`, linked with `asset('css/app.css')`.

Never add `@vite` to a view and never reintroduce npm to style a page — that reinstates a whole toolchain for a couple of static pages. Horizon and Pulse inline their own compiled assets and need nothing from this project.

This overrides the Boost "Frontend Bundling" and "Vite Error" guidelines, which assume a Vite setup that no longer exists here. If a real toolchain is ever needed again, raise it with the user first.
