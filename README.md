# LYLY ROSE (لیلی رز)

A production-ready Persian RTL e-commerce website for selling perfumes and fragrance-related products, styled after Digikala.

## Features

- **Language**: Persian (Farsi) with RTL support
- **Currency**: Iranian Toman (`IRT`) — prices render as `۲۳,۰۰۰,۰۰۰ تومان` with Persian digits, no decimals
- **Plugins**: WooCommerce, lylyrose-core, persian-woocommerce (ووکامرس فارسی), wp-parsidate, plus security/backup/cache/SEO/multivendor stack — see `docs/DEVELOPMENT_LOG.md`
- **Login URL**: moved to `/secure-login` by WPS Hide Login (`wp-login.php` redirects away)
- **Platform**: WordPress + WooCommerce
- **Active Theme**: `lylyrose` — a Digikala-inspired storefront (red #ef394e accent, IRANYekan/Vazirmatn fonts, RTL-first layout)
- **Product page**: Digikala-style single product layout — gallery / info / sticky buybox grid, discount badge, seller row, stock line, quick-specs card, reviews histogram + Q&A row, and a fixed mobile purchase bar (≤768px)
- **Product codes**: every product gets a public code `sku-<digits>` (from its SKU) shown as «کد کالا» on the product page and in the Products admin column; alias URL `/product/sku-<digits>/` serves the product while `/product/<slug>/` stays canonical (handled by `ASC_Product_Code` in lylyrose-core; when SKUs share a digit run the oldest product keeps the run-based code and the rest use their ID, so no product 404s)
- **Legacy Theme**: `aroma-store` — original custom perfume theme (kept as fallback)
- **Responsive**: Mobile-first design, fully responsive

## Project Structure

```
lylyrose/
├── docker-compose.yml          # Docker environment configuration
├── docker/
│   ├── run-tests.sh            # Full test suite (lint + config + HTTP smoke tests)
│   └── uploads.ini             # PHP upload limits for the WP container
├── .env.example                # Environment variables template
├── .gitignore                  # Git ignore rules
├── README.md                   # This file
├── docs/
│   ├── 00_MASTER.md            # Project master document
│   ├── 01_REQUIREMENTS.md      # Requirements
│   ├── 02_TECHNICAL_ARCHITECTURE.md # Technical architecture
│   ├── 03_IMPLEMENTATION_PLAN.md    # Implementation plan
│   ├── 04_DEPLOYMENT.md        # Production deployment guide (hosting-ready branch)
│   ├── CONTINUATION.md         # Continuation points
│   ├── DEVELOPMENT_LOG.md      # Development progress log
│   └── FEATURES_ROADMAP.md     # Prioritized feature recommendations
└── wordpress/
    └── wp-content/
        ├── themes/
        │   ├── lylyrose/        # ACTIVE: Digikala-style theme (homepage, shop, product, cart, checkout)
        │   ├── digikala-v1.0.0/ # Frozen v1.0.0 snapshot of the old digikala theme (rollback copy)
        │   ├── aroma-store/     # Original perfume store theme (fallback)
        │   └── aroma-store-old/ # Archive copy of aroma-store (do not edit)
        └── plugins/
            └── lylyrose-core/   # Core functionality plugin
```

## Local Development

### Prerequisites

- Docker Desktop (Windows, Mac, or Linux)
- Docker Compose

### Setup

1. Copy environment file:
```bash
cp .env.example .env
# Edit .env with your settings
```

2. Start the development environment:
```bash
docker-compose up -d
```

3. Access the site:
- Website: http://localhost:8080
- Admin: http://localhost:8080/wp-admin
- phpMyAdmin: http://localhost:8081

4. Admin credentials:
- Username: `admin`
- Password: `admin123` (change after first login)

### Commands

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# Restart containers
docker-compose restart

# Access WordPress container
docker exec -it lylyrose-wp bash
```

## Deployment to Production

1. Export database:
```bash
docker exec lylyrose-db mysqldump -u root -p lylyrose > lylyrose.sql
```

2. Backup wp-content:
```bash
tar -czf wp-content.tar.gz wordpress/wp-content
```

3. Upload files to production server

4. Import database:
```bash
mysql -u root -p production_db < lylyrose.sql
```

5. Update URLs:
```bash
wp search-replace 'http://localhost:8080' 'https://yourdomain.com' --allow-root
```

6. Update siteurl and homeurl in wp_options table

7. Set permalinks: `wp-admin/options-permalink.php`

## Product Categories

LYLY ROSE supports the following perfume categories:

- مردانه (Men's)
- زنانه (Women's)
- یونیسکس (Unisex)
- ادو پرفیوم (Eau de Parfum)
- ادو تویلت (Eau de Toilette)
- عطر روغنی (Perfume Oil)
- بادی اسپلش (Body Splash)
- ست هدیه (Gift Sets)
- سمپل (Samples)

## Product Attributes

- برند (Brand)
- جنسیت (Gender)
- غلظت (Concentration)
- حجم (Volume)
- خانواده عطر (Fragrance Family)
- فصل (Season)
- مناسبت (Occasion)
- ماندگاری (Longevity)
- استقرار (Sillage)
- یادداشت‌های بالا (Top Notes)
- یادداشت‌های میانی (Heart Notes)
- یادداشت‌های پایه (Base Notes)

## Security Notes

⚠️ **IMPORTANT**: Never commit the `.env` file as it contains sensitive credentials. The file is already listed in `.gitignore`.

## Testing

Run the full test suite (requires the Docker stack to be up):

```bash
bash docker/run-tests.sh
```

The suite verifies:

1. PHP syntax (`php -l`) on all custom theme and plugin files
2. Active theme is `lylyrose`
3. Required plugins are active (WooCommerce, lylyrose-core)
4. WooCommerce currency is Toman (`IRT`) and site locale is `fa_IR`
5. HTTP smoke tests: homepage, shop, cart, checkout, my-account, login all return 200
6. Homepage renders RTL with Lyly Rose theme markup (announcement bar, hero/offers sections)
7. Shop page shows products and Toman prices
8. Product codes: `/product/sku-<digits>/` alias URLs resolve to the right product (slug URLs stay canonical), missing codes return HTTP 404, and the Products admin shows the «کد کالا» column

## Theme Switching

To switch themes manually via database:

```bash
docker exec lylyrose-db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e \
  "UPDATE wp_options SET option_value=\"lylyrose\" WHERE option_name IN (\"template\",\"stylesheet\");"'
```

## License

GNU General Public License v2 or later