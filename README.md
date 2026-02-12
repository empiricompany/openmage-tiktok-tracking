# OpenMage TikTok Tracking Extension

OpenMage module for TikTok Pixel integration with automatic e-commerce event tracking.

## What It Does

This module automatically tracks e-commerce events on your OpenMage store and sends them to TikTok Pixel:

- **ViewContent**: Tracks when customers view product pages
- **AddToCart**: Tracks when customers add products to cart
- **InitiateCheckout**: Tracks when customers start the checkout process
- **Purchase**: Tracks completed orders

Optional Advanced Matching feature sends hashed customer email and phone data for improved tracking accuracy.

## Installation

### Via Composer

```bash
composer require empiricompany/openmage-tiktok-tracking
```

### Manual Installation

Copy the contents of `app/` folder to your OpenMage installation's `app/` folder:

```bash
cp -r app/* /path/to/openmage/app/
```

Then clear cache:

```bash
rm -rf /path/to/openmage/var/cache/*
```

## Configuration

1. Go to **Admin Panel → System → Configuration → Sales → TikTok Tracking**
2. Enable the module
3. Enter your TikTok Pixel ID (you can find it in TikTok Ads Manager)
4. Optionally enable Advanced Matching for better tracking accuracy

## Testing

Install the [TikTok Pixel Helper browser extension](https://chrome.google.com/webstore) to verify events are being tracked correctly.
