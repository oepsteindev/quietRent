<?php

namespace QuietRent\Services;

use QuietRent\Core\DB;
use QuietRent\Models\{Property, Unit, Tenant};

class CsvImporter
{
    /**
     * Import units+tenants from CSV.
     * Expected columns: property_name, unit_label, monthly_rent, due_day,
     *                   tenant_name, tenant_email, tenant_phone
     */
    public static function import(int $accountId, string $filePath): array
    {
        $errors  = [];
        $created = ['properties' => 0, 'units' => 0, 'tenants' => 0];
        $seen    = [];

        if (!file_exists($filePath)) {
            return ['errors' => ['File not found'], 'created' => $created];
        }

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);
        $header = array_map('trim', $header);

        $required = ['property_name', 'unit_label', 'monthly_rent', 'tenant_name', 'tenant_email'];
        foreach ($required as $col) {
            if (!in_array($col, $header, true)) {
                fclose($handle);
                return ['errors' => ["Missing required column: $col"], 'created' => $created];
            }
        }

        $row = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            $record = array_combine($header, $data);

            // Basic validation
            if (empty($record['tenant_email']) || !filter_var($record['tenant_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row $row: invalid email '{$record['tenant_email']}'";
                continue;
            }
            if (!is_numeric($record['monthly_rent']) || $record['monthly_rent'] <= 0) {
                $errors[] = "Row $row: invalid rent '{$record['monthly_rent']}'";
                continue;
            }

            // Upsert property
            $propKey = strtolower(trim($record['property_name']));
            if (!isset($seen['properties'][$propKey])) {
                $existing = DB::fetchOne(
                    'SELECT id FROM properties WHERE account_id=? AND LOWER(name)=?',
                    [$accountId, $propKey]
                );
                if ($existing) {
                    $seen['properties'][$propKey] = $existing['id'];
                } else {
                    $id = Property::create($accountId, [
                        'name'          => trim($record['property_name']),
                        'address_line1' => $record['address_line1'] ?? 'Unknown',
                        'city'          => $record['city'] ?? '',
                        'state'         => $record['state'] ?? '',
                        'zip'           => $record['zip'] ?? '',
                    ]);
                    $seen['properties'][$propKey] = $id;
                    $created['properties']++;
                }
            }
            $propId = $seen['properties'][$propKey];

            // Upsert unit
            $unitKey = $propId . ':' . strtolower(trim($record['unit_label']));
            if (!isset($seen['units'][$unitKey])) {
                $existing = DB::fetchOne(
                    'SELECT id FROM units WHERE property_id=? AND LOWER(unit_label)=?',
                    [$propId, strtolower(trim($record['unit_label']))]
                );
                if ($existing) {
                    $seen['units'][$unitKey] = $existing['id'];
                } else {
                    $id = Unit::create($propId, [
                        'unit_label'   => trim($record['unit_label']),
                        'monthly_rent' => (float) $record['monthly_rent'],
                        'due_day'      => (int) ($record['due_day'] ?? 1),
                    ]);
                    $seen['units'][$unitKey] = $id;
                    $created['units']++;
                }
            }
            $unitId = $seen['units'][$unitKey];

            // Create tenant (skip if email already exists in this account)
            $existingTenant = DB::fetchOne(
                'SELECT id FROM tenants WHERE account_id=? AND email=?',
                [$accountId, trim($record['tenant_email'])]
            );
            if (!$existingTenant) {
                Tenant::create($accountId, $unitId, [
                    'full_name'         => trim($record['tenant_name']),
                    'email'             => trim($record['tenant_email']),
                    'phone'             => $record['tenant_phone'] ?? null,
                    'preferred_channel' => $record['preferred_channel'] ?? 'email',
                ]);
                $created['tenants']++;
            } else {
                $errors[] = "Row $row: tenant '{$record['tenant_email']}' already exists, skipped";
            }
        }

        fclose($handle);

        return ['errors' => $errors, 'created' => $created];
    }
}
