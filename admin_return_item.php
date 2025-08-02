<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $borrowingId = (int)$_POST['borrowing_id'];
    $returnCondition = sanitizeInput($_POST['return_condition']);
    
    if (!empty($returnCondition)) {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Check if borrowing exists and is not already returned
        $stmt = $pdo->prepare("
            SELECT b.*, i.name as item_name, i.id as item_id 
            FROM borrowings b 
            JOIN items i ON b.item_id = i.id 
            WHERE b.id = ? AND b.date_returned IS NULL
        ");
        $stmt->execute([$borrowingId]);
        $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($borrowing) {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Update borrowing record
                $sql = "UPDATE borrowings SET date_returned = NOW(), return_condition = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$returnCondition, $borrowingId]);
                
                // Increase item quantity
                $sql = "UPDATE items SET quantity = quantity + ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$borrowing['quantity_borrowed'], $borrowing['item_id']]);
                
                // Log activity
                logActivity($pdo, $_SESSION['user_id'], $borrowing['item_id'], 'Admin marked item as returned', "Condition: $returnCondition");
                
                $pdo->commit();
                setFlashMessage('success', 'Item marked as returned successfully!');
            } catch (Exception $e) {
                $pdo->rollback();
                setFlashMessage('danger', 'Error marking item as returned. Please try again.');
            }
        } else {
            setFlashMessage('danger', 'Invalid borrowing record or item already returned.');
        }
    } else {
        setFlashMessage('danger', 'Please select the return condition.');
    }
}

// Redirect back to admin borrowings page
header("Location: admin_borrowings.php");
exit();
?> 