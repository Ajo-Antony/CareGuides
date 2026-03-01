<?php
// Include PHPMailer before anything else
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

session_start();

// Database connection using your existing config
require_once 'config/database.php';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

$error = '';
$success = '';

// Check if this is a reschedule request
$reschedule_id = isset($_GET['reschedule']) ? intval($_GET['reschedule']) : 0;
$reschedule_data = null;

if ($reschedule_id && $user_id) {
    // Fetch existing appointment data for rescheduling
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reschedule_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reschedule_data = $result->fetch_assoc();
    $stmt->close();
}

// Process appointment form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $parent_name = isset($_POST['parent_name']) ? trim($_POST['parent_name']) : '';
    $child_name = isset($_POST['child_name']) ? trim($_POST['child_name']) : '';
    $child_age = isset($_POST['child_age']) ? intval($_POST['child_age']) : 0;
    $parent_phone = isset($_POST['parent_phone']) ? trim($_POST['parent_phone']) : '';
    $parent_email = isset($_POST['parent_email']) ? trim($_POST['parent_email']) : '';
    $doctor = isset($_POST['doctor']) ? trim($_POST['doctor']) : '';
    $appointment_date = isset($_POST['date']) ? $_POST['date'] : '';
    $appointment_time = isset($_POST['time']) ? $_POST['time'] : '';
    $consult_type = isset($_POST['consult_type']) ? $_POST['consult_type'] : 'in-person';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Validate inputs
    if (empty($parent_name) || empty($child_name) || empty($parent_phone) || empty($parent_email) || empty($doctor) || empty($appointment_date) || empty($appointment_time)) {
        $error = "Please fill in all required fields.";
    } elseif ($child_age < 1 || $child_age > 18) {
        $error = "Child's age must be between 1 and 18 years.";
    } elseif (!preg_match('/^[0-9]{10}$/', $parent_phone)) {
        $error = "Please enter a valid 10-digit phone number.";
    } elseif (!filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strtotime($appointment_date) < strtotime('today')) {
        $error = "Appointment date cannot be in the past.";
    } elseif (!isset($_POST['terms'])) {
        $error = "Please agree to the terms and conditions.";
    } else {
        // Format time properly
        $time_slot = '';
        switch($appointment_time) {
            case '09:00': $time_slot = '09:00 AM - 10:00 AM'; break;
            case '10:00': $time_slot = '10:00 AM - 11:00 AM'; break;
            case '11:00': $time_slot = '11:00 AM - 12:00 PM'; break;
            case '14:00': $time_slot = '02:00 PM - 03:00 PM'; break;
            case '15:00': $time_slot = '03:00 PM - 04:00 PM'; break;
            case '16:00': $time_slot = '04:00 PM - 05:00 PM'; break;
            default: $time_slot = $appointment_time;
        }
        
        // If rescheduling, update existing appointment
        if ($reschedule_id && $reschedule_data) {
            $stmt = $conn->prepare("UPDATE appointments SET parent_name = ?, child_name = ?, child_age = ?, parent_phone = ?, parent_email = ?, doctor = ?, appointment_date = ?, appointment_time = ?, consult_type = ?, message = ?, status = 'pending' WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssisssssssii", $parent_name, $child_name, $child_age, $parent_phone, $parent_email, $doctor, $appointment_date, $appointment_time, $consult_type, $message, $reschedule_id, $user_id);
            
            if ($stmt->execute()) {
                $appointment_id = $reschedule_id;
                $is_rescheduled = true;
                $stmt->close();
            } else {
                $error = "Failed to reschedule appointment. Please try again. Error: " . $conn->error;
            }
        } else {
            // For guests, set user_id to 0 or NULL
            $booking_user_id = $user_id ? $user_id : 0;
            
            // Insert new appointment
            $stmt = $conn->prepare("INSERT INTO appointments (user_id, parent_name, child_name, child_age, parent_phone, parent_email, doctor, appointment_date, appointment_time, consult_type, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("ississsssss", $booking_user_id, $parent_name, $child_name, $child_age, $parent_phone, $parent_email, $doctor, $appointment_date, $appointment_time, $consult_type, $message);
            
            if ($stmt->execute()) {
                $appointment_id = $stmt->insert_id;
                $is_rescheduled = false;
                $stmt->close();
            } else {
                $error = "Failed to book appointment. Please try again. Error: " . $conn->error;
            }
        }
        
        if (empty($error) && isset($appointment_id)) {
            // Track activity only if user is logged in
            if ($user_id) {
                $activity_type = $is_rescheduled ? 'appointment_rescheduled' : 'appointment_booked';
                $activity_details = ($is_rescheduled ? 'Rescheduled' : 'Booked') . " appointment with $doctor for $child_name";
                trackActivity($user_id, $activity_type, $activity_details);
            }
            
            // Send email notification using PHPMailer
            try {
                $mail = new PHPMailer(true);
                
                // SMTP Configuration
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'ajoantony2021@gmail.com';   // Your Gmail
                $mail->Password   = 'vihxutkagmwybopr';          // Your App Password
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                
                // Email settings
                $mail->setFrom('ajoantony2021@gmail.com', 'CareGuides Autism Center');
                $mail->addAddress($parent_email, $parent_name);
                $mail->addCC('careguidesautism@gmail.com'); // CC to admin
                
                $mail->Subject = $is_rescheduled ? 'Appointment Rescheduled - CareGuides Autism Center' : 'Appointment Confirmation - CareGuides Autism Center';
                $mail->isHTML(true);
                
                // HTML Email Content
                $mail->Body = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Appointment " . ($is_rescheduled ? 'Rescheduled' : 'Confirmation') . "</title>
                    <style>
                        body {
                            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            line-height: 1.6;
                            color: #333;
                            background-color: #f8f9fa;
                            margin: 0;
                            padding: 0;
                        }
                        .container {
                            max-width: 600px;
                            margin: 0 auto;
                            background: white;
                            border-radius: 15px;
                            overflow: hidden;
                            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                        }
                        .header {
                            background: linear-gradient(135deg, #4a6fa5 0%, #2c3e50 100%);
                            color: white;
                            padding: 30px 20px;
                            text-align: center;
                        }
                        .header h1 {
                            margin: 0;
                            font-size: 28px;
                            font-weight: 600;
                        }
                        .logo {
                            font-size: 2.5rem;
                            font-weight: bold;
                            margin-bottom: 10px;
                        }
                        .content {
                            padding: 30px;
                        }
                        .details-card {
                            background: #f8f9fa;
                            border-radius: 10px;
                            padding: 20px;
                            margin: 20px 0;
                            border-left: 4px solid #4a6fa5;
                        }
                        .detail-row {
                            display: flex;
                            margin-bottom: 10px;
                            padding-bottom: 10px;
                            border-bottom: 1px solid #eee;
                        }
                        .detail-label {
                            flex: 1;
                            font-weight: 600;
                            color: #555;
                        }
                        .detail-value {
                            flex: 2;
                            color: #333;
                        }
                        .footer {
                            background: #2c3e50;
                            color: white;
                            padding: 20px;
                            text-align: center;
                            font-size: 14px;
                        }
                        .status-badge {
                            display: inline-block;
                            padding: 5px 15px;
                            background: #fff3cd;
                            color: #856404;
                            border-radius: 20px;
                            font-weight: 600;
                            font-size: 12px;
                        }
                        .cta-button {
                            display: inline-block;
                            background: #4a6fa5;
                            color: white;
                            padding: 12px 30px;
                            text-decoration: none;
                            border-radius: 6px;
                            font-weight: 600;
                            margin: 20px 0;
                        }
                        .contact-info {
                            background: #e9f7fe;
                            border-radius: 8px;
                            padding: 15px;
                            margin: 20px 0;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <div class='logo'>CareGuides</div>
                            <h1>Appointment " . ($is_rescheduled ? 'Rescheduled' : 'Confirmation') . "</h1>
                        </div>
                        
                        <div class='content'>
                            <p>Dear <strong>$parent_name</strong>,</p>
                            <p>" . ($is_rescheduled ? 'Your appointment has been successfully rescheduled.' : 'Thank you for scheduling an appointment with CareGuides Autism Center. Your appointment has been successfully booked') . " and is currently <span class='status-badge'>PENDING CONFIRMATION</span>.</p>
                            
                            <div class='details-card'>
                                <h3 style='color: #4a6fa5; margin-top: 0;'>Appointment Details</h3>
                                <div class='detail-row'>
                                    <div class='detail-label'>Appointment ID:</div>
                                    <div class='detail-value'>APPT-$appointment_id</div>
                                </div>
                                <div class='detail-row'>
                                    <div class='detail-label'>Child's Name:</div>
                                    <div class='detail-value'>$child_name</div>
                                </div>
                                <div class='detail-row'>
                                    <div class='detail-label'>Child's Age:</div>
                                    <div class='detail-value'>$child_age years</div>
                                </div>
                                <div class='detail-row'>
                                    <div class='detail-label'>Specialist:</div>
                                    <div class='detail-value'>$doctor</div>
                                </div>
                                <div class='detail-row'>
                                    <div class='detail-label'>Date:</div>
                                    <div class='detail-value'>" . date('F j, Y', strtotime($appointment_date)) . "</div>
                                </div>
                                <div class='detail-row'>
                                    <div class='detail-label'>Time Slot:</div>
                                    <div class='detail-value'>$time_slot</div>
                                </div>
                                <div class='detail-row'>
                                    <div class='detail-label'>Consultation Type:</div>
                                    <div class='detail-value'>" . ucfirst($consult_type) . "</div>
                                </div>
                                <div class='detail-row'>
                                    <div class='detail-label'>Parent Email:</div>
                                    <div class='detail-value'>$parent_email</div>
                                </div>
                                <div class='detail-row'>
                                    <div class='detail-label'>Parent Phone:</div>
                                    <div class='detail-value'>$parent_phone</div>
                                </div>
                            </div>
                            
                            " . (!empty($message) ? "
                            <div class='contact-info'>
                                <strong>Your Notes:</strong>
                                <p>$message</p>
                            </div>
                            " : "") . "
                            
                            <div class='contact-info'>
                                <h4 style='margin-top: 0;'>What's Next?</h4>
                                <ol>
                                    <li>Our team will review your appointment within 24 hours</li>
                                    <li>You'll receive a confirmation call/email once approved</li>
                                    <li>Please arrive 15 minutes early for in-person consultations</li>
                                    <li>For video consultations, you'll receive a Zoom link 1 hour before the appointment</li>
                                </ol>
                            </div>
                            
                            <p><strong>Important:</strong> Please keep this email for your records. If you need to reschedule or cancel, please contact us at least 24 hours in advance.</p>
                            
                            <a href='mailto:careguidesautism@gmail.com' class='cta-button'>Contact Support</a>
                        </div>
                        
                        <div class='footer'>
                            <p><strong>CareGuides Autism Center</strong></p>
                            <p>📍 123 Autism Care Lane, Kochi, Kerala 682001</p>
                            <p>📞 +91 94969 409XX | ✉️ careguidesautism@gmail.com</p>
                            <p>⏰ Mon-Sat: 9:00 AM - 6:00 PM | Sun: 10:00 AM - 2:00 PM</p>
                            <p style='margin-top: 15px; font-size: 12px; opacity: 0.8;'>
                                This is an automated message. Please do not reply to this email.
                            </p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Plain text version for non-HTML email clients
                $mail->AltBody = "APPOINTMENT " . ($is_rescheduled ? 'RESCHEDULED' : 'CONFIRMATION') . "\n\n" .
                    "Dear $parent_name,\n\n" .
                    ($is_rescheduled ? 'Your appointment has been successfully rescheduled.' : 'Thank you for scheduling an appointment with CareGuides Autism Center.') . "\n\n" .
                    "APPOINTMENT DETAILS:\n" .
                    "Appointment ID: APPT-$appointment_id\n" .
                    "Child's Name: $child_name\n" .
                    "Child's Age: $child_age years\n" .
                    "Specialist: $doctor\n" .
                    "Date: " . date('F j, Y', strtotime($appointment_date)) . "\n" .
                    "Time: $time_slot\n" .
                    "Consultation Type: " . ucfirst($consult_type) . "\n" .
                    "Parent Email: $parent_email\n" .
                    "Parent Phone: $parent_phone\n" .
                    "Status: PENDING CONFIRMATION\n\n" .
                    (!empty($message) ? "Your Notes: $message\n\n" : "") .
                    "WHAT'S NEXT:\n" .
                    "1. Our team will review your appointment within 24 hours\n" .
                    "2. You'll receive a confirmation call/email once approved\n" .
                    "3. Please arrive 15 minutes early for in-person consultations\n" .
                    "4. For video consultations, you'll receive a Zoom link 1 hour before\n\n" .
                    "IMPORTANT: Please keep this email for your records.\n\n" .
                    "Contact: +91 94969 409XX | careguidesautism@gmail.com\n" .
                    "Address: 123 Autism Care Lane, Kochi, Kerala 682001\n\n" .
                    "This is an automated message. Please do not reply to this email.";
                
                $mail->send();
                
                // Redirect to success page
                header("Location: appointment_success.php?id=$appointment_id" . ($is_rescheduled ? '&rescheduled=1' : ''));
                exit();
                
            } catch (Exception $e) {
                // Email failed, but appointment was saved
                // Still redirect to success since appointment was saved
                header("Location: appointment_success.php?id=$appointment_id&email=failed" . ($is_rescheduled ? '&rescheduled=1' : ''));
                exit();
            }
        }
    }
}

// Function to track activity
function trackActivity($user_id, $type, $details) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $type, $details, $ip, $user_agent);
    $stmt->execute();
    $stmt->close();
}

