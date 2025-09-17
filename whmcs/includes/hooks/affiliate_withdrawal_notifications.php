<?php
add_hook('AffiliateWithdrawalRequest', 1, function ($vars) {
  $command = 'TriggerNotificationEvent';
  $postData = array(
    'notification_identifier' => 'affiliate.withdrawal.confirmation',
    'title' => '💰 Withdrawal Request Received',
    'message' => 'Your withdrawal request for $' . number_format($vars['balance'], 2) . ' has been received and is being processed. You will be notified once it\'s completed.',
    'url' => 'https://' . $_SERVER['HTTP_HOST'] . '/affiliates.php',
    'status' => 'Info',
    'statusStyle' => 'info',
    'attributes' => [
      [
        'label' => 'Affiliate ID',
        'value' => $vars['affiliateId'],
      ],
      [
        'label' => 'User ID',
        'value' => $vars['userId'],
      ],
      [
        'label' => 'Client ID',
        'value' => $vars['clientId'],
      ],
      [
        'label' => 'Withdrawal Amount',
        'value' => '$' . number_format($vars['balance'], 2),
      ]
    ]
  );

  $results = localAPI($command, $postData);

  if ($results['result'] == 'success') {
    logActivity("Affiliate Withdrawal: Successfully triggered notification for affiliate ID " . $vars['affiliateId'] . " - Amount: $" . $vars['balance']);
  } else {
    logActivity("Affiliate Withdrawal: Failed to trigger notification - " . $results['message']);
  }

  return [];
});
