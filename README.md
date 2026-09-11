

# Rouh Perfume — Premium Syrian E-commerce & ERP Platform

 
A modern, bilingual (Arabic/English) e-commerce and ERP platform for premium
Syrian perfumes. Built with React 18 + TypeScript + Vite on the frontend and
Laravel + PHP 8.5 + MySQL/MariaDB on the backend, it supports a made-to-order
manufacturing workflow, full inventory & financial accounting, loyalty
rewards, fragrance-note comparisons, and a comprehensive admin dashboard.

---

## 🏗️ Project Structure

```
rouh-perfume/
├── src/                     # React frontend source
│   ├── components/          # Reusable UI components (admin & storefront)
│   ├── contexts/            # React contexts (state management)
│   ├── hooks/               # Custom React hooks (useProducts, useCart, …)
│   ├── lib/                 # Utility functions & API client
│   ├── pages/               # Page components (storefront + admin)
│   ├── store/               # Redux Toolkit slices
│   └── main.tsx             # Application entry point
├── public/                  # Static assets
├── backend/                 # Laravel backend API
│   ├── app/
│   │   ├── Http/Controllers/     # Controllers (Admin & storefront)
│   │   ├── Models/               # Eloquent models
│   │   ├── Services/             # Business-logic services
│   │   └── Notifications/        # Email notifications
│   ├── config/               # Configuration files
│   ├── database/
│   │   ├── migrations/       # Database migrations
│   │   └── seeders/          # Database seeders
│   ├── routes/api.php        # API routes
│   └── storage/              # File storage
├── index.html               # HTML template
├── vite.config.ts           # Vite bundler configuration
├── tailwind.config.ts       # Tailwind CSS configuration
├── tsconfig.*.json          # TypeScript configurations
├── package.json             # Frontend dependencies
├── backend/database/migrations/   # Database schema history (source of truth)
└── README.md                # This file
```

---

## 🚀 Features

### Core E-commerce
- **Product Management**: Full CRUD for products with multiple sizes, prices,
  bottle shapes, and variant-level recipes
- **Category System**: Organized product categories
- **Shopping Cart**: Persistent cart with quantity management
- **Checkout Process**: Complete order flow with shipping calculation
- **Order Management**: Admin order tracking and status updates
- **Payment**: Cash on delivery with secure processing

### Manufacturing & Inventory (Made-to-Order)
- **Recipe System**: Each product variant has a recipe
  (`Product → ProductVariant → Recipe → RecipeItem → Material`)
- **Oil & Alcohol Percentages**: Recipes store an oil percentage (default 32%)
  with alcohol automatically calculated as 100% − oil%. Values are displayed
  in millilitres (ml), not litres.
- **Material Substitution Log**: When a material is substituted during order
  preparation, a full audit trail is written to the `material_substitutions`
  table (original material, replacement, user, timestamp, order reference).
- **Fixed Assets vs Inventory**: Operational equipment is tracked as fixed
  assets (`fixed_assets` table), not as consumable inventory. The inventory
  API automatically excludes any legacy `equipment` subcategory from
  material listings.
- **Consumption Rules**: Recipe items support `fixed`, `per_bottle`, and
  `percentage` consumption rule types. Percentage rules multiply by bottle
  size in ml.

### Advanced Features
- **Fragrance Notes Pyramid**: Each perfume has top, heart, and base notes
  (stored as JSON arrays) plus an accurate fragrance-family classification.
  The product details page displays the full note pyramid with colour-coded
  badges.
- **Perfume Comparison with Shared Notes**: The comparison page computes the
  intersection of top/heart/base notes across all compared perfumes,
  highlights shared notes in green, and shows a similarity-score percentage
  bar.
- **Bottle Shapes**: Each product variant can specify a bottle shape, shown
  in the variant manager and product cards.
- **Loyalty Points System**: Points are now correctly calculated and awarded
  when an order is prepared or paid. An idempotency guard
  (`loyalty_awarded_at` timestamp) prevents double-awarding.
