# JO Shop

A PHP 8 + SQLite single-page shop with admin product management.

## Features

- Single-page storefront
- Product cards as sale posts
- Featured products
- Category and search filters
- Product detail modal
- Shopping cart using localStorage
- Admin login at `/login.php`
- Protected admin dashboard at `/admin.php`
- Create / update / delete products
- SQLite auto-schema on first run
- Image upload support
- CSRF protection
- Audit log

## Admin Credentials

- Email: `admin@shop.com`
- Password: `p4ssw0rd`

## Structure

```text
/public
  index.php
  admin.php
  login.php
  logout.php

/app
  /Controllers
    ShopController.php
    AdminController.php
    AuthController.php
  /Helpers
    ProductHelper.php
    AuthHelper.php
    UploadHelper.php
  /Views
    layout.php
    home.php
    admin_dashboard.php
    admin_login.php

/storage
  /images
  /logs
  shop.sqlite