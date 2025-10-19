<?php
add_hook('AffiliateActivation', 1, function ($vars) {
  $command = 'TriggerNotificationEvent';
  $postData = array(
    'notification_identifier' => 'affiliate.activation.user',
    'title' => '🎉 Welcome to Our Affiliate Program!',
    'message' => 'Your affiliate account has been activated! You can now start earning commissions by referring new customers.',
    'url' => 'https://' . $_SERVER['HTTP_HOST'] . '/affiliates.php',
    'status' => 'Success',
    'statusStyle' => 'success',
    'attributes' => [
      [
        'label' => 'Affiliate ID',
        'value' => $vars['affid'],
      ],
      [
        'label' => 'User ID',
        'value' => $vars['userid'],
      ]
    ]
  );

  $results = localAPI($command, $postData);

  if ($results['result'] == 'success') {
    logActivity("Affiliate Activation: Successfully triggered notification for affiliate ID " . $vars['affid']);
  } else {
    logActivity("Affiliate Activation: Failed to trigger notification - " . $results['message']);
  }
});
