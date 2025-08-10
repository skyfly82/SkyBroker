#!/usr/bin/env bash
set -euo pipefail

# Zakładam, że jesteś w root projektu (jest plik artisan)
php artisan make:migration create_shipments_table
php artisan make:migration create_payments_table
php artisan make:migration create_shipment_labels_table
php artisan make:migration create_tracking_events_table
php artisan make:migration create_carrier_accounts_table
php artisan make:migration create_manifests_table
php artisan make:migration create_virtual_accounts_table
php artisan make:migration create_cod_payments_table
php artisan make:migration create_webhook_deliveries_table