- **Invoice with Company Logo**: Printable invoices include the company logo.
- **Advanced Search**: Real-time search with auto-complete and recent searches
- **Product Bundles**: Volume discounts and special offers
- **Social Sharing**: Native social media integration
- **WhatsApp Notifications**: Admin-to-customer status updates via WhatsApp

### User Experience
- **Bilingual Interface**: Full Arabic/English support with RTL
- **Responsive Design**: Mobile-first approach with Tailwind CSS
- **Admin Dashboard**: Comprehensive admin panel with analytics
- **Customer Reviews**: Product review and rating system
- **Coupon System**: Discount codes and promotions
- **Order Tracking**: Real-time order status tracking

---

## 🛠️ Tech Stack

### Frontend
- **React 18** with hooks
- **TypeScript** for type safety
- **Vite** for fast builds & dev server
- **Tailwind CSS** + **shadcn/ui** (Radix UI)
- **Redux Toolkit** + **TanStack Query**
- **Framer Motion** for animation
- **React Router** for routing

### Backend
- **Laravel 13** (PHP 8.4+)
- **MySQL / MariaDB** (production database)
- Custom token-based authentication + RBAC
- **RESTful API**

---

## 📋 Prerequisites

- **Node.js** >= 18.x
- **PHP** >= 8.3
- **Composer** (for Laravel)
- **npm** (bundled with Node.js)

---

## 🔧 Installation

The release archive intentionally does not include local setup/preflight shell or PowerShell helpers. Use the manual setup below so production data and accounting checks remain explicit and controlled.

### Manual setup

#### 1. Install Frontend Dependencies
```bash
npm install
```

#### 2. Install Backend Dependencies
```bash
cd backend
composer install
```

#### 3. Environment Setup
```bash
# Frontend env
cp .env.example .env

# Backend env
cd backend
cp .env.example .env
php artisan key:generate
```

#### 4. Database Setup
```bash
cd backend
php artisan migrate
php artisan db:seed
```

The seeders populate:
- Admin user, categories, financial core accounts
- ~95 perfume materials, 1 alcohol material, 13 packaging materials
- ~95 products with variants, prices, and recipes
- ~96 perfumes with real fragrance-note pyramids
- Fixed assets (operational equipment)

---

## ▶️ Running the Application

### Start Backend Server
```bash
cd backend
php artisan serve
```
Backend runs on: `http://127.0.0.1:8000`

### Start Frontend Dev Server
```bash
npm run dev
```
Frontend runs on: `http://localhost:5174` (or the port Vite reports)

---

## 🔑 Admin Access

Production credentials are supplied only through `backend/.env`; no passwords are committed to the repository.

## 📦 Building for Production

### Frontend Build
```bash
npm run build
```
The production frontend build is written to `dist/`. Preview it locally with `npm run preview`.

