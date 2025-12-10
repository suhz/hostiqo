# ⚡ Quick Start Guide

Get your Git Webhook Manager up and running in 5 minutes!

> **🚀 Production Setup?** For automated Ubuntu/Debian server deployment with all dependencies (Nginx, PHP 7.4-8.4, MySQL, Redis, Supervisor, etc), see [scripts/README.md](scripts/README.md) - complete setup in ~25-35 minutes!

## 📦 Local Development Installation (5 Steps)

### Step 1: Install Dependencies
```bash
composer install
npm install
```

### Step 2: Setup Environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure your database:
```env
DB_CONNECTION=mysql
DB_DATABASE=webhook_db
DB_USERNAME=root
DB_PASSWORD=your_password

QUEUE_CONNECTION=database
```

### Step 3: Run Migrations
```bash
php artisan migrate
```

### Step 3.5: Seed Default Users
```bash
php artisan db:seed --class=UserSeeder
```

**Default Login:**
- Email: `admin@gitwebhook.local`
- Password: `password`

### Step 4: Build Assets
```bash
npm run build
```

### Step 5: Start Services
```bash
# Terminal 1 - Web Server
php artisan serve

# Terminal 2 - Queue Worker (Required!)
php artisan queue:work
```

Visit: **http://localhost:8000**

---

## 🚀 Create Your First Webhook (3 Minutes)

### 1. Create Webhook
1. Click **"Create Webhook"**
2. Fill in details:
   - **Name:** My Website
   - **Git Provider:** GitHub
   - **Repository URL:** `git@github.com:username/repo.git`
   - **Branch:** `main`
   - **Local Path:** `/var/www/html/myproject`
   - ✅ Check **"Auto-generate SSH Key Pair"**
3. Click **"Create Webhook"**

### 2. Add SSH Deploy Key to GitHub
1. Copy the **Public SSH Key** from webhook details
2. Go to GitHub: Repository → Settings → Deploy keys
3. Add the public key
4. **Don't** check "Allow write access"

### 3. Configure GitHub Webhook
1. Copy the **Webhook URL** from webhook details
2. Copy the **Secret Token**
3. Go to GitHub: Repository → Settings → Webhooks → Add webhook
4. Paste:
   - **Payload URL:** [Your webhook URL]
   - **Content type:** `application/json`
   - **Secret:** [Your secret token]
5. Select: **Just the push event**
6. Click **"Add webhook"**

### 4. Test It!
- Push to your repository
- Watch deployment happen automatically!
- Or click **"Deploy Now"** for manual deployment

---

## 📋 Example Post-Deploy Script

For Laravel projects:
```bash
#!/bin/bash
composer install --no-dev
php artisan migrate --force
php artisan config:cache
npm install
npm run build
```

---

## ❗ Important Notes

1. **Queue Worker Must Run** - Deployments won't work without it!
   ```bash
   php artisan queue:work
   ```

2. **Directory Permissions** - Ensure deployment path is writable:
   ```bash
   sudo chown -R $USER:www-data /var/www/html/myproject
   ```

3. **SSH Access** - If using private repos, SSH keys are required

4. **First Clone** - First deployment will clone the repository

---

## 🐛 Quick Troubleshooting

**Deployment stuck on "pending"?**
→ Queue worker not running! Start it with `php artisan queue:work`

**Permission denied error?**
→ Add SSH key to GitHub/GitLab deploy keys

**Can't write to directory?**
→ Check folder permissions: `ls -la /path/to/directory`

---

## 📚 Need More Help?

- Read the full [README.md](README.md)
- Check [Troubleshooting section](README.md#troubleshooting)
- Review deployment logs in the UI

**Happy Deploying! 🎉**
