# Pupilovo — instructions for coding agents

## Project and architecture
- Pupilovo is a real Polish pet e-commerce project using React, TypeScript, Vite and SCSS in `frontend/`, WordPress/WooCommerce in Docker, and a custom supplier integration plugin in `wordpress/plugins/pupilovo-dropshipping/`.
- Preserve the current architecture and conventions. Prefer existing services, repositories, REST controllers and WooCommerce APIs over parallel implementations.
- Frontend: use SCSS, not Tailwind or Bulma; preserve the `@/` import alias, TypeScript types, responsive UX and accessibility.
- Supplier Hub already handles supplier sources, product/category mappings, selected imports, pricing, queues and synchronization. Inspect it before adding related capabilities.
- Supplier relationships exist in `psh_product_links` and WooCommerce product metadata, including `_pupilovo_supplier_id` and `_pupilovo_supplier_external_id`. Do not invent an independent product identity system.

## Collaboration and scope
- Work only on the task requested and the current branch. Keep changes small and reviewable; preserve unrelated and untracked files.
- Do not create commits, push branches, merge pull requests, rebase or delete branches unless explicitly instructed.
- Do not run broad test suites, builds, browser automation or Docker operations by default: ChatGPT uses Desktop Commander for independent verification. You may add/update focused tests, but report that they were not executed unless specifically requested.
- At the end, report changed files, database/schema changes, assumptions, risks and any manual verification needed. Do not claim tests passed unless you ran them.
- Never expose secrets from `.env`, credentials, tokens or personal customer data in logs or responses.

## Safety for commerce and supplier operations
- Treat supplier feeds and API responses as untrusted. Validate and sanitize data, enforce WordPress capabilities and REST nonces, and use safe HTTP access and bounded processing.
- Keep WooCommerce as the source of truth for customer orders. Use idempotent operations, audit trails and stable order-item snapshots for fulfillment.
- Never send real orders to suppliers, charge payments, publish products, alter live order statuses, delete WooCommerce data or run destructive migrations without explicit approval.
- Prefer dry-run, draft and manually approved workflows. Make retries safe and prevent duplicate supplier orders.
- Do not assume a live PolZoo account or any production supplier integration; use local fixtures and mock adapters until access is explicitly provided.

## Current implementation direction
- Stage 6: supplier fulfillment. Reuse Supplier Hub and add supplier-level grouping, order snapshots, status history, administrator visibility and explicit approval before external dispatch.
- Keep the supplier fulfillment lifecycle separate from WooCommerce customer order statuses. No automatic external dispatch in the first implementation.
- Preserve backward compatibility and make schema upgrades repeatable; document migrations and failure handling.
