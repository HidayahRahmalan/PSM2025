<?php
require 'vendor/autoload.php'; // Path to Stripe PHP library
session_start();

// Set your Stripe secret key (replace with your actual test secret key)
\Stripe\Stripe::setApiKey('sk_test_51RpTEKLHrw1a9LlFPZN98Zu75qfIvOsp5nBonSJU1dx5iFmWPPX6JmlmKMFNEk4XWtejCcRVB1fEUDEG8cgBc6XU00KlIOmcdT');

$amount = $_POST['amountPaid'] * 100; // Stripe expects cents
$bookID = $_POST['bookID'];
$studID = $_SESSION['studID'];

$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency' => 'myr',
            'product_data' => [
                'name' => 'Hostel Fee Payment (Booking ' . $bookID . ')',
            ],
            'unit_amount' => $amount,
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/studStripeCallback.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/studPayment.php?tab=pending',
    'metadata' => [
        'bookID' => $bookID,
        'studID' => $studID
    ]
]);

header('Location: ' . $session->url);
exit(); 