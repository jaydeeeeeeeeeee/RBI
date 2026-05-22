<?php
// admin/approve_request.php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

// Get current Philippine time
$current_datetime = date('Y-m-d H:i:s');

$id = $_GET['id'] ?? 0;

if ($id) {
    // Update status to Approved with PHP datetime (not MySQL NOW())
    $stmt = $pdo->prepare("UPDATE certificate_requests SET status = 'Approved', approved_at = ? WHERE id = ?");
    $stmt->execute([$current_datetime, $id]);
    
    // Optional: Get resident email to send notification
    try {
        // Get request details and resident email
        $stmt2 = $pdo->prepare("
            SELECT cr.*, r.first_name, r.last_name, r.email, ct.template_name 
            FROM certificate_requests cr
            JOIN residents r ON cr.resident_id = r.id
            LEFT JOIN certificate_templates ct ON cr.template_id = ct.id
            WHERE cr.id = ?
        ");
        $stmt2->execute([$id]);
        $request = $stmt2->fetch();
        
        if ($request && !empty($request['email'])) {
            // Send approval email to resident
            $full_name = $request['first_name'] . ' ' . $request['last_name'];
            $ref_no = $request['reference_no'];
            $template_name = $request['template_name'] ?? 'Certificate';
            $approved_date = date('F d, Y g:i A');
            
            $subject = "Barangay 410 - Your Certificate Request has been Approved";
            
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
                    .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { padding: 20px; }
                    .ref-number { background: #f0f0f0; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 2px; border-radius: 8px; margin: 20px 0; }
                    .instructions { background: #e8f4e8; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
                    .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>✅ Request Approved!</h2>
                        <p>Barangay 410 Zone 42</p>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>" . htmlspecialchars($full_name) . "</strong>,</p>
                        <p>Good news! Your certificate request has been <strong style='color:#28a745'>APPROVED</strong>.</p>
                        
                        <div class='ref-number'>
                            Reference Number: " . htmlspecialchars($ref_no) . "
                        </div>
                        
                        <p><strong>Request Details:</strong></p>
                        <ul>
                            <li><strong>Document Type:</strong> " . htmlspecialchars($template_name) . "</li>
                            <li><strong>Date Approved:</strong> " . $approved_date . "</li>
                        </ul>
                        
                        <div class='instructions'>
                            <h4>📋 What to do next:</h4>
                            <ol>
                                <li>Go to Barangay 410 Zone 42 Hall</li>
                                <li>Bring a valid ID for verification</li>
                                <li>Present your reference number: <strong>" . htmlspecialchars($ref_no) . "</strong></li>
                                <li>Pay the necessary fees (if applicable)</li>
                                <li>Claim your certificate</li>
                            </ol>
                        </div>
                        
                        <p><strong>Barangay Hall Information:</strong></p>
                        <ul>
                            <li>📍 Address: District IV, Sampaloc, Manila</li>
                            <li>📞 Contact #: 522-2991</li>
                            <li>🕒 Office Hours: Monday-Friday, 8:00 AM - 5:00 PM</li>
                        </ul>
                        
                        <p>Thank you for using our online service!</p>
                        
                        <p>Best regards,<br>
                        <strong>Barangay 410 Zone 42</strong><br>
                        P/B MICHAEL JOHN M. REGALA<br>
                        Barangay Captain</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>© 2024 Barangay 410 Zone 42 - All Rights Reserved</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Barangay 410 <noreply@barangay410.gov.ph>" . "\r\n";
            
            mail($request['email'], $subject, $message, $headers);
        }
    } catch (PDOException $e) {
        // Email sending failed, but approval still successful
    }
    
    // Log the approval activity
    logActivity("Certificate Request Approved", "Request ID: $id");
    
    // Redirect back to requests page with success message
    header('Location: requests.php?success=approved');
    exit();
} else {
    header('Location: requests.php');
    exit();
}
?>