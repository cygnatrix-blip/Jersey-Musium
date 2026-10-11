<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Delivery lifecycle. Courier-agnostic: admin enters courier + AWB (Shiprocket, Delhivery,
 * Blue Dart, India Post...). Shipment events drive the order status automatically.
 * A courier aggregator API (e.g. Shiprocket) can later call addEvent() from a webhook.
 */
final class ShipmentService
{
    public const COURIERS = ['Shiprocket', 'Delhivery', 'Blue Dart', 'DTDC', 'Ecom Express', 'Xpressbees', 'India Post', 'Shadowfax', 'Other'];
    public const STATUSES = ['created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'rto', 'lost'];

    public static function forOrder(int $orderId): array
    {
        $shipments = db()->select('SELECT * FROM shipments WHERE order_id = :id ORDER BY id DESC', ['id' => $orderId]);
        foreach ($shipments as &$s) {
            $s['events'] = db()->select('SELECT * FROM shipment_events WHERE shipment_id = :id ORDER BY event_at DESC, id DESC', ['id' => $s['id']]);
        }
        return $shipments;
    }

    public static function create(array $order, string $courier, string $awb, ?string $trackingUrl, ?int $weight, ?int $adminId): int
    {
        if (!in_array($order['status'], ['confirmed', 'packed'], true)) {
            throw new RuntimeException('Only confirmed or packed orders can be shipped.');
        }
        if ($trackingUrl && !preg_match('#^https?://#', $trackingUrl)) {
            throw new RuntimeException('Tracking URL must start with http(s)://');
        }
        $id = db()->transaction(function () use ($order, $courier, $awb, $trackingUrl, $weight) {
            $id = db()->insert('shipments', [
                'order_id' => $order['id'], 'courier' => $courier, 'awb_number' => $awb,
                'tracking_url' => $trackingUrl ?: null, 'weight_grams' => $weight, 'status' => 'in_transit', 'shipped_at' => now(),
            ]);
            db()->insert('shipment_events', ['shipment_id' => $id, 'status' => 'shipped', 'note' => "Handed over to $courier", 'event_at' => now()]);
            // GST time of supply = removal of goods → the tax invoice is issued at dispatch.
            InvoiceService::assign((int) $order['id']);
            return $id;
        });
        $order = OrderService::find((int) $order['id']);
        OrderService::transition($order, 'shipped', "Shipped via $courier · AWB $awb", 'admin', $adminId, [
            'courier' => $courier, 'awb' => $awb, 'tracking_url' => $trackingUrl,
        ]);
        return $id;
    }

    public static function addEvent(int $shipmentId, string $status, ?string $location, ?string $note, ?int $adminId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Invalid shipment status.');
        }
        $s = db()->first('SELECT * FROM shipments WHERE id = :id', ['id' => $shipmentId]) ?? throw new RuntimeException('Shipment not found.');
        db()->insert('shipment_events', ['shipment_id' => $shipmentId, 'status' => $status, 'location' => $location, 'note' => $note, 'event_at' => now()]);
        db()->update('shipments', ['status' => $status] + ($status === 'delivered' ? ['delivered_at' => now()] : []), 'id = :id', ['id' => $shipmentId]);

        $order = OrderService::find((int) $s['order_id']);
        $map = ['out_for_delivery' => 'out_for_delivery', 'delivered' => 'delivered'];
        if (isset($map[$status]) && $order['status'] !== $map[$status]) {
            OrderService::transition($order, $map[$status], $note ?: 'Courier update: ' . label($status), 'admin', $adminId, ['tracking_url' => $s['tracking_url']]);
        }
        if ($status === 'rto') {
            OrderService::log((int) $order['id'], $order['status'], $order['status'], 'Shipment returning to origin (RTO). Cancel the order once received.', 'admin', $adminId);
        }
    }
}