// Now continue with HTML output
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - CareGuides</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* All the CSS styles from the previous code remain exactly the same */
        /* I'm including the complete CSS here for reference, but it's the same as before */
        
        :root {
            --primary: #4a6fa5;
            --primary-dark: #166088;
            --primary-light: #e8f0ff;
            --secondary: #6d9dc5;
            --accent: #7ae7c7;
            --success: #4caf50;
            --error: #f44336;
            --warning: #ff9800;
            --text-dark: #2c3e50;
            --text-light: #546e7a;
            --bg-light: #f8f9fa;
            --bg-white: #ffffff;
            --shadow: 0 8px 30px rgba(0,0,0,0.08);
            --shadow-hover: 0 15px 40px rgba(0,0,0,0.12);
            --radius: 16px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .appointment-container {
            max-width: 1000px;
            margin: 30px auto;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }

        .header-section h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            position: relative;
            z-index: 1;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 40px 0;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 20px;
            right: 20px;
            height: 3px;
            background: #e0e0e0;
            transform: translateY(-50%);
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            color: #999;
            transition: var(--transition);
        }

        .step.active .step-circle {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .step-label {
            font-size: 0.9rem;
            color: var(--text-light);
            transition: var(--transition);
        }

        .step.active .step-label {
            color: var(--primary);
            font-weight: 600;
        }

        .form-wrapper {
            background: var(--bg-white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
            transition: var(--transition);
        }

        .form-wrapper:hover {
            box-shadow: var(--shadow-hover);
        }

        .form-section {
            padding: 40px;
            border-bottom: 1px solid #f0f0f0;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            color: var(--primary-dark);
        }

        .section-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: var(--primary);
        }

        .section-title h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 1rem;
        }

        .form-label .required {
            color: var(--error);
            margin-left: 3px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(74, 111, 165, 0.1);
        }

        .form-control:focus + .input-icon {
            color: var(--primary);
        }

        .input-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            transition: var(--transition);
        }

        .input-hint {
            display: block;
            margin-top: 6px;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        /* Doctor Cards */
        .doctor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .doctor-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 25px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .doctor-card:hover {
            border-color: var(--primary-light);
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .doctor-card.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .doctor-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .doctor-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }

        .doctor-specialty {
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .doctor-experience {
            font-size: 0.85rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .doctor-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        /* Time Slots */
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .time-slot {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: white;
        }

        .time-slot:hover {
            border-color: var(--primary-light);
            transform: translateY(-3px);
        }

        .time-slot.selected {
            border-color: var(--primary);
            background: var(--primary-light);
            font-weight: 600;
        }

        .time-slot input[type="radio"] {
            display: none;
        }

        /* Consultation Type */
        .consult-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .consult-option {
            padding: 25px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: white;
        }

        .consult-option:hover {
            border-color: var(--primary-light);
            transform: translateY(-5px);
        }

        .consult-option.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .consult-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary);
        }

        .consult-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .consult-desc {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        /* Buttons */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #f0f0f0;
        }

        .btn {
            padding: 16px 40px;
            border: none;
            border-radius: 10px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(74, 111, 165, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(74, 111, 165, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-light);
            border: 2px solid #e0e0e0;
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Alerts */
        .alert {
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert-error {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            border-left: 5px solid #f44336;
        }

        .alert-success {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
            border-left: 5px solid #4caf50;
        }

        .alert-icon {
            font-size: 1.5rem;
        }

        /* User Info Banner */
        .user-banner {
            background: linear-gradient(135deg, var(--primary-light) 0%, #e3f2fd 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid var(--primary);
        }

        .user-info h3 {
            color: var(--primary-dark);
            margin-bottom: 5px;
            font-size: 1.3rem;
        }

        .user-status {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-light);
        }

        .status-badge {
            background: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Loading Animation */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Checkbox Styling */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 25px 0;
        }

        .custom-checkbox {
            width: 22px;
            height: 22px;
            border: 2px solid #ddd;
            border-radius: 6px;
            position: relative;
            cursor: pointer;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .custom-checkbox::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            color: white;
            font-weight: bold;
            transition: var(--transition);
        }

        .checkbox-group input:checked + .custom-checkbox {
            background: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-group input:checked + .custom-checkbox::after {
            transform: translate(-50%, -50%) scale(1);
        }

        .checkbox-group input {
            display: none;
        }

        .checkbox-label {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .checkbox-label a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .checkbox-label a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-section {
                padding: 30px 20px;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .form-section {
                padding: 25px 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .doctor-grid,
            .time-slots,
            .consult-options {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
            }
            
            .progress-steps {
                flex-direction: column;
                gap: 20px;
            }
            
            .progress-steps::before {
                display: none;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .appointment-container {
                margin: 15px auto;
            }
            
            .header-section h1 {
                font-size: 1.8rem;
            }
            
            .section-title h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="appointment-container">
        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="step active">
                <div class="step-circle">1</div>
                <div class="step-label">Child Info</div>
            </div>
            <div class="step">
                <div class="step-circle">2</div>
                <div class="step-label">Parent Info</div>
            </div>
            <div class="step">
                <div class="step-circle">3</div>
                <div class="step-label">Specialist</div>
            </div>
            <div class="step">
                <div class="step-circle">4</div>
                <div class="step-label">Schedule</div>
            </div>
            <div class="step">
                <div class="step-circle">5</div>
                <div class="step-label">Confirmation</div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle alert-icon"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="header-section">
            <h1>
                <?php echo !empty($reschedule_data) ? 'Reschedule Appointment' : 'Book Specialist Consultation'; ?>
                <i class="fas fa-calendar-check" style="margin-left: 15px; opacity: 0.8;"></i>
            </h1>
            <p>
                <?php echo !empty($reschedule_data) 
                    ? 'Update your existing appointment details below' 
                    : 'Complete the form below to schedule a consultation with our autism specialists'; ?>
            </p>
        </div>

        <!-- User Info Banner -->
        <div class="user-banner">
            <div class="user-info">
                <h3>
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars(!empty($full_name) ? $full_name : $username); ?>
                </h3>
                <div class="user-status">
                    <span class="status-badge">
                        <?php echo !empty($user_id) ? 'Registered User' : 'Guest'; ?>
                    </span>
                    <?php if (!empty($reschedule_data)): ?>
                        <span>• Rescheduling Appointment #APPT-<?php echo $reschedule_id; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <?php if (empty($user_id)): ?>
                    <a href="login.php" class="btn btn-outline" style="padding: 10px 20px;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Form -->
        <form method="POST" action="" id="appointmentForm" onsubmit="return validateAppointmentForm()">
            <div class="form-wrapper">
                <!-- Child Information Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-child"></i>
                        </div>
                        <h2>Child Information</h2>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="child_name" class="form-label">
                                Child's Full Name <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="child_name" name="child_name" class="form-control" required
                                       value="<?php echo htmlspecialchars(isset($_POST['child_name']) ? $_POST['child_name'] : (!empty($reschedule_data['child_name']) ? $reschedule_data['child_name'] : '')); ?>"
                                       placeholder="Enter child's full name">
                                <i class="fas fa-user input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="child_age" class="form-label">
                                Child's Age <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="child_age" name="child_age" min="1" max="18" class="form-control" required
                                       value="<?php echo htmlspecialchars(isset($_POST['child_age']) ? $_POST['child_age'] : (!empty($reschedule_data['child_age']) ? $reschedule_data['child_age'] : '')); ?>"
                                       placeholder="Age in years">
                                <i class="fas fa-birthday-cake input-icon"></i>
                            </div>
                            <span class="input-hint">Must be between 1 and 18 years</span>
                        </div>
                    </div>
                </div>

                <!-- Parent Information Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h2>Parent/Guardian Information</h2>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="parent_name" class="form-label">
                                Your Name <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="parent_name" name="parent_name" class="form-control" required
                                       value="<?php echo htmlspecialchars(isset($_POST['parent_name']) ? $_POST['parent_name'] : (!empty($reschedule_data['parent_name']) ? $reschedule_data['parent_name'] : (!empty($full_name) ? $full_name : ''))); ?>"
                                       placeholder="Enter your full name">
                                <i class="fas fa-user-tie input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_phone" class="form-label">
                                Phone Number <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="tel" id="parent_phone" name="parent_phone" class="form-control" required
                                       pattern="[0-9]{10}"
                                       value="<?php echo htmlspecialchars(isset($_POST['parent_phone']) ? $_POST['parent_phone'] : (!empty($reschedule_data['parent_phone']) ? $reschedule_data['parent_phone'] : '')); ?>"
                                       placeholder="10-digit mobile number">
                                <i class="fas fa-phone input-icon"></i>
                            </div>
                            <span class="input-hint">We'll send appointment reminders via SMS</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_email" class="form-label">
                                Email Address <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="email" id="parent_email" name="parent_email" class="form-control" required
                                       value="<?php echo htmlspecialchars(isset($_POST['parent_email']) ? $_POST['parent_email'] : (!empty($reschedule_data['parent_email']) ? $reschedule_data['parent_email'] : '')); ?>"
                                       placeholder="Your email address">
                                <i class="fas fa-envelope input-icon"></i>
                            </div>
                            <span class="input-hint">Confirmation email will be sent here</span>
                        </div>
                    </div>
                </div>

                <!-- Specialist Selection Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h2>Select Specialist</h2>
                    </div>
                    
                    <div class="doctor-grid">
                        <?php 
                        $doctors = [
                            'Dr. Antony Sebastian' => ['specialty' => 'Child Psychiatry', 'experience' => '15+ years', 'avatar' => 'AS'],
                            'Dr. Maria George' => ['specialty' => 'Behavioral Therapy', 'experience' => '12+ years', 'avatar' => 'MG'],
                            'Dr. Jacob Thomas' => ['specialty' => 'Developmental Pediatrics', 'experience' => '18+ years', 'avatar' => 'JT'],
                            'Dr. Thomas Philip' => ['specialty' => 'Pediatric Neurology', 'experience' => '20+ years', 'avatar' => 'TP']
                        ];
                        
                        $selected_doctor = '';
                        if (isset($_POST['doctor'])) {
                            $selected_doctor = $_POST['doctor'];
                        } elseif (!empty($reschedule_data['doctor'])) {
                            $selected_doctor = $reschedule_data['doctor'];
                        }
                        ?>
                        
                        <?php foreach ($doctors as $doctor_name => $details): ?>
                        <label class="doctor-card <?php echo ($selected_doctor == $doctor_name) ? 'selected' : ''; ?>">
                            <input type="radio" name="doctor" value="<?php echo $doctor_name; ?>" 
                                   <?php echo ($selected_doctor == $doctor_name) ? 'checked' : ''; ?> required>
                            <div class="doctor-avatar"><?php echo $details['avatar']; ?></div>
                            <div class="doctor-name"><?php echo $doctor_name; ?></div>
                            <div class="doctor-specialty"><?php echo $details['specialty']; ?></div>
                            <div class="doctor-experience">
                                <i class="fas fa-award"></i>
                                <?php echo $details['experience']; ?> experience
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Appointment Schedule Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h2>Appointment Schedule</h2>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date" class="form-label">
                                Preferred Date <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="date" id="date" name="date" class="form-control" required
                                       value="<?php echo htmlspecialchars(isset($_POST['date']) ? $_POST['date'] : (!empty($reschedule_data['appointment_date']) ? $reschedule_data['appointment_date'] : '')); ?>">
                                <i class="fas fa-calendar-day input-icon"></i>
                            </div>
                            <span class="input-hint">Appointments available Monday to Saturday</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Preferred Time Slot <span class="required">*</span>
                            </label>
                            <div class="time-slots">
                                <?php 
                                $time_slots = [
                                    '09:00' => '09:00 AM - 10:00 AM',
                                    '10:00' => '10:00 AM - 11:00 AM',
                                    '11:00' => '11:00 AM - 12:00 PM',
                                    '14:00' => '02:00 PM - 03:00 PM',
                                    '15:00' => '03:00 PM - 04:00 PM',
                                    '16:00' => '04:00 PM - 05:00 PM'
                                ];
                                
                                $selected_time = '';
                                if (isset($_POST['time'])) {
                                    $selected_time = $_POST['time'];
                                } elseif (!empty($reschedule_data['appointment_time'])) {
                                    $selected_time = $reschedule_data['appointment_time'];
                                }
                                ?>
                                
                                <?php foreach ($time_slots as $time_value => $time_label): ?>
                                <label class="time-slot <?php echo ($selected_time == $time_value) ? 'selected' : ''; ?>">
                                    <input type="radio" name="time" value="<?php echo $time_value; ?>" 
                                           <?php echo ($selected_time == $time_value) ? 'checked' : ''; ?> required>
                                    <?php echo $time_label; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Consultation Type Section -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <h2>Consultation Type</h2>
                    </div>
                    
                    <?php 
                    $selected_type = 'in-person';
                    if (isset($_POST['consult_type'])) {
                        $selected_type = $_POST['consult_type'];
                    } elseif (!empty($reschedule_data['consult_type'])) {
                        $selected_type = $reschedule_data['consult_type'];
                    }
                    ?>
                    
                    <div class="consult-options">
                        <label class="consult-option <?php echo ($selected_type == 'in-person') ? 'selected' : ''; ?>">
                            <input type="radio" name="consult_type" value="in-person" 
                                   <?php echo ($selected_type == 'in-person') ? 'checked' : ''; ?>>
                            <div class="consult-icon">🏥</div>
                            <div class="consult-title">In-Person Consultation</div>
                            <div class="consult-desc">Visit our specialized center</div>
                        </label>
                        
                        <label class="consult-option <?php echo ($selected_type == 'video') ? 'selected' : ''; ?>">
                            <input type="radio" name="consult_type" value="video" 
                                   <?php echo ($selected_type == 'video') ? 'checked' : ''; ?>>
                            <div class="consult-icon">📹</div>
                            <div class="consult-title">Video Consultation</div>
                            <div class="consult-desc">Secure online video call</div>
                        </label>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="form-section">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-sticky-note"></i>
                        </div>
                        <h2>Additional Information</h2>
                    </div>
                    
                    <div class="form-group">
                        <label for="message" class="form-label">
                            Additional Notes (Optional)
                        </label>
                        <div class="input-wrapper">
                            <textarea id="message" name="message" rows="4" class="form-control" 
                                      placeholder="Please share any specific concerns, symptoms, previous diagnoses, or other relevant information that will help our specialist prepare for your consultation..."><?php echo htmlspecialchars(isset($_POST['message']) ? $_POST['message'] : (!empty($reschedule_data['message']) ? $reschedule_data['message'] : '')); ?></textarea>
                            <i class="fas fa-comment-medical input-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Terms & Submit Section -->
                <div class="form-section">
                    <div class="checkbox-group">
                        <input type="checkbox" id="terms" name="terms" required 
                               <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                        <label for="terms" class="custom-checkbox"></label>
                        <div class="checkbox-label">
                            I agree to the <a href="terms.php" target="_blank">terms and conditions</a> 
                            and <a href="privacy.php" target="_blank">privacy policy</a> 
                            <span class="required">*</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="<?php echo !empty($reschedule_data) ? 'appointments.php' : 'index.php'; ?>" 
                           class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-calendar-check"></i>
                            <?php echo !empty($reschedule_data) ? 'Reschedule Appointment' : 'Book Appointment Now'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('date');
            dateInput.setAttribute('min', today);
            
            // If no value is set, set it to tomorrow by default
            if (!dateInput.value) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                dateInput.value = tomorrow.toISOString().split('T')[0];
            }
            
            // Initialize doctor card selection
            document.querySelectorAll('.doctor-card').forEach(card => {
                card.addEventListener('click', function() {
                    document.querySelectorAll('.doctor-card').forEach(c => {
                        c.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
            
            // Initialize time slot selection
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.time-slot').forEach(s => {
                        s.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
            
            // Initialize consultation type selection
            document.querySelectorAll('.consult-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.consult-option').forEach(o => {
                        o.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
            
            // Update progress steps based on scroll
            function updateProgressSteps() {
                const sections = document.querySelectorAll('.form-section');
                const scrollPosition = window.scrollY + window.innerHeight / 3;
                
                let activeStep = 0;
                sections.forEach((section, index) => {
                    const sectionTop = section.offsetTop;
                    if (scrollPosition >= sectionTop) {
                        activeStep = index + 1;
                    }
                });
                
                document.querySelectorAll('.step').forEach((step, index) => {
                    if (index <= activeStep) {
                        step.classList.add('active');
                    } else {
                        step.classList.remove('active');
                    }
                });
            }
            
            window.addEventListener('scroll', updateProgressSteps);
            updateProgressSteps(); // Initial call
        });
        
        function validateAppointmentForm() {
            const phone = document.getElementById('parent_phone').value;
            const age = document.getElementById('child_age').value;
            const terms = document.getElementById('terms');
            const date = document.getElementById('date').value;
            
            // Validate phone number
            if (!/^\d{10}$/.test(phone)) {
                alert('Please enter a valid 10-digit phone number.');
                document.getElementById('parent_phone').focus();
                return false;
            }
            
            // Validate age
            if (age < 1 || age > 18) {
                alert('Child\'s age must be between 1 and 18 years.');
                document.getElementById('child_age').focus();
                return false;
            }
            
            // Validate date
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                alert('Appointment date cannot be in the past.');
                document.getElementById('date').focus();
                return false;
            }
            
            // Validate terms
            if (!terms.checked) {
                alert('Please agree to the terms and conditions to continue.');
                terms.focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
            submitBtn.disabled = true;
            
            return true;
        }
        
        // Add input validation feedback
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() !== '' && !this.checkValidity()) {
                    this.style.borderColor = 'var(--error)';
                    this.parentElement.querySelector('.input-icon').style.color = 'var(--error)';
                } else if (this.value.trim() !== '') {
                    this.style.borderColor = 'var(--success)';
                    this.parentElement.querySelector('.input-icon').style.color = 'var(--success)';
                }
            });
            
            input.addEventListener('input', function() {
                this.style.borderColor = '#e0e0e0';
                this.parentElement.querySelector('.input-icon').style.color = '#999';
            });
        });
    </script>
</body>
</html>