# Docker & Docker Compose Deployment

For containerized cloud environments (AWS, GCP, DigitalOcean, local servers), Zoom Pool Manager includes a production-ready `docker-compose.yml` stack.

---

## 📦 Architecture Stack

The Docker Compose configuration provisions:
- **`zpm-app`**: PHP 8.3 FPM container running the application core.
- **`zpm-web`**: Nginx web server configured with security headers and reverse proxy.
- **`zpm-db`**: MariaDB 10.11 database with persistent volume storage.

---

## 🚀 1. Clone & Start Containers

```bash
# Clone the repository
git clone https://github.com/senthilnasa/zoom-pool-manager.git
cd zoom-pool-manager

# Copy environment template
cp .env.example .env

# Start containers in background
docker compose up -d
```

---

## ⚡ 2. Initialize Container Dependencies

Run the following commands inside the app container:

```bash
# Generate application encryption key
docker exec zpm-app php artisan key:generate

# Run database schema migrations
docker exec zpm-app php artisan migrate --force

# Seed canonical roles and initial templates
docker exec zpm-app php artisan db:seed --force
```

---

## 🌐 3. Access the Application

Open your browser and navigate to:
```text
http://localhost:8088
```

You can now log in or complete the administrator setup.

---

## 🛠️ Useful Docker Commands

```bash
# View live application logs
docker compose logs -f zpm-app

# Run artisan command inside container
docker exec zpm-app php artisan zpm:version

# Run test suite
docker exec zpm-app ./vendor/bin/pest

# Stop containers
docker compose down
```
