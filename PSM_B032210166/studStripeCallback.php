<?php
require 'vendor/autoload.php';
session_start();
include 'dbConnection.php';

// Set your Stripe secret key (replace with your actual test secret key)
\Stripe\Stripe::setApiKey('sk_test_51RpTEKLHrw1a9LlFPZN98Zu75qfIvOsp5nBonSJU1dx5iFmWPPX6JmlmKMFNEk4XWtejCcRVB1fEUDEG8cgBc6XU00KlIOmcdT');

$session_id = $_GET['session_id'];
$session = \Stripe\Checkout\Session::retrieve($session_id);

if ($session->payment_status == 'paid') {
    $bookID = $session->metadata->bookID;
    $studID = $session->metadata->studID;
    $amountPaid = $session->amount_total / 100;
    $paymentDate = date('Y-m-d');
    $status = 'PENDING'; // Set to PENDING for admin/staff verification
    $paymentMethod = 'STRIPE';
    $balance = 0;
    $receipt_url = '';
    $receipt_image = null;

    if ($session->payment_intent) {
        $paymentIntent = \Stripe\PaymentIntent::retrieve(
            $session->payment_intent,
            ['expand' => ['charges']]
        );
        error_log("PaymentIntent: " . print_r($paymentIntent, true));
        error_log("Charges: " . print_r($paymentIntent->charges, true));
        $receipt_url = '';
        if (
            isset($paymentIntent->charges->data[0]) &&
            isset($paymentIntent->charges->data[0]->receipt_url)
        ) {
            $receipt_url = $paymentIntent->charges->data[0]->receipt_url;
            error_log("Receipt URL (from charges): " . $receipt_url);
        } elseif (isset($paymentIntent->latest_charge)) {
            $charge = \Stripe\Charge::retrieve($paymentIntent->latest_charge);
            if (isset($charge->receipt_url)) {
                $receipt_url = $charge->receipt_url;
                error_log("Receipt URL (from latest_charge): " . $receipt_url);
            } else {
                error_log("No receipt_url found in latest_charge.");
            }
        } else {
            error_log("No receipt_url found in PaymentIntent charges or latest_charge.");
        }
    }

    // Generate new payment ID (reuse your logic)
    $stmt = $conn->prepare("SELECT MAX(SUBSTRING(PymtID, 2)) as MaxID FROM PAYMENT");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $maxID = intval($row['MaxID'] ?? 0);
    $newID = $maxID + 1;
    $pymtID = 'P' . str_pad($newID, 5, '0', STR_PAD_LEFT);
    $stmt->close();

    // Save the receipt URL as proof
    $pymtProof = $receipt_url;

    // 1. Get hostel fee and semester for this booking
    $stmt = $conn->prepare("
        SELECT s.HostelFee, s.SemID, b.StudID
        FROM BOOKING b
        JOIN SEMESTER s ON b.SemID = s.SemID
        WHERE b.BookID = ?
    ");
    $stmt->bind_param("s", $bookID);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookingData = $result->fetch_assoc();
    $stmt->close();

    $hostelFee = $bookingData['HostelFee'];
    $semID = $bookingData['SemID'];
    $studID = $bookingData['StudID'];

    // 2. Get total paid (COMPLETED + PENDING) before this payment
    $stmt = $conn->prepare("
        SELECT SUM(p.AmountPaid) as TotalPaid
        FROM PAYMENT p
        JOIN BOOKING b ON p.BookID = b.BookID
        WHERE b.SemID = ? AND b.StudID = ? AND (p.Status = 'COMPLETED' OR p.Status = 'PENDING')
    ");
    $stmt->bind_param("ss", $semID, $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $allPaidBefore = $row['TotalPaid'] ?? 0;
    $stmt->close();

    // 3. Calculate new balance after this payment
    $balance = $hostelFee - ($allPaidBefore + $amountPaid);

    // 4. Now insert the payment with the correct balance
    $stmt = $conn->prepare("INSERT INTO PAYMENT (PymtProof, AmountPaid, Balance, Status, PaymentDate, BookID) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdssss", $pymtProof, $amountPaid, $balance, $status, $paymentDate, $bookID);
    if (!$stmt->execute()) {
        error_log("Insert error: " . $stmt->error);
    }
    $stmt->close();

    $_SESSION['success'] = 'Stripe payment successful! Awaiting admin/staff verification.';
    header('Location: studPayment.php?tab=history');
    exit();
} else {
    $_SESSION['error'] = 'Stripe payment failed or cancelled.';
    header('Location: studPayment.php?tab=history');
    exit();
} 