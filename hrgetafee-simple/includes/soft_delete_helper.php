<?php
/**
 * Soft Delete Helper Functions
 * Place at: hrgetafee-simple/includes/soft_delete_helper.php
 */

/**
 * Soft delete an item and log it to recycle bin
 */
function softDeleteItem($conn, $item_type, $item_id, $item_name, $deleted_data, $user_id) {
    try {
        // Prepare JSON snapshot of deleted data
        $deleted_data_json = json_encode($deleted_data);
        
        // Log to recycle bin
        $stmt = $conn->prepare("INSERT INTO recycle_bin (item_type, item_id, item_name, deleted_by, deleted_data) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            return array('success' => false, 'error' => 'Database error: ' . $conn->error);
        }
        
        $stmt->bind_param('sisis', $item_type, $item_id, $item_name, $user_id, $deleted_data_json);
        
        if (!$stmt->execute()) {
            $stmt->close();
            return array('success' => false, 'error' => 'Error logging to recycle bin');
        }
        $stmt->close();
        
        // Update the original table with soft delete flags
        $table_map = array(
            'role' => 'roles',
            'department' => 'departments',
            'holiday' => 'holidays',
            'leave_type' => 'leave_types',
            'permission' => 'role_permissions'
        );
        
        if (!isset($table_map[$item_type])) {
            return array('success' => false, 'error' => 'Unknown item type');
        }
        
        $table = $table_map[$item_type];
        $id_column = $item_type . '_id';
        
        $update_stmt = $conn->prepare("UPDATE $table SET is_deleted = TRUE, deleted_at = NOW(), deleted_by = ? WHERE $id_column = ?");
        if (!$update_stmt) {
            return array('success' => false, 'error' => 'Database error during update');
        }
        
        $update_stmt->bind_param('ii', $user_id, $item_id);
        
        if (!$update_stmt->execute()) {
            $update_stmt->close();
            return array('success' => false, 'error' => 'Error updating table');
        }
        $update_stmt->close();
        
        return array('success' => true, 'message' => 'Item moved to recycle bin');
    } catch (Exception $e) {
        return array('success' => false, 'error' => $e->getMessage());
    }
}

/**
 * Get item from table (only active items)
 */
function getActiveItem($conn, $item_type, $item_id) {
    $table_map = array(
        'role' => array('table' => 'roles', 'id_col' => 'role_id'),
        'department' => array('table' => 'departments', 'id_col' => 'dept_id'),
        'holiday' => array('table' => 'holidays', 'id_col' => 'holiday_id'),
        'leave_type' => array('table' => 'leave_types', 'id_col' => 'leave_type_id'),
        'permission' => array('table' => 'role_permissions', 'id_col' => 'permission_id')
    );
    
    if (!isset($table_map[$item_type])) return null;
    
    $config = $table_map[$item_type];
    $table = $config['table'];
    $id_col = $config['id_col'];
    
    $stmt = $conn->prepare("SELECT * FROM $table WHERE $id_col = ? AND is_deleted = FALSE LIMIT 1");
    if (!$stmt) return null;
    
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row;
}

/**
 * Get all active items of a type
 */
function getActiveItems($conn, $item_type, $order_by = 'id') {
    $table_map = array(
        'role' => array('table' => 'roles', 'id_col' => 'role_id'),
        'department' => array('table' => 'departments', 'id_col' => 'dept_id'),
        'holiday' => array('table' => 'holidays', 'id_col' => 'holiday_id'),
        'leave_type' => array('table' => 'leave_types', 'id_col' => 'leave_type_id'),
        'permission' => array('table' => 'role_permissions', 'id_col' => 'permission_id')
    );
    
    if (!isset($table_map[$item_type])) return array();
    
    $config = $table_map[$item_type];
    $table = $config['table'];
    
    $stmt = $conn->prepare("SELECT * FROM $table WHERE is_deleted = FALSE ORDER BY $order_by");
    if (!$stmt) return array();
    
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();
    $stmt->close();
    
    return $items;
}

/**
 * Get recycle bin items count
 */
function getRecycleBinCount($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM recycle_bin WHERE restored = FALSE AND permanent_delete = FALSE");
    if (!$stmt) return 0;
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return intval($row['count'] ?? 0);
}
?>
