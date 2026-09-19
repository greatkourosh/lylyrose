# LYLY ROSE — Master Instructions

## 1. Project

Build a production-ready Persian RTL e-commerce website for selling perfumes and fragrance-related products.

Project name:

**LYLY ROSE**

Target:

* Persian language
* RTL
* Iran-oriented e-commerce
* Desktop + mobile responsive
* WordPress
* WooCommerce
* Currently running locally on Windows
* Must be deployable to a real hosting server later

Project directory:

`/media/kourosh/VMSSD/projects/lylyrose`

You are working directly on this project directory.

---

# 2. IMPORTANT WORKING RULE

Do NOT only explain how to build the website.

You are the implementation agent.

You must:

1. Inspect the existing project directory.
2. Create the required files and directories.
3. Configure the local development environment.
4. Install/configure WordPress and WooCommerce as appropriate.
5. Create the custom theme and required functionality.
6. Run the project locally.
7. Test the important functionality.
8. Fix errors you encounter.
9. Leave the project in a state that can later be deployed to a normal hosting environment.

Do not stop after generating sample code.

If something is ambiguous, choose the most standard, maintainable and production-safe solution and continue.

Do not repeatedly ask for confirmation for routine implementation decisions.

---

# 3. Core Technical Principles

The website must be:

* maintainable
* modular
* secure
* responsive
* SEO-friendly
* accessible
* performant
* Persian/RTL-first
* WooCommerce-compatible
* easy to deploy
* easy to backup and restore
* independent from this specific Windows machine

Avoid creating a system that only works on the current computer.

The final application must not depend on hard-coded Windows paths.

---

# 4. Technology Direction

Use:

* WordPress
* WooCommerce
* PHP
* MySQL/MariaDB
* HTML
* CSS
* JavaScript

Prefer a custom lightweight WordPress theme rather than building the entire website with Elementor or another heavy visual page builder.

The theme should be developed specifically for LYLY ROSE.

Use WordPress/WooCommerce standard APIs and hooks whenever possible.

Do not modify WordPress core.

Do not modify WooCommerce core.

Custom functionality must live in:

* the custom theme
* custom plugins where appropriate

Do not put large amounts of business logic directly into random theme files.

---

# 5. Local Development

The local environment should preferably use Docker Compose so that the environment is reproducible.

The project should contain an appropriate Docker Compose setup for:

* WordPress
* database
* persistent WordPress data

If a reverse proxy or phpMyAdmin is useful, it may be included, but avoid unnecessary services.

The local environment must be easy to start with a small number of commands.

Document:

* startup
* shutdown
* logs
* database access
* WordPress access
* backup
* restore

---

# 6. Hosting Readiness

The project must NOT be tightly coupled to Docker.

Docker is for local development.

The final WordPress installation should also be deployable to a conventional hosting environment with:

* PHP
* MySQL/MariaDB
* Apache or Nginx

Prepare the project so migration to hosting is straightforward.

Do not require Docker on the final hosting server.

Document a future migration procedure.

---

# 7. Language

Primary language:

Persian (Farsi)

Direction:

RTL

The entire frontend must be designed for Persian users.

Use appropriate Persian typography.

Do not hard-code English UI strings when they should be translatable.

Dates, prices and numbers should be handled appropriately for Persian users while preserving WooCommerce's internal numeric correctness.

---

# 8. Currency

The storefront is intended for Iran.

Use Iranian Toman as the customer-facing currency.

Be careful with Rial/Toman conversions.

Internally maintain numeric correctness and avoid string-based price calculations.

Make the currency configuration easy to change later.

---

# 9. Design Philosophy

The site should feel like a serious, modern Iranian e-commerce marketplace.

The GUI should provide the same level of familiarity, completeness and ease of use that users expect from leading Iranian e-commerce marketplaces. Recreate the proven marketplace information architecture and interaction patterns, including a prominent search-first header, category navigation, promotional sections, product cards, sorting and filtering, product comparison, customer reviews, cart and checkout flow, account area, order tracking, trust signals, and responsive mobile navigation.

Do use digikala.com as a UX reference — study its layout, information architecture, and interaction patterns — but create your own brand identity. Use the requirements above as UX guidance. The product UI must never impersonate or display the name or branding of another marketplace.

The resulting experience should feel immediately familiar to Persian e-commerce users while remaining clearly identifiable as لیلی رز (Lyly Rose).

The website should emphasize:

* product discovery
* search
* filtering
* product comparison
* product information
* trust
* reviews
* purchase flow
* mobile usability

---

# 10. Product Domain

Main product category:

Perfume / Fragrance

The architecture must support future categories such as:

* Men's perfume
* Women's perfume
* Unisex perfume
* Eau de Parfum
* Eau de Toilette
* Perfume oil
* Body spray
* Gift sets
* Samples
* Fragrance accessories

Product attributes should support fragrance-specific information such as:

* brand
* perfume name
* gender
* concentration
* volume
* fragrance family
* season
* occasion
* longevity
* sillage
* top notes
* middle notes
* base notes
* country of origin
* designer / house
* year released

Use WooCommerce product attributes/taxonomies appropriately rather than putting everything into uncontrolled text fields.

---

# 11. Quality Standard

Do not consider the project complete merely because the homepage loads.

The implementation is complete only when:

* WordPress works
* WooCommerce works
* products can be created
* categories work
* product pages work
* cart works
* checkout works
* customer accounts work
* search works
* filtering works
* responsive layout works
* RTL works
* admin works
* basic SEO structure exists
* security basics are implemented
* deployment documentation exists
* backup/restore documentation exists

---

# 12. Development Behavior

Before making major architectural decisions:

1. Inspect the current project.
2. Read all project MD specification files.
3. Determine what already exists.
4. Preserve useful existing work.
5. Avoid unnecessary rewrites.

Keep a development log in:

`docs/DEVELOPMENT_LOG.md`

Record:

* major decisions
* completed tasks
* problems
* fixes
* remaining work

---

# 13. Never

Never:

* modify WordPress core
* modify WooCommerce core
* hard-code machine-specific paths
* hard-code secrets into source code
* commit API keys/passwords
* use insecure default production credentials
* create unnecessary dependencies
* install dozens of unnecessary plugins
* use a heavy page builder unless explicitly required
* leave known PHP/JS errors unresolved
* claim something works without testing it

---

# 14. Final Goal

The final result should be a real, maintainable Persian WooCommerce store called LYLY ROSE that can start locally on this Windows machine and later be migrated to a real hosting server with minimal architectural changes.
