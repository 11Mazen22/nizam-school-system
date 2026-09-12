# 🤖 Nizam Automation Guide

This guide explains all the automated features in Nizam School System and how to set them up for cloud deployment (Railway + Supabase + GitHub Actions).

---

## 📋 Overview

Nizam includes **5 automated workflows**:

1. **Daily Birthday Notifications** - Alerts staff when students have birthdays
2. **Year Rollover Reminders** - Warns admins when academic year is ending
3. **Weekly Database Cleanup** - Removes old logs and expired data
4. **Automated Backups** - Daily database backups to cloud storage
5. **Report Generation Scheduling** - Email notifications when reports are ready

---

## 🌐 Cloud Deployment Architecture

### Why Cloud?

✅ **Multi-device access** - Teachers work from home, admin checks on phone  
✅ **Always online** - No VPN, port forwarding, or network issues  
✅ **Automatic backups** - GitHub Actions + Supabase Storage  
✅ **Professional SSL** - HTTPS by default  
✅ **Free tier available** - Railway + Supabase have generous free plans

### Components

| Component | Purpose | Free Tier |
|-----------|---------|-----------|
| **Railway** | PHP app hosting + PostgreSQL database | $5 credit/month |
| **Supabase** | File storage (photos, backups) | 1GB storage |
| **GitHub Actions** | Automated tasks (birthdays, cleanup, backups) | 2,000 minutes/month |

---

## ⚙️ Setup Instructions

### Step 1: Deploy to Railway + Supabase

1. **Fork this repository** to your GitHub account

