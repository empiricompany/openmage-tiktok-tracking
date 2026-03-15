# OpenMage TikTok Tracking Extension
[![Latest Version on Packagist](https://img.shields.io/packagist/v/empiricompany/openmage-tiktok-tracking.svg?style=flat-square)](https://packagist.org/packages/empiricompany/openmage-tiktok-tracking)
[![Total Downloads](https://img.shields.io/packagist/dt/empiricompany/openmage-tiktok-tracking.svg?style=flat-square)](https://packagist.org/packages/empiricompany/openmage-tiktok-tracking)

OpenMage module for TikTok Pixel integration with automatic e-commerce event tracking.

> **Platform compatibility**: this module is compatible with both [OpenMage](https://www.openmage.org/) and [MahoCommerce](https://mahocommerce.com/).

## What It Does

This module automatically tracks e-commerce events on your OpenMage store and sends them to TikTok Pixel:

- **ViewContent**: Tracks when customers view product pages
- **AddToCart**: Tracks when customers add products to cart
- **InitiateCheckout**: Tracks when customers start the checkout process
- **Purchase**: Tracks completed orders

Optional Advanced Matching feature sends hashed customer email data for improved tracking accuracy.

## Installation

### Via Composer

```bash
composer require empiricompany/openmage-tiktok-tracking
```

### Via Modman

```bash
modman clone https://github.com/empiricompany/openmage-tiktok-tracking.git
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

1. Go to **Admin Panel → System → Configuration → TikTok → Tracking**
2. Enable the module
3. Enter your TikTok Pixel ID (you can find it in TikTok Ads Manager)
4. Optionally enable Advanced Matching for better tracking accuracy
5. Optionally enable Debug Mode to log tracking data

## Testing

Install the [TikTok Pixel Helper browser extension](https://chromewebstore.google.com/detail/aelgobmabdmlfmiblddjfnjodalhidnn?utm_source=item-share-cb) to verify events are being tracked correctly.
