# Deploy Option 1 (Public Link) - Railway

This deployment gives you a real public URL so module sharing works on any phone/PC.

## 1) Push project to GitHub

Make sure your latest code is on a GitHub repository.

## 2) Create a Railway project

1. Go to Railway dashboard.
2. Click **New Project** -> **Deploy from GitHub repo**.
3. Select this repository.

## 3) Add MySQL service

1. In the same Railway project: **New** -> **Database** -> **MySQL**.
2. Railway creates DB credentials automatically.

## 4) Configure environment variables (Web service)

Set at least:

- `APP_ENV=prod`
- `APP_DEBUG=0`
- `APP_SECRET=<your-secret>`
- `DATABASE_URL=mysql://<user>:<password>@<host>:<port>/<db>?serverVersion=8.0`

Then, after first deploy URL exists, set:

- `APP_PUBLIC_URL=https://<your-railway-domain>`

## 5) Build/Start commands

Railway will use `Procfile` (`php -S ... -t public`) added in this project.

## 6) One-time setup on production

Run in Railway shell/console:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
```

## 7) Validate sharing

Open:

- `https://<your-railway-domain>/blog/module/3`

Use the green share button and send to phone contact.  
Now the link/content is public and no longer `127.0.0.1`.

