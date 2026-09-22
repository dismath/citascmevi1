<?php
namespace App\Models;

class OrderItem extends BaseModel
{
    protected string $table = 'order_items';

    public function getByOrderId(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT oi.*, c.category as catalog_category 
             FROM order_items oi
             LEFT JOIN catalogs c ON oi.catalog_item_id = c.id 
             WHERE oi.order_id = ?",
            [$orderId]
        );
    }
}
