<?php
class LoyaltyService {
    private static $db = null;
    
    private static function getDb() {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }
    
    /**
     * Update loyalty count when a user orders bread loaves
     * @param int $userId User ID
     * @param int $loafCount Number of loaves ordered
     * @return array Updated loyalty data
     */
    public static function updateLoyaltyCount($userId, $loafCount) {
        $db = self::getDb();
        
        // Get current loyalty data
        $loyalty = $db->fetchOne("SELECT * FROM user_loyalty WHERE user_id = ?", [$userId]);
        
        if (!$loyalty) {
            // Initialize new loyalty record
            $db->insert("INSERT INTO user_loyalty (user_id, total_loaves, free_loaves_earned) VALUES (?, ?, ?)", 
                [$userId, 0, 0]);
            $loyalty = ['total_loaves' => 0, 'free_loaves_earned' => 0];
        }
        
        $newTotal = $loyalty['total_loaves'] + $loafCount;
        $freeLoavesBefore = $loyalty['free_loaves_earned'];
        $freeLoavesAfter = intval($newTotal / 10);
        $newFreeLoaves = $freeLoavesAfter - $freeLoavesBefore;
        
        // Update loyalty record
        $db->execute("UPDATE user_loyalty SET total_loaves = ?, free_loaves_earned = ?, last_free_loaf_at = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?", 
            [$newTotal, $freeLoavesAfter, $newFreeLoaves > 0 ? date('Y-m-d H:i:s') : $loyalty['last_free_loaf_at'], $userId]);
        
        return [
            'total_loaves' => $newTotal,
            'free_loaves_earned' => $freeLoavesAfter,
            'new_free_loaves_earned' => $newFreeLoaves,
            'progress_to_next' => 10 - ($newTotal % 10)
        ];
    }
    
    /**
     * Get user loyalty data
     * @param int $userId User ID
     * @return array|null Loyalty data
     */
    public static function getUserLoyalty($userId) {
        $db = self::getDb();
        return $db->fetchOne("SELECT * FROM user_loyalty WHERE user_id = ?", [$userId]);
    }
    
    /**
     * Calculate loaf count from order items
     * @param array $orderItems Order items from database
     * @return int Total loaf count
     */
    public static function calculateLoafCount($orderItems) {
        $totalLoaves = 0;
        
        // This would need to be adapted based on your actual order items structure
        // For now, assuming each item represents one loaf
        foreach ($orderItems as $item) {
            // You might need to check product categories or specific product types
            // For example: if product is in 'breads' category
            $totalLoaves += $item['quantity'] ?? 1;
        }
        
        return $totalLoaves;
    }
}
?>
