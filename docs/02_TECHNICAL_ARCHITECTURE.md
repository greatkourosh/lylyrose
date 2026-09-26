# LYLY ROSE — Technical Architecture

> **Upstream:** this project is **downstream** of `aroma_store`, which is the source of
> truth. Develop and test features upstream first; mirror here only when needed.
> See [UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md).

> **Feature requests route upstream.** This store is downstream of `aroma_store`,
> which is the source of truth and is always ahead on features. A feature requested
> here is referred to `aroma_store`, built and tested there, and mirrored back only
> once that suite is green. See [FEATURE_REQUEST_POLICY.md](FEATURE_REQUEST_POLICY.md).


## 1. Architecture

Use a standard WordPress + WooCommerce architecture.

Local:

Docker Compose

Production:

Conventional WordPress hosting OR VPS.

The application must not depend on Docker-specific functionality.

---

# 2. Suggested Project Structure

Use a clean structure similar to:

lylyrose-core/

├── docker-compose.yml
├── .env.example
├── .gitignore
├── README.md
├── docs/
│   ├── DEVELOPMENT_LOG.md
│   ├── LOCAL_SETUP.md
│   ├── DEPLOYMENT.md
│   ├── BACKUP_RESTORE.md
│   └── ARCHITECTURE.md
│
├── wordpress/
│   └── wp-content/
│       ├── themes/
│       │   └── aroma-store/
│       └── plugins/
│           └── lylyrose-core/
│
└── docker/

Adjust the structure if a better WordPress/Docker architecture is appropriate.

Do not unnecessarily duplicate WordPress core files in the repository.

---

# 3. Custom Theme

Create:

`aroma-store`

The theme must be custom-designed for this store.

It should include appropriate separation for:

* header
* footer
* navigation
* product cards
* product archive
* single product
* cart
* checkout
* account
* homepage sections
* reusable components

Use WooCommerce template overrides only when necessary.

Keep WooCommerce compatibility in mind.

---

# 4. Custom Core Plugin

Create a small custom plugin:

`lylyrose-core`

Purpose:

Store LYLY ROSE-specific business functionality that should survive theme changes.

Examples:

* custom product taxonomies
* custom product metadata
* fragrance note structures
* custom shortcodes/components where necessary
* custom WooCommerce hooks
* brand functionality
* custom product badges

Do not put business logic into WordPress core.

Do not put everything into the theme.

---

# 5. Product Data Model

Use WooCommerce products as the primary product entity.

Use taxonomies for values useful for filtering/grouping.

Potential taxonomies/attributes:

* brand
* gender
* concentration
* fragrance_family
* season
* occasion

Use structured metadata where a value is genuinely product-specific.

Avoid creating a separate custom product database unless there is a compelling technical reason.

---

# 6. Fragrance Notes

Model fragrance notes in a maintainable way.

Potential structure:

Top Notes

Heart Notes

Base Notes

A note should ideally be reusable.

For example:

Bergamot

could be associated with many products.

Avoid storing everything as one giant comma-separated string if structured data provides a better solution.

---

# 7. Database

Use MySQL or MariaDB compatible with current supported WordPress/WooCommerce versions.

Use persistent Docker volumes locally.

Do not hard-code database credentials.

Use `.env`.

Provide `.env.example`.

Never commit real credentials.

---

# 8. Configuration

Configuration must be environment-based.

Examples:

* database name
* database user
* database password
* database host
* WordPress URL

Never hard-code:

`/media/kourosh/VMSSD/projects/lylyrose`

inside application code.

The Windows path belongs only to local tooling/configuration where necessary.

---

# 9. Assets

Keep assets organized.

Prefer:

`assets/css`

`assets/js`

`assets/images`

Optimize images.

Do not ship huge unnecessary images.

Use responsive image support.

---

# 10. Performance

Avoid unnecessary JavaScript.

Avoid huge frontend frameworks for simple UI components unless there is a strong reason.

Optimize:

* CSS
* JS
* images
* database queries
* WooCommerce queries

Do not install caching plugins during initial development unless required.

Keep the architecture compatible with future caching.

---

# 11. Security

Implement standard WordPress security practices.

Use:

* nonces
* capability checks
* sanitization
* escaping
* prepared database queries
* secure AJAX handling
* validation

Never trust frontend input.

Never expose secrets.

Do not create custom authentication when WordPress authentication is sufficient.

---

# 12. SEO

Maintain SEO-friendly architecture.

Use semantic HTML.

Proper heading hierarchy.

Product schema should remain compatible with WooCommerce.

Avoid duplicate URLs.

Avoid unnecessary query-parameter indexation where applicable.

---

# 13. Accessibility

Target good practical accessibility.

Use:

* semantic HTML
* keyboard navigation
* visible focus states
* alt text
* accessible forms
* appropriate labels
* sufficient contrast
* ARIA only where necessary

---

# 14. Hosting Migration

The deployment architecture should support:

### Option A

Shared WordPress hosting.

### Option B

VPS with:

* Nginx/Apache
* PHP
* MySQL/MariaDB

Document both at a high level.

The migration process should include:

1. Backup database
2. Backup wp-content
3. Create production database
4. Upload WordPress files
5. Import database
6. Configure wp-config.php/environment
7. Replace local URL with production URL safely
8. Verify permalinks
9. Verify WooCommerce
10. Verify media
11. Verify checkout
12. Enable HTTPS

---

# 15. Local URLs

Choose a sensible local URL.

For example:

`http://localhost:8080`

or another appropriate port.

Document it in:

`docs/LOCAL_SETUP.md`

Do not assume port 80 is available.

---

# 16. Docker

Use a maintainable Docker Compose file.

Do not use:

* privileged containers unnecessarily
* host networking unnecessarily
* hard-coded machine-specific paths inside containers

Use named volumes or project-relative paths appropriately.

---

# 17. Git

Prepare the project for Git.

`.gitignore` must exclude:

* secrets
* `.env`
* database dumps containing credentials
* unnecessary generated files
* caches
* logs where appropriate

Provide:

`.env.example`

---

# 18. Testing

Before declaring the project complete, test:

### WordPress

* installation
* login
* admin

### WooCommerce

* product creation
* category
* attributes
* inventory
* price
* variation

### Frontend

* homepage
* category
* search
* filters
* product page
* cart
* checkout
* account

### Responsive

* mobile
* tablet
* desktop

### Persian

* RTL
* Persian text
* Persian product names
* forms
* checkout

### Security

* unauthorized requests
* form validation
* nonce handling

### Deployment

* backup
* restore
* fresh installation procedure

---

# 19. Documentation

Create and maintain:

`README.md`

`docs/LOCAL_SETUP.md`

`docs/DEPLOYMENT.md`

`docs/BACKUP_RESTORE.md`

`docs/ARCHITECTURE.md`

`docs/DEVELOPMENT_LOG.md`

Documentation must describe the actual implemented system, not an imagined future system.

Update documentation when architecture changes.

---

# 20. Definition of Done

The technical implementation is complete only when:

* local environment starts successfully
* WordPress loads
* WooCommerce is configured
* custom LYLY ROSE theme is active
* custom core plugin works
* sample products work
* product variations work
* catalog works
* filters work
* search works
* cart works
* checkout works
* account works
* RTL works
* responsive UI works
* no obvious PHP errors
* no obvious JavaScript console errors
* project can be backed up
* project can theoretically be migrated to hosting
* deployment documentation exists
* `.env.example` exists
* no secrets are committed
