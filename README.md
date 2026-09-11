# Pupilovo 🐾

Modern headless e-commerce platform for pet products.

Pupilovo is a real-world pet store project built with a modern frontend architecture. The project is designed to provide a fast, responsive and maintainable shopping experience while using WordPress and WooCommerce as the backend and content management system.

## Tech Stack

### Frontend

* React
* TypeScript
* Vite
* SCSS
* Custom component system

### Backend

* WordPress
* WooCommerce
* WooCommerce REST API
* MySQL

### Infrastructure

* Docker
* Docker Compose
* Git
* GitHub

## Architecture

```text
React + TypeScript
        │
        │ WooCommerce REST API
        ▼
WordPress + WooCommerce
        │
        ▼
      MySQL
```

The frontend is completely separated from the WordPress presentation layer. WordPress and WooCommerce are used as the CMS and e-commerce backend, while React provides the customer-facing storefront.

## Project Structure

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
├── Dockerfile
├── docker-compose.yml
├── .dockerignore
├── .gitignore
└── README.md
```

## Development

Local WooCommerce setup, backups, and demo catalog instructions:
[WooCommerce local setup](docs/woocommerce-local.md).

The entire development environment runs with Docker Compose.

Start the application:

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

## Development Workflow

The project uses a feature-branch workflow:

```text
main
  │
  ├── feature/header
  ├── feature/homepage
  ├── feature/shop
  ├── feature/product
  ├── feature/cart
  └── feature/checkout
```

Each feature is developed on a separate branch and merged into `main` through a Pull Request.

## Design Goals

Pupilovo is being developed as a real e-commerce platform with a focus on:

* modern and responsive UI
* clean component architecture
* reusable React components
* separation of frontend and backend
* maintainable SCSS architecture
* good performance
* mobile-first experience
* scalable WooCommerce integration

## Status

🚧 **In active development**

The initial project infrastructure and frontend foundation are in place. The storefront is currently being built component by component.

## License

This project is currently private in terms of commercial use. The source code is maintained as part of the Pupilovo project and portfolio.
