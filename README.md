# Raqtan TMS

A task management system for a single organisation: tasks on a configurable
workflow, grouped into projects, with sprints, reporting and an audit trail.

Laravel 12 + React 19 + Inertia v2 on MySQL. See [CLAUDE.md](CLAUDE.md) for the
architecture, the commands and the conventions this codebase holds itself to.

## Getting started

```
composer install && npm install
cp .env.example .env
php artisan key:generate
mysql -uroot -e "CREATE DATABASE project_management"
php artisan migrate --seed
composer dev
```

`composer dev` runs the server, the queue worker, the scheduler and Vite
together. Sign-in is two-step: credentials, then a 6-digit code that lands in
`storage/logs/laravel.log` while `MAIL_MAILER=log`.