2. **Create Railway project:**
   - Go to [railway.app](https://railway.app)
   - Sign in with GitHub
   - Create new project from GitHub repo
   - Add PostgreSQL database
   - Set environment variables:
     ```
     DATABASE_URL=<auto-provided-by-railway>
     ```

3. **Create Supabase project:**
   - Go to [supabase.com](https://supabase.com)
   - Create new project
   - Get credentials from Settings → API
   - Follow [CLOUD_STORAGE_SETUP.md](CLOUD_STORAGE_SETUP.md)

4. **Update config/config.php:**
   ```php
   return [
       'driver'    => 'pgsql',
       'host'      => 'aws-0-us-west-1.pooler.supabase.com',
       'port'      => 6543,
       'database'  => 'postgres',
       'username'  => 'postgres.xxxxx',
       'password'  => 'your-password',
       
       'email' => [
           'enabled'       => true,
           'from_email'    => 'nizam@yourschool.com',
           'from_name'     => 'Al-Nizam School',
       ],
       
       'scheduled_backup_token' => 'GENERATE_RANDOM_SECRET_HERE',
   ];
   ```

---

### Step 2: Configure GitHub Secrets

Go to your GitHub repo → Settings → Secrets and variables → Actions → New repository secret

Add these 3 secrets:

| Secret Name | Value | Example |
|-------------|-------|---------|
| `NIZAM_URL` | Your Railway app URL | `https://nizam-production.up.railway.app` |
| `NIZAM_SCHEDULED_BACKUP_TOKEN` | Random secret (32+ chars) | `8a7d9f2e1c5b3a6d...` |
| `SUPABASE_STORAGE_TOKEN` | From Supabase → Settings → API | `eyJhbGc...` |

**Generate random token:**
```bash
# Linux/Mac
openssl rand -hex 32

# Windows PowerShell
-join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | % {[char]$_})
```

---

### Step 3: Enable Email Notifications

#### Option A: PHP mail() (Simple, may not work on all hosts)
```php
'email' => [
    'enabled' => true,
    'from_email' => 'nizam@yourschool.com',
    'from_name' => 'Al-Nizam School',
]
```

#### Option B: SMTP (Recommended - use Gmail, SendGrid, Mailgun)
```php
'email' => [
    'enabled'       => true,
    'from_email'    => 'notifications@yourschool.com',
    'from_name'     => 'Al-Nizam School',
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 587,
    'smtp_user'     => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',  // NOT your Gmail password!
]
```

**Gmail App Password:** https://support.google.com/accounts/answer/185833

---

## 🔄 Automated Workflows

### 1. Daily Birthday Notifications
**File:** `.github/workflows/daily-birthdays.yml`  
**Schedule:** Every day at 8:00 AM UTC  
**What it does:**
- Checks for students with birthdays today
- Sends email to all admin users
- Includes student name, code, grade, and class

**Manual trigger:**
```bash
curl -X POST https://your-railway-url.railway.app/scheduled/birthdays \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 2. Year Rollover Reminders
**File:** `.github/workflows/daily-year-reminder.yml`  
**Schedule:** Every day at 9:00 AM UTC  
**What it does:**
- Checks days until active academic year ends
- Sends reminders at **60, 30, 14, and 7 days** before end
- Prompts admins to prepare promotions and next year

---

### 3. Weekly Database Cleanup
**File:** `.github/workflows/weekly-cleanup.yml`  
**Schedule:** Every Sunday at 3:00 AM UTC  
**What it does:**
- Deletes activity logs older than **90 days**
- Removes failed backup records older than **30 days**
- Clears expired login lockouts
- Auto-closes academic years older than **2 years** (if no active enrollments)

---

### 4. Automated Daily Backups
**File:** `.github/workflows/scheduled-backup.yml`  
**Schedule:** Every day at 2:00 AM UTC  
**What it does:**
- Creates full database backup
- Uploads to Supabase Storage
- Keeps last 30 backups
- Sends email on success/failure

---

### 5. Report Generation Notifications
**Built into app** - No GitHub Action needed  
**What it does:**
- When admin generates a report (PDF/Excel)
- Email sent to all admins with download link
- Includes report name, generated by, timestamp

---

## 🧪 Testing Automations

### Test Birthday Notifications
```bash
curl -X POST https://your-url.railway.app/scheduled/birthdays \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test Year Reminder
```bash
curl -X POST https://your-url.railway.app/scheduled/year-rollover \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test Database Cleanup
```bash
curl -X POST https://your-url.railway.app/scheduled/cleanup \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test Email Configuration
```bash
curl -X POST https://your-url.railway.app/scheduled/test-notification \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d "email=admin@yourschool.com"
```

---

## 📊 Monitoring

### Check GitHub Actions Status
1. Go to your GitHub repo
2. Click **Actions** tab
3. See all workflow runs and logs

### View Workflow Logs
- Click any workflow run
- Expand "Check birthdays and send notifications" step
- See JSON response: `{"success":true,"birthdays_found":3}`

### Check Email Delivery
- Add your email as admin in Users page
- Run test notification endpoint
- Check spam folder if not received

---

## ⚠️ Troubleshooting

### Workflows not running
- Check GitHub Actions are enabled: Settings → Actions → Allow all actions
- Verify secrets are set correctly (no extra spaces)
- Check workflow files are in `.github/workflows/` folder

### Email not sending
- Set `'enabled' => true` in config.php
- Test with `/scheduled/test-notification` endpoint
- Check Railway logs for errors
- Try SMTP instead of mail()

### Authentication errors (401 Unauthorized)
- Token in GitHub Secrets must match config.php `scheduled_backup_token`
- Token is case-sensitive
- No extra spaces or quotes

### Database connection errors
- Railway PostgreSQL credentials expire - regenerate if needed
- Check config.php host/port/username/password match Railway dashboard
- Use **transaction pooler** host (port 6543), not direct host (5432)

---

## 🔐 Security Notes

1. **Never commit config.php** - It's in .gitignore
2. **Use strong bearer token** - 32+ random characters
3. **Rotate tokens periodically** - Update both config.php and GitHub Secret
4. **Email credentials** - Use app passwords, never main passwords
5. **Admin emails only** - Notifications only sent to users with admin role

---

## 📱 Offline vs Cloud Comparison

| Feature | Offline (XAMPP) | Cloud (Railway) |
|---------|----------------|-----------------|
| Access | Single computer only | Any device, anywhere |
| Setup | Install XAMPP locally | Deploy once to cloud |
| Backups | Manual only | Automated daily |
| Emails | Requires local SMTP | Works out of box |
| Teachers at home | ❌ No access | ✅ Full access |
| Mobile access | ❌ No | ✅ Yes |
| Cost | Free | Free tier available |
| Internet required | No | Yes |

**Recommendation:** Use **Cloud** for schools with multiple staff members and remote access needs.

---

## 📞 Support

- **Documentation:** See [README.md](README.md) for full system documentation
- **Cloud Storage:** See [CLOUD_STORAGE_SETUP.md](CLOUD_STORAGE_SETUP.md)
- **Database:** See `database/schema.sql` for structure
- **Issues:** Open GitHub issue for bugs or questions

---

## ✅ Quick Checklist

- [ ] App deployed to Railway
- [ ] PostgreSQL database created
- [ ] Supabase Storage configured
- [ ] GitHub Secrets added (NIZAM_URL, NIZAM_SCHEDULED_BACKUP_TOKEN)
- [ ] config.php updated with database credentials
- [ ] config.php updated with email settings
- [ ] Tested with `/scheduled/test-notification`
- [ ] Verified GitHub Actions workflows are enabled
- [ ] Checked one workflow run manually (Actions tab → Run workflow)
- [ ] Added admin email address in Users page

---

**Last updated:** 2026-09-12  
**Automation Version:** 1.0
