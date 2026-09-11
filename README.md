# Pupilovo 🐾

**Headless pet e-commerce storefront built with React, TypeScript, WordPress and WooCommerce.**

Pupilovo is a real-world e-commerce project designed around a decoupled architecture: WooCommerce manages products and commerce data, while a custom React application provides the customer-facing storefront.

The project is actively developed using a feature-branch and pull-request workflow and is intended both as a working online store and as a production-style portfolio project.

## ✨ Implemented Features

- WooCommerce-backed product catalog using live store data
- Product categories and category filtering
- Product search
- Dedicated product detail pages using product slugs
- Product availability and pricing from WooCommerce
- Real WooCommerce shopping cart integration
- Add-to-cart from product pages
- Cart quantity updates and product removal
- Live cart item counter in the application header
- Cart totals synchronized with WooCommerce
- Optimistic cart quantity updates with rollback on API errors
- Responsive React storefront
- Customer account UI foundation
- About and shop pages
- Docker-based local WordPress and WooCommerce environment
- Demo WooCommerce catalog and local development setup

## 🧱 Architecture

```text
Customer
   │
   ▼
React + TypeScript storefront
   │
   │ WooCommerce Store API / REST integration
   ▼
WordPress + WooCommerce
   │
   ▼
MySQL
```

The React frontend is separated from the WordPress presentation layer. WordPress and WooCommerce act as the CMS and commerce backend, while the storefront controls the customer experience.

The shopping cart uses WooCommerce's Store API and a browser-session Cart Token, allowing the React application to work with real WooCommerce cart state instead of maintaining a disconnected mock cart.

## 🛠 Tech Stack

**Frontend:** React · TypeScript · Vite · SCSS  
**E-commerce / CMS:** WordPress · WooCommerce  
**Data:** MySQL · WooCommerce Store API / REST APIs  
**Infrastructure:** Docker · Docker Compose  
**Workflow:** Git · GitHub · Feature branches · Pull Requests

## 📁 Project Structure

```text
Pupilovo/
├── frontend/
│   └── src/
│       ├── api/
│       ├── assets/
│       ├── components/
│       ├── context/
│       ├── hooks/
│       ├── layouts/
│       ├── pages/
│       ├── styles/
│       └── types/
├── docs/
├── Dockerfile
├── docker-compose.yml
├── .dockerignore
├── .gitignore
└── README.md
```

## 🚀 Local Development

The project includes a Docker-based local environment for the storefront, WordPress and WooCommerce.

WooCommerce setup, backups and demo catalog instructions are available in [`docs/woocommerce-local.md`](docs/woocommerce-local.md).

Start the environment:

```bash
docker compose up -d
```

Frontend:

```text
http://localhost:5173
```

WordPress:

```text
http://localhost:8080
```

Stop the environment:

```bash
docker compose down
```

## 🔄 Development Workflow

Development is organized around short-lived feature branches. Features are implemented independently and merged into `main` through Pull Requests after verification.

Recent milestones include:

1. Local WordPress and WooCommerce environment with demo catalog
2. WooCommerce-backed product catalog
3. Search and category filtering
4. WooCommerce cart integration
5. Product details page with quantity selection and add-to-cart

This workflow keeps individual changes reviewable and provides a clear history of the project's development.

## 🎯 Engineering Goals

Pupilovo focuses on practical e-commerce engineering rather than a static storefront demo. Key goals are clean separation of concerns, reusable React components, maintainable TypeScript and SCSS, robust WooCommerce integration, responsive UX and incremental delivery through tested feature branches.

## 🚧 Status

**Active development.**

The core storefront-to-WooCommerce integration is working: products are loaded from WooCommerce, customers can search and filter the catalog, open product detail pages and interact with a real WooCommerce-backed cart.

Planned work includes completing the customer account/authentication flow, checkout and further storefront polish.

## 📸 Screenshots

Screenshots and a visual project walkthrough will be added after the current storefront implementation is complete.

## License

The source code is maintained as part of the Pupilovo project and developer portfolio. Commercial-use rights are not granted by default.
