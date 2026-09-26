# LYLY ROSE — Implementation Plan

> **Upstream:** this project is **downstream** of `aroma_store`, which is the source of
> truth. Develop and test features upstream first; mirror here only when needed.
> See [UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md).

> **Feature requests route upstream.** This store is downstream of `aroma_store`,
> which is the source of truth and is always ahead on features. A feature requested
> here is referred to `aroma_store`, built and tested there, and mirrored back only
> once that suite is green. See [FEATURE_REQUEST_POLICY.md](FEATURE_REQUEST_POLICY.md).


## Phase 0 — Inspect

Before changing anything:

* inspect `/media/kourosh/VMSSD/projects/lylyrose`
* determine whether files already exist
* inspect existing Docker configuration
* inspect existing WordPress files
* inspect Git status if applicable

Do not destroy existing useful work.

---

# Phase 1 — Foundation

Implement:

* Docker Compose
* WordPress
* database
* persistent storage
* environment configuration
* `.env.example`
* `.gitignore`

Verify that WordPress starts.

---

# Phase 2 — WooCommerce

Install and configure WooCommerce.

Configure:

* store location
* currency
* basic product settings
* tax behavior suitable for initial local development
* shipping framework
* checkout framework

Do not configure a real payment gateway yet.

---

# Phase 3 — LYLY ROSE Theme

Create the custom theme.

Implement:

* typography
* colors
* spacing system
* responsive breakpoints
* header
* navigation
* search
* footer
* product cards
* buttons
* forms
* badges
* alerts
* loading states

The visual identity should be original.

---

# Phase 4 — Product System

Implement:

* product categories
* brands
* attributes
* fragrance families
* fragrance notes
* product metadata
* variations
* stock
* pricing
* sale pricing

Create realistic sample data.

---

# Phase 5 — Catalog

Implement:

* shop page
* category pages
* brand pages
* search results
* filters
* sorting
* pagination

Test combinations of filters.

---

# Phase 6 — Product Page

Implement the complete perfume product page.

Prioritize:

1. images
2. title
3. brand
4. price
5. variation
6. stock
7. add to cart
8. fragrance information
9. notes
10. specifications
11. reviews
12. related products

---

# Phase 7 — Shopping Flow

Implement/test:

* cart
* mini cart
* checkout
* account
* order creation
* coupon
* stock reduction

Use a dummy/offline-compatible checkout configuration during development.

---

# Phase 8 — Discovery Features

Implement:

* wishlist
* comparison
* related products
* popular products
* recommended sections

Only add these after the core shopping flow works.

---

# Phase 9 — SEO / Performance / Accessibility

Review:

* semantic HTML
* headings
* metadata compatibility
* structured product data
* image optimization
* lazy loading
* keyboard navigation
* focus states
* mobile usability

---

# Phase 10 — Testing

Perform an end-to-end test.

Scenario:

1. Open homepage
2. Search for a perfume
3. Open a product
4. Select size
5. Add to cart
6. Open cart
7. Apply coupon
8. Proceed to checkout
9. Enter customer information
10. Create order
11. Verify order in WordPress admin
12. Verify stock behavior

Repeat on mobile viewport.

---

# Phase 11 — Deployment Preparation

Create:

`docs/DEPLOYMENT.md`

Document:

* requirements
* database creation
* file upload
* database import
* URL migration
* HTTPS
* permalinks
* WooCommerce verification
* media verification

Create:

`docs/BACKUP_RESTORE.md`

Document:

* database backup
* WordPress/wp-content backup
* restore procedure

---

# Phase 12 — Final Audit

Before saying "complete":

Check:

* PHP errors
* browser console errors
* broken links
* missing images
* RTL problems
* responsive problems
* WooCommerce warnings
* plugin/theme warnings
* hard-coded localhost URLs
* hard-coded Windows paths
* exposed credentials
* unnecessary dependencies

Fix discovered problems.

---

# Execution Rule

Do the work in phases.

After each phase:

1. implement
2. test
3. fix
4. update documentation
5. continue

Do not skip testing because the next phase depends on the previous phase being stable.

---

# Final Deliverable

At the end, provide a concise report containing:

* what was implemented
* local URL
* how to start the project
* how to stop it
* admin URL
* important credentials/location of credentials
* installed plugins
* theme name
* current limitations
* remaining optional features
* hosting migration steps

Do not claim production readiness if critical functionality is still broken.
