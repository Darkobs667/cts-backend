# Gemini CLI Project Context: BackendCTS

This project is the backend for **CTS (Candidats et Scrutins)**, an electronic voting system built with Laravel 13 and PHP 8.3. It provides a RESTful API for managing elections, candidates, and secure voting processes.

## 🏗 Architecture & Technologies

- **Framework:** Laravel 13.x
- **Language:** PHP 8.3+
- **Authentication:** JWT (via `php-open-source-saver/jwt-auth`) and Laravel Sanctum.
- **Database:** Eloquent ORM with migrations for `users`, `positions` (elections), `candidates`, and `votes`.
- **Business Logic:** Organized into **Services** (e.g., `VoteService`, `CandidatService`) to keep controllers lean.
- **Caching:** Custom `Cacheable` trait used in controllers to optimize heavy queries like results and stats.
- **PDF Generation:** `barryvdh/laravel-dompdf` for generating voting receipts and global results.
- **Middleware:** Role-based access control (RBAC) via `CheckAdmin` middleware.

## 📂 Key Directory Structure

- `app/Http/Controllers/Api/`: Main API controllers.
- `app/Services/`: Business logic layer.
- `app/Models/`: Eloquent models with relationships.
- `app/Traits/Cacheable.php`: Helper for uniform caching across the app.
- `routes/api.php`: All API endpoints, grouped by public/protected access.
- `resources/views/pdf/`: Blade templates for PDF generation.
- `database/migrations/`: Database schema definitions.

## 🚀 Key Commands

### Development
- `composer install`: Install PHP dependencies.
- `npm install && npm run build`: Install JS dependencies and build assets (Vite).
- `php artisan serve`: Start the development server.
- `php artisan migrate`: Run database migrations.
- `php artisan db:seed`: Seed the database (if applicable).
- `php artisan boost:install`: Laravel Boost tools for AI agent assistance.

### Custom Scripts (from composer.json)
- `composer setup`: Complete environment setup (install, env, key, migrate, npm).
- `composer dev`: Runs server, queue, logs, and vite concurrently.
- `composer test`: Clears config and runs tests.

### Testing
- `php artisan test`: Run the test suite.

## 🛠 Development Conventions

- **API Versioning:** Currently flat under `/api/`, but follows RESTful principles.
- **Response Format:** Uniform JSON responses for success and errors.
- **Security:** 
    - Votes are tracked using a `hash_session` (email based) to prevent double voting.
    - Admin-only routes are protected by the `admin` middleware.
- **Performance:** High-traffic endpoints (results, stats) utilize caching with a default TTL (e.g., 2 minutes for results).

## 📝 Usage Notes

- **Authentication:** Most write operations require a JWT token in the `Authorization: Bearer <token>` header.
- **Render Deployment:** Includes a `/keep-alive` route to prevent instance sleeping on Render.com.
- **CORS/Storage:** Public routes exist for debugging storage links and database connectivity.