### Backend Optimization
```bash
cd backend
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🗃️ Database

### Migrations
```bash
cd backend
php artisan migrate
```

### Seeders
`db:seed` is for local/demo initialization only and is blocked in Production. Opening balances are intentionally not auto-created.

### Final Go-Live Reset
Do not use `migrate:fresh` on a database that contains real production data. For the controlled test-to-go-live cleanup, finish all testing first, take a verified backup, then use:

```bash
cd backend
php artisan rouh:prepare-go-live-reset --force
```

Add `--customers` only when the customer master and customer-facing test history should also be removed. The command preserves products, variants, recipes, materials, chart of accounts, permissions, and store/tax configuration.

---

## 🌐 Environment Variables

### Frontend (.env)
```env
# Leave empty for local development; Vite proxies /api to Laravel on port 8000.
VITE_API_URL=
```

For a separate production frontend, set `VITE_API_URL` to the public Laravel API base URL. During local development you can set `VITE_DEV_API_PROXY_TARGET` when Laravel is served by XAMPP/Apache instead of `php artisan serve`.

### Backend (backend/.env)
```env
APP_NAME=ROUH ERP
APP_ENV=local
APP_KEY=your-generated-key
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rouh_erp
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
```

For production, use a dedicated database user and a strong password supplied only through the server environment. Do not commit `.env` files or database dumps.

---

## 🧪 New Features in This Release

| # | Feature | Description |
|---|---------|-------------|
| 1 | **Substitution Log** | Full audit trail when materials are substituted during order preparation |
| 2 | **Oil Percentage & Alcohol in ml** | Recipes default to 32% oil; alcohol = 100% − oil%; displayed in millilitres |
| 3 | **Loyalty Points Fix** | Points are now correctly calculated & awarded on order preparation/payment (idempotent) |
| 4 | **Invoice Logo** | Printable invoices include the company logo |
| 5 | **Fixed Assets Separation** | Operational equipment moved to fixed assets; excluded from inventory listings |
| 6 | **Fragrance Notes & Comparison** | Real top/heart/base notes per perfume, shared-notes comparison, accurate fragrance-family classification, bottle shapes |

See the production checklists and deployment guide in this repository for the supported release procedure.

---

## 🔐 Security

- Token-based API authentication with RBAC
- Input validation and sanitization
- SQL injection protection (Eloquent parameter binding)
- XSS protection
- CSRF protection
- Rate limiting

---

## 🎨 Design System

### Colors
- **Primary**: Gold (#b8860b)
- **Secondary**: Deep blue (#1e3a5f)
- **Accent**: Emerald green
- **Background**: White / Off-white
- **Text**: Dark gray (#1f2937)

### Typography
- **Headings**: Arabic: Tajawal, English: Poppins
- **Body**: Arabic: Tajawal, English: Inter
- **RTL Support**: Full bidirectional text support

---

## 📱 Responsive Breakpoints
- **Mobile**: < 640px
- **Tablet**: 640px – 1024px
- **Desktop**: > 1024px

---

## 📧 Support

For support, email: contact@rouh.shop

---

## 📄 License

This project is proprietary software. All rights reserved.

---

**Built with ❤️ for the Syrian market**

## Product images and descriptions
Each product uses its explicitly assigned image URL/path, with `public/placeholder.svg` as the safe fallback when no image is configured. The application no longer performs automatic third-party image searching or remote image downloading.

## Staging deployment

For a low-cost staging setup, deploy the Vite frontend to Vercel and deploy Laravel as a separate backend service. Set `VITE_API_URL` in the frontend environment to the backend base URL. Do not commit `.env`, local SQLite databases, `node_modules`, Laravel `vendor`, or build/test artifacts.

## Release notes

The production release is API-only on Laravel; the frontend is built separately with Vite. The production deployment package should exclude dependency directories, local environment files containing secrets, logs, and generated build output. This testing package intentionally retains its local `.env` files only because they are needed for the current testing phase; do not ship those files to production. Historical Laravel migrations remain because they are part of the supported database upgrade path.

## Configurability and product variants


Each active product variant/size can have its own image through `product_variants.image_url`. If an image is only illustrative, mark the variant as a reference image; the storefront then shows a clear customer-facing disclaimer. When a variant has no image, the storefront falls back to the main product image.

## Variant images and storefront behavior

- Each product size/variant can have its own uploaded image from the Admin → Products → Variants screen.
- Supported formats: JPG/JPEG, PNG, WebP; maximum 5 MB.
- Run `php artisan storage:link` once per environment so uploaded images are publicly reachable.
- The storefront changes the product image when the customer selects a size.
- Product cards on the home/shop pages do not expose a price or add-to-cart action until a size is selected on the product page.
- The illustrative-image notice appears only below the product image on the customer product page.

## Opening balance date

The opening balance screen is the sole source of the accounting start date. The date is persisted with the opening balance and used by financial reporting.

## September 2026 UI update

The storefront requires size selection before exposing the price or add-to-cart flow. Product-card price/cart controls are intentionally omitted until the customer reaches the product page and chooses a variant. Variant images are uploaded from the admin screen and automatically switch when the customer chooses a size.

Search is live while typing on the home page, shop page, and navbar search.

The opening-balance screen defaults to the configured accounting start date but permits a later date; the backend enforces the same rule when the entry is posted.
#
