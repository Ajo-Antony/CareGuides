<?php
session_start();
require_once 'config/database.php';

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? 0;
$email_status = $_GET['email'] ?? 'sent';

// Fetch appointment details
$appointment = null;
if ($appointment_id) {
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();
}

// Format date and time
if ($appointment) {
    $formatted_date = date('F j, Y', strtotime($appointment['appointment_date']));
    $time_slot = '';
    switch($appointment['appointment_time']) {
        case '09:00': $time_slot = '09:00 AM - 10:00 AM'; break;
        case '10:00': $time_slot = '10:00 AM - 11:00 AM'; break;
        case '11:00': $time_slot = '11:00 AM - 12:00 PM'; break;
        case '14:00': $time_slot = '02:00 PM - 03:00 PM'; break;
        case '15:00': $time_slot = '03:00 PM - 04:00 PM'; break;
        case '16:00': $time_slot = '04:00 PM - 05:00 PM'; break;
        default: $time_slot = $appointment['appointment_time'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Confirmed - CareGuides</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .success-container {
            max-width: 800px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-header {
            background: linear-gradient(135deg, #4a6fa5 0%, #2c3e50 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 1s infinite alternate;
        }
        
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-10px); }
        }
        
        .success-title {
            font-size: 2.5rem;
            margin: 0 0 10px 0;
            font-weight: 700;
        }
        
        .success-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .content {
            padding: 40px;
        }
        
        .appointment-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            border-left: 5px solid #4a6fa5;
        }
        
        .detail-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-label {
            flex: 1;
            font-weight: 600;
            color: #495057;
        }
        
        .detail-value {
            flex: 2;
            color: #212529;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            background: #fff3cd;
            color: #856404;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .email-notice {
            background: #e7f3ff;
            border: 1px solid #b6d4fe;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .email-notice.error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .next-steps {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .next-steps h3 {
            color: #2e7d32;
            margin-top: 0;
        }
        
        .next-steps ol {
            margin: 15px 0;
            padding-left: 20px;
        }
        
        .next-steps li {
            margin-bottom: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            min-width: 180px;
        }
        
        .btn-primary {
            background: #4a6fa5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a5a8c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(58, 90, 140, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-icon {
            margin-right: 8px;
        }
        
        .footer-info {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .footer-info a {
            color: #4a6fa5;
            text-decoration: none;
        }
        
        .footer-info a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .success-container {
                margin: 20px;
            }
            
            .success-header {
                padding: 30px 20px;
            }
            
            .content {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">✓</div>
            <h1 class="success-title">Appointment Confirmed!</h1>
            <p class="success-subtitle">Your consultation has been successfully scheduled</p>
            <div class="status-badge">APPOINTMENT ID: APPT-<?php echo $appointment_id; ?></div>
        </div>
        
        <div class="content">
            <?php if ($email_status === 'failed'): ?>
                <div class="email-notice error">
                    <h3>⚠️ Email Notification Issue</h3>
                    <p>Your appointment was booked successfully, but we encountered an issue sending the confirmation email. Please note your Appointment ID for reference.</p>
                </div>
            <?php else: ?>
                <div class="email-notice">
                    <h3>📧 Email Sent!</h3>
                    <p>A detailed confirmation has been sent to <strong><?php echo htmlspecialchars($appointment['parent_email'] ?? ''); ?></strong>. Please check your inbox (and spam folder).</p>
                </div>
            <?php endif; ?>
            
            <?php if ($appointment): ?>
                <div class="appointment-details">
                    <h3 style="margin-top: 0; color: #2c3e50;">Appointment Summary</h3>
                    <div class="detail-item">
                        <div class="detail-label">Appointment ID:</div>
                        <div class="detail-value">APPT-<?php echo $appointment_id; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Child's Name:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($appointment['child_name']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Child's Age:</div>
                        <div class="detail-value"><?php echo $appointment['child_age']; ?> years</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Parent/Guardian:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($appointment['parent_name']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Specialist:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($appointment['doctor']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date:</div>
                        <div class="detail-value"><?php echo $formatted_date; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Time Slot:</div>
                        <div class="detail-value"><?php echo $time_slot; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Consultation Type:</div>
                        <div class="detail-value"><?php echo ucfirst($appointment['consult_type']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value"><span class="status-badge">PENDING CONFIRMATION</span></div>
                    </div>
                </div>
                
                <div class="next-steps">
                    <h3>📋 What Happens Next?</h3>
                    <ol>
                        <li><strong>Confirmation Call:</strong> Our team will contact you within 24 hours to confirm the appointment</li>
                        <li><strong>Reminder:</strong> You'll receive a reminder 24 hours before your appointment</li>
                        <li><strong>Preparation:</strong> For in-person consultations, please bring any relevant medical reports</li>
                        <li><strong>Video Consult:</strong> If you chose video consultation, you'll receive a Zoom link 1 hour before the appointment</li>
                    </ol>
                    <p><strong>Need to reschedule?</strong> Contact us at least 24 hours in advance at <strong>+91 94969 409XX</strong>.</p>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="book_appointment.html" class="btn btn-primary">
                    <span class="btn-icon">📅</span> Book Another Appointment
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <span class="btn-icon">🏠</span> Back to Home
                </a>
                <a href="book_appointment.php" class="btn btn-primary">
                    <span class="btn-icon">👁️</span> View My Appointments
                </a>
            </div>
        </div>
        
        <div class="footer-info">
            <p><strong>CareGuides Autism Center</strong></p>
            <p>📍 123 Autism Care Lane, Kochi, Kerala 682001</p>
            <p>📞 Emergency: +91 94969 409XX | 📧 Email: careguidesautism@gmail.com</p>
            <p>⏰ Operating Hours: Mon-Sat 9:00 AM - 6:00 PM | Sun 10:00 AM - 2:00 PM</p>
            <p style="margin-top: 15px; font-size: 12px; opacity: 0.8;">
                Need help? <a href="contact.php">Contact Support</a> | 
                <a href="privacy.php">Privacy Policy</a> | 
                <a href="terms.php">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>