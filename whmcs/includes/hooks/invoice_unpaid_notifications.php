<?php
add_hook('InvoiceUnpaid', 1, function ($vars) {
  $command = 'TriggerNotificationEvent';
  $postData = array(
    'notification_identifier' => 'invoice.unpaid.client',
    'title' => '⚠️ Invoice Payment Issue',
    'message' => 'Your invoice payment could not be processed or has been reversed. Please review your invoice and update your payment method if needed.',
    'url' => 'https://' . $_SERVER['HTTP_HOST'] . '/clientarea.php?action=invoices',
    'status' => 'Warning',
    'statusStyle' => 'warning',
    'attributes' => [
      [
        'label' => 'Invoice ID',
        'value' => $vars['invoiceid'],
      ]
    ]
  );

  $results = localAPI($command, $postData);

  if ($results['result'] == 'success') {
    logActivity("Invoice Unpaid: Successfully triggered notification for invoice ID " . $vars['invoiceid']);
  } else {
    logActivity("Invoice Unpaid: Failed to trigger notification - " . $results['message']);
  }
});
