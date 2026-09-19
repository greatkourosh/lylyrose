# LYLY ROSE — Functional Requirements

## 1. Homepage

Create a professional Persian RTL e-commerce homepage.

Sections should include:

1. Header
2. Main navigation
3. Search
4. Promotional/banner area
5. Featured categories
6. Popular brands
7. Best-selling products
8. New products
9. Special offers
10. Recommended products
11. Fragrance discovery section
12. Trust/features section
13. Newsletter section
14. Footer

Homepage sections should be modular so they can later be managed through WordPress.

---

# 2. Header

Desktop header should contain:

* LYLY ROSE logo
* search box
* account
* shopping cart
* wishlist
* navigation/categories

Mobile header should have:

* menu
* logo
* search
* cart

Search must be prominent.

---

# 3. Navigation

Create a category-oriented navigation.

Example:

عطر و ادکلن

* مردانه
* زنانه
* یونیسکس
* ادو پرفیوم
* ادو تویلت
* عطر روغنی
* بادی اسپلش
* ست هدیه
* سمپل

Brands

* برندها
* برندهای محبوب
* برندهای لوکس

Discovery

* بر اساس فصل
* بر اساس رایحه
* بر اساس موقعیت
* بر اساس ماندگاری
* بر اساس جنسیت

---

# 4. Product Catalog

Product listing page must support:

* grid/list presentation where appropriate
* pagination
* sorting
* filtering
* price filtering
* brand filtering
* gender filtering
* fragrance family filtering
* concentration filtering
* volume filtering
* availability
* rating
* sale status

Filters must work correctly on mobile.

---

# 5. Search

Implement a useful product search.

Search should consider:

* product name
* brand
* SKU
* relevant product attributes

Support Persian text.

Handle common Persian/Arabic character differences where practical, such as:

* ی / ي
* ک / ك

Search UI should provide useful results rather than a generic WordPress search page.

---

# 6. Product Page

The product page is one of the most important pages.

Include:

* product gallery
* main product image
* thumbnails
* product title
* brand
* rating
* review count
* price
* sale price
* stock status
* SKU
* product variations
* quantity
* add to cart
* wishlist
* share
* shipping information
* product description
* specifications
* fragrance pyramid
* notes
* fragrance family
* longevity
* sillage
* season
* occasion
* reviews
* related products
* similar products

---

# 7. Fragrance Pyramid

Create a visually attractive fragrance-notes section.

Example:

Top Notes

* Bergamot
* Lemon
* Apple

Heart Notes

* Rose
* Jasmine
* Lavender

Base Notes

* Amber
* Musk
* Vanilla
* Cedar

This should be represented as structured product data where possible.

---

# 8. Product Variations

Support variations such as:

* 30ml
* 50ml
* 75ml
* 100ml

Variation pricing must work through WooCommerce.

Stock should be managed independently when appropriate.

---

# 9. Product Brands

Create a proper brand taxonomy/system.

Each brand should have:

* name
* slug
* description
* logo/image
* brand page
* list of products

Brand pages should be indexable and SEO-friendly.

---

# 10. Categories

Use WooCommerce product categories.

Category pages should contain:

* category title
* description
* category image
* products
* filters
* sorting
* pagination

---

# 11. Wishlist

Implement wishlist functionality.

Prefer a lightweight and maintained solution.

The wishlist should work for:

* logged-in users
* guest users where practical

Do not build a huge custom wishlist system if a small reliable implementation is sufficient.

---

# 12. Product Comparison

Implement product comparison if practical.

Users should be able to compare perfume products based on:

* price
* brand
* gender
* concentration
* volume
* fragrance family
* longevity
* sillage
* notes

The comparison UI must remain usable on mobile.

---

# 13. Reviews

WooCommerce reviews should be enabled.

Support:

* rating
* text review
* verified purchase indicator
* review moderation

Display rating summary on product pages.

---

# 14. Customer Account

Use WooCommerce customer accounts.

Include:

* dashboard
* orders
* order details
* addresses
* account details
* password management
* wishlist if supported

---

# 15. Cart

Cart should include:

* products
* product image
* quantity
* unit price
* subtotal
* remove
* coupon
* shipping estimate
* total

Cart should be responsive.

Consider a mini-cart in the header.

---

# 16. Checkout

Create a clean Persian checkout.

Fields should include appropriate Iranian customer information.

At minimum:

* first name
* last name
* mobile
* province
* city
* address
* postal code
* optional notes

Do not add unnecessary fields.

Payment gateway can remain disabled during local development.

The architecture must allow an Iranian payment gateway to be added later without redesigning checkout.

---

# 17. Shipping

Prepare WooCommerce shipping architecture for:

* local delivery
* courier
* postal delivery
* free shipping thresholds
* province/city-based shipping later

Actual shipping integration does not need to be implemented yet.

---

# 18. Promotions

Support:

* sale prices
* coupons
* percentage discounts
* fixed discounts
* minimum order amount
* coupon expiration

Architecture should allow future campaigns.

---

# 19. Product Badges

Support visual badges such as:

* جدید
* پرفروش
* ویژه
* تخفیف
* محبوب
* ناموجود

Do not hard-code them per template.

---

# 20. SEO

Prepare the site for SEO.

Implement clean:

* URLs
* slugs
* titles
* headings
* canonical behavior
* product structured data through WooCommerce
* category structure
* brand pages

Do not create fake SEO content.

Use an established SEO plugin if needed, preferably a lightweight and well-maintained one.

---

# 21. Persian / RTL

Everything must work correctly in RTL.

Check:

* typography
* spacing
* icons
* product cards
* forms
* checkout
* dropdowns
* filters
* tables
* mobile navigation

Avoid CSS that assumes LTR positioning.

---

# 22. Responsive Design

The website must be designed mobile-first.

Test at least:

* mobile
* tablet
* desktop
* wide desktop

The store should remain usable at narrow widths.

---

# 23. Admin Experience

The WordPress admin should make it easy to manage:

* products
* categories
* brands
* attributes
* orders
* coupons
* customers
* reviews

Avoid forcing the store owner to edit code for normal product management.

---

# 24. Sample Data

Create a small set of realistic sample perfume products for development/testing.

Do not use copyrighted product images without appropriate rights.

Use placeholders or freely usable assets.

Sample products should demonstrate:

* simple product
* variable product
* sale product
* out-of-stock product
* product with fragrance notes
* product with reviews

---

# 25. Future Features

Design the architecture so these can be added later:

* Iranian payment gateway
* SMS notifications
* order tracking
* Persian calendar
* advanced search
* recommendation engine
* AI perfume recommendation
* customer loyalty
* wallet
* affiliate system
* multi-vendor marketplace
* price alerts
* inventory synchronization
* analytics
* CRM

Do NOT implement these now unless required for the core store.

The architecture should simply avoid making future implementation difficult.

---

# 26. Important Scope Rule

Build a solid MVP first.

Do not spend most of the implementation time on advanced features.

Priority:

1. Store foundation
2. Product catalog
3. Product page
4. Search/filter
5. Cart
6. Checkout
7. Customer account
8. Responsive Persian UI
9. SEO
10. Performance/security
11. Deployment readiness

Only after these work should optional features be added.
