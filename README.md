# Pupilovo 🐾

**Headless pet e-commerce storefront built with React, TypeScript, WordPress and WooCommerce.**

Pupilovo is a portfolio e-commerce project built around a decoupled architecture. WooCommerce manages products, customers, carts and orders, while a custom React application provides the customer-facing storefront.

The project is developed using feature branches, Pull Requests and automated quality checks. The current goal is a complete demonstrational e-commerce flow rather than a production store processing real payments.

## ✨ Implemented Features

### Storefront

- WooCommerce-backed product catalog using live store data
- Product categories and category filtering
- Product search
- Dedicated product detail pages using product slugs
- Product availability and pricing from WooCommerce
- Responsive React storefront
- About and shop pages

### Cart & Checkout

- Real WooCommerce Store API cart integration
- Browser-session Cart Token
- Add-to-cart from catalog and product pages
- Cart quantity updates and product removal
- Live cart item counter in the application header
- Cart totals synchronized with WooCommerce
- Optimistic quantity updates with rollback on API errors
- Dedicated React checkout flow
- Billing and shipping form
- Polish shipping address support
- Courier shipping method with WooCommerce-backed totals
- Guest checkout
- Test Cash on Delivery payment flow
- WooCommerce order creation
- Order confirmation page

### Customer Accounts

- WooCommerce customer registration
- Email and password login
- Google Sign-In
- Server-side Google token verification
- Safe linking of Google identities with existing customer accounts
- WordPress/WooCommerce authenticated session
- Session restoration
- Logout with REST nonce protection
- Customer order history
- Paginated order history restricted to the authenticated customer

### Development & Quality

- Docker-based local WordPress, WooCommerce and MySQL environment
- Demo WooCommerce catalog
- PHP integration tests for authentication, Google token verification and order history
- ESLint and TypeScript/Vite production build checks
- GitHub Actions quality workflow
- Feature-branch and Pull Request development workflow

## 🧱 Architecture

```text
Customer
   │
   ▼
React + TypeScript storefront
   │
   │ WooCommerce Store API / custom REST API
   ▼
WordPress + WooCommerce
   │
   ▼
MySQL
```

The React frontend is separated from the WordPress presentation layer. WordPress and WooCommerce act as the CMS and commerce backend, while React controls the storefront experience.

The cart uses WooCommerce's Store API and a browser-session Cart Token. Customer authentication uses the native WordPress/WooCommerce session through a custom REST API, keeping cart state and authenticated account state as separate concerns.

## 🛠 Tech Stack

**Frontend:** React · TypeScript · Vite · SCSS
**E-commerce / CMS:** WordPress · WooCommerce
**Data:** MySQL · WooCommerce Store API · Custom WordPress REST API
**Authentication:** WordPress/WooCommerce sessions · Google Sign-In
**Infrastructure:** Docker · Docker Compose
**Quality:** ESLint · TypeScript · PHP integration tests · GitHub Actions
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
├── wordpress/
│   └── plugins/
│       └── pupilovo-auth/
├── docs/
├── .github/
│   └── workflows/
├── Dockerfile
├── docker-compose.yml
├── .env.example
└── README.md
```

## 🚀 Local Development

The project includes a Docker Compose environment for the React storefront, WordPress, WooCommerce and MySQL.

WooCommerce setup, backups and demo catalog instructions are available in `docs/woocommerce-local.md`.

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

Google Sign-In requires the local Google client ID configuration described by the project's environment example and authentication documentation.

## ✅ Quality Checks

Frontend lint:

```bash
docker compose exec frontend sh -lc 'cd frontend && npm run lint'
```

Production build:

```bash
docker compose exec frontend sh -lc 'cd frontend && npm run build'
```

Authentication integration tests:

```bash
docker compose exec wordpress php /var/www/html/wp-content/plugins/pupilovo-auth/tests/auth-integration.php
```

Google token tests:

```bash
docker compose exec wordpress php /var/www/html/wp-content/plugins/pupilovo-auth/tests/google-token.php
```

Customer order-history integration tests:

```bash
docker compose exec wordpress php /var/www/html/wp-content/plugins/pupilovo-auth/tests/orders-integration.php
```

GitHub Actions also runs repeatable frontend and PHP quality checks without requiring repository secrets.

## 🔄 Development Workflow

Development is organized around GitHub Issues, a Kanban project and short-lived branches.

```text
Issue
  ↓
feature / fix / chore branch
  ↓
Pull Request
  ↓
automated quality checks
  ↓
main
```

Major completed milestones include the local WooCommerce environment, live product catalog, search and filtering, product details, Store API cart, checkout and shipping flow, customer authentication, Google Sign-In and customer order history.

## 🚧 Status

**Active development — functional demo flow available locally.**

The main commerce foundation is implemented. Products come from WooCommerce, the cart uses real Store API state, checkout creates WooCommerce orders, and customers can register, sign in and view their own order history.

Remaining work focuses on completing account management, customer addresses and password recovery; strengthening checkout and order-confirmation validation; sandbox payment integration; transactional email testing; catalog and UX polish; security review; E2E testing; and final demo deployment.

Real payment processing is intentionally not enabled at this stage.

## 📸 Screenshots

Screenshots and a visual project walkthrough will be added during final demo preparation.

## License

The source code is maintained as part of the Pupilovo project and developer portfolio. Commercial-use rights are not granted by default.