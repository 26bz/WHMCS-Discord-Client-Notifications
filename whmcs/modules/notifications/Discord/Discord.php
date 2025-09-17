<?php

namespace WHMCS\Module\Notification\Discord;

use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Module\Notification\DescriptionTrait;
use WHMCS\Notification\Contracts\NotificationInterface;

class Discord implements NotificationModuleInterface
{
    use DescriptionTrait;

    public function __construct()
    {
        $this->setDisplayName('Discord');
        $this->setLogoFileName('logo.png');
    }

    public function settings()
    {
        return [
            'api_url' => [
                'FriendlyName' => 'Discord Bot API URL',
                'Type' => 'text',
                'Description' => 'The URL of your Discord bot API endpoint (e.g., http://your-domain.com:3000/api/send-dm)',
                'Placeholder' => 'http://your-domain.com:3000/api/send-dm',
                'Required' => true,
            ],
            'api_key' => [
                'FriendlyName' => 'API Key',
                'Type' => 'password',
                'Description' => 'The API key for authenticating with your Discord bot',
                'Required' => true,
            ],
            'custom_field_name' => [
                'FriendlyName' => 'Discord Custom Field Name',
                'Type' => 'text',
                'Description' => 'The name of the custom field that stores Discord profile data',
                'Default' => 'Discord Profile',
                'Required' => true,
            ],
        ];
    }

    public function notificationSettings()
    {
        return [
            'message_format' => [
                'FriendlyName' => 'Message Format',
                'Type' => 'textarea',
                'Rows' => 5,
                'Description' => 'Customize the message format. You can use {title}, {message}, and {url} variables.',
                'Default' => "**{title}**\n\n{message}\n\n{url}",
                'Required' => true,
            ],
            'use_rich_embeds' => [
                'FriendlyName' => 'Use Rich Embeds',
                'Type' => 'yesno',
                'Description' => 'Send notifications as Discord rich embeds instead of plain text',
                'Default' => 'yes',
            ],
            'priority_color_coding' => [
                'FriendlyName' => 'Priority Color Coding',
                'Type' => 'yesno',
                'Description' => 'Use different colors based on ticket priority (requires rich embeds)',
                'Default' => 'yes',
            ],
            'include_attributes' => [
                'FriendlyName' => 'Include Attributes',
                'Type' => 'yesno',
                'Description' => 'Include notification attributes in the message',
                'Default' => 'yes',
            ],
            'include_client_info' => [
                'FriendlyName' => 'Include Client Information',
                'Type' => 'yesno',
                'Description' => 'Include client details in notifications',
                'Default' => 'no',
            ],
        ];
    }
    

    public function testConnection($settings)
    {
        $apiUrl = $settings['api_url'];
        $apiKey = $settings['api_key'];
        
        if (empty($apiUrl) || empty($apiKey)) {
            return [
                'success' => false,
                'errorMsg' => 'API URL and API Key are required',
            ];
        }
        
        try {
            $healthUrl = str_replace('/api/send-dm', '/health', $apiUrl);
            
            $ch = curl_init($healthUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                return [
                    'success' => false,
                    'errorMsg' => 'Connection failed: ' . curl_error($ch),
                ];
            }
            
            curl_close($ch);
            
            if ($httpCode < 200 || $httpCode >= 300) {
                return [
                    'success' => false,
                    'errorMsg' => 'API returned HTTP code ' . $httpCode,
                ];
            }
            
            $responseData = json_decode($response, true);
            if (!$responseData || $responseData['status'] !== 'ok') {
                return [
                    'success' => false,
                    'errorMsg' => 'Bot health check failed',
                ];
            }
            
            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errorMsg' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    public function sendNotification(NotificationInterface $notification, $moduleSettings, $notificationSettings)
    {
        $apiUrl = $moduleSettings['api_url'];
        $apiKey = $moduleSettings['api_key'];
        $customFieldName = $moduleSettings['custom_field_name'];
        
        $clientId = null;
        foreach ($notification->getAttributes() as $attribute) {
            if ($attribute->getLabel() == 'Client ID') {
                $clientId = $attribute->getValue();
                break;
            }
        }
        
        if (!$clientId) {
            $attributes = $notification->getAttributes();
            $attributeValues = [];
            foreach ($attributes as $attribute) {
                $attributeValues[$attribute->getLabel()] = $attribute->getValue();
            }
            
            $possibleClientIdFields = ['Client ID', 'User ID', 'UserID', 'ClientID'];
            foreach ($possibleClientIdFields as $field) {
                if (isset($attributeValues[$field])) {
                    $clientId = $attributeValues[$field];
                    break;
                }
            }
            
            if (!$clientId && isset($attributeValues['Ticket ID'])) {
                try {
                    $ticketId = $attributeValues['Ticket ID'];
                    $ticket = \localAPI('GetTicket', ['ticketid' => $ticketId]);
                    
                    if ($ticket && $ticket['result'] == 'success' && !empty($ticket['userid'])) {
                        $clientId = $ticket['userid'];
                    }
                } catch (\Exception $e) {
                    \logActivity('Discord Notification Error: ' . $e->getMessage());
                }
            }
        }
        
        if (!$clientId) {
            $title = $notification->getTitle();
            $message = $notification->getMessage();
            
            if (preg_match('/User ID:\s*(\d+)/', $title, $matches)) {
                $clientId = $matches[1];
                \logActivity("Discord Notification: Extracted client ID {$clientId} from notification title");
            } 
            else if (preg_match('/#([A-Z]{2,3})-([0-9]+)/', $title, $matches)) {
                $ticketMask = $matches[0];
                \logActivity("Discord Notification: Found ticket mask {$ticketMask} in title");
                try {
                    $ticket = \WHMCS\Database\Capsule::table('tbltickets')
                        ->where('tid', ltrim($ticketMask, '#'))
                        ->orWhere('tid', $matches[2])
                        ->first();
                        
                    if ($ticket && !empty($ticket->userid)) {
                        $clientId = $ticket->userid;
                        \logActivity("Discord Notification: Found client ID {$clientId} from ticket database");
                    }
                } catch (\Exception $e) {
                    \logActivity('Discord Notification Error: ' . $e->getMessage());
                }
            }
            
            if (!$clientId) {
                \logActivity("Discord Notification: No client ID found for notification. Title: {$title}");
                return;
            }
        }
        
        $client = \localAPI('GetClientsDetails', ['clientid' => $clientId, 'stats' => false]);
        
        if ($client['result'] !== 'success') {
            throw new \Exception('Failed to get client details for user ID ' . $clientId);
        }
        
        $discordId = null;
        
        try {
            $discordValue = \WHMCS\Database\Capsule::table('tblcustomfields')
                ->join('tblcustomfieldsvalues', 'tblcustomfields.id', '=', 'tblcustomfieldsvalues.fieldid')
                ->where('tblcustomfields.fieldname', 'LIKE', '%discord%')
                ->where('tblcustomfieldsvalues.relid', $clientId)
                ->value('tblcustomfieldsvalues.value');
            
            if ($discordValue && is_numeric($discordValue)) {
                $discordId = $discordValue;
            } else if ($discordValue) {
                $jsonData = json_decode($discordValue, true);
                if ($jsonData && isset($jsonData['id'])) {
                    $discordId = $jsonData['id'];
                }
            }
        } catch (\Exception $e) {
            \logActivity('Discord Notification Error: ' . $e->getMessage());
        }
        
        foreach ($notification->getAttributes() as $attribute) {
            if ($attribute->getLabel() === 'target_discord_id') {
                $discordId = $attribute->getValue();
                break;
            }
        }
        
        if (!$discordId) {
            return;
        }
        
        $notificationData = $this->prepareNotificationData(
            $notification, 
            $notificationSettings, 
            $client
        );
        if ($notificationSettings['use_rich_embeds'] == 'on' || $notificationSettings['use_rich_embeds'] == 'yes') {
            $message = $notificationData;
        } else {
            $messageFormat = $notificationSettings['message_format'];
            $message = str_replace(
                ['{title}', '{message}', '{url}'],
                [$notification->getTitle(), $notification->getMessage(), $notification->getUrl()],
                $messageFormat
            );
            
            if ($notificationSettings['include_attributes'] == 'on' || $notificationSettings['include_attributes'] == 'yes') {
                $message .= "\n\n";
                foreach ($notification->getAttributes() as $attribute) {
                    $message .= "**" . $attribute->getLabel() . "**: " . $attribute->getValue() . "\n";
                }
            }
        }
        
        try {
            $ch = curl_init($apiUrl);
            $postData = [
                'discord_id' => $discordId,
                'message' => $message
            ];
            if ($notificationSettings['use_rich_embeds'] == 'on' || $notificationSettings['use_rich_embeds'] == 'yes') {
                $postData['use_embed'] = true;
                $postData['embed_data'] = $message;
                $postData['message'] = null;
            }
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            curl_close($ch);
            
            if ($response === false) {
                \logActivity("Discord Notification: Connection failed - {$curlError}");
                return true;
            }
            
            if ($httpCode < 200 || $httpCode >= 300) {
                \logActivity("Discord Notification: API Error - HTTP code {$httpCode}");
                return true;
            }
            
            \logActivity("Discord Notification: Successfully sent message to Discord ID {$discordId}");
            return true;
            
        } catch (\Exception $e) {
            \logActivity("Discord Notification Error: " . $e->getMessage());
            return true;
        }
    }

    private function prepareNotificationData($notification, $notificationSettings, $client)
    {
        $embedData = [
            'title' => $notification->getTitle(),
            'description' => $notification->getMessage(),
            'url' => $notification->getUrl(),
            'timestamp' => date('c'),
            'footer' => [
                'text' => 'WHMCS Notification System'
            ]
        ];

        if ($notificationSettings['priority_color_coding'] == 'on' || $notificationSettings['priority_color_coding'] == 'yes') {
            $embedData['color'] = $this->getPriorityColor($notification);
        } else {
            $embedData['color'] = 0x0099ff;
        }

        $fields = [];

        if ($notificationSettings['include_attributes'] == 'on' || $notificationSettings['include_attributes'] == 'yes') {
            foreach ($notification->getAttributes() as $attribute) {
                $label = $attribute->getLabel();
                
                if ($label === 'Priority' && stripos($notification->getTitle(), 'new support ticket') !== false) {
                    continue;
                }
                
                $value = $attribute->getValue();
                $url = $attribute->getUrl();
                $style = $attribute->getStyle();
                $icon = $attribute->getIcon();

                $formattedValue = $this->formatAttributeValue($value, $style, $url, $icon);

                $fields[] = [
                    'name' => $label,
                    'value' => $formattedValue,
                    'inline' => true
                ];
            }
        }

        if ($notificationSettings['include_client_info'] == 'on' || $notificationSettings['include_client_info'] == 'yes') {
            $clientFields = $this->getClientInfoFields($client);
            $fields = array_merge($fields, $clientFields);
        }

        $embedData['fields'] = $fields;

        return $embedData;
    }

    private function getPriorityColor($notification)
    {
        foreach ($notification->getAttributes() as $attribute) {
            if (strtolower($attribute->getLabel()) === 'priority') {
                $priority = strtolower($attribute->getValue());
                
                switch ($priority) {
                    case 'high':
                    case 'urgent':
                        return 0xff0000;
                    case 'medium':
                        return 0xff9900;
                    case 'low':
                        return 0x00ff00;
                }
            }
        }

        $title = strtolower($notification->getTitle());
        if (strpos($title, 'urgent') !== false || strpos($title, 'high') !== false) {
            return 0xff0000;
        } elseif (strpos($title, 'medium') !== false) {
            return 0xff9900;
        } elseif (strpos($title, 'low') !== false) {
            return 0x00ff00;
        }

        return 0x0099ff;
    }

    private function formatAttributeValue($value, $style, $url, $icon)
    {
        if (is_string($value) && (stripos($value, 'priority.gif') !== false || stripos($value, 'Priority') !== false)) {
            if (preg_match('/(High|Medium|Low)$/i', $value, $matches) || preg_match('/Priority[:\s]+(High|Medium|Low)/i', $value, $matches)) {
                $priority = strtolower($matches[1]);
                
                switch ($priority) {
                    case 'high':
                        return '🔴 High';
                    case 'medium':
                        return '🟠 Medium';
                    case 'low':
                        return '🟢 Low';
                    default:
                        return $matches[1];
                }
            }
            
            if (stripos($value, 'highpriority.gif') !== false) {
                return '🔴 High';
            } elseif (stripos($value, 'mediumpriority.gif') !== false) {
                return '🟠 Medium';
            } elseif (stripos($value, 'lowpriority.gif') !== false) {
                return '🟢 Low';
            }
        }
        
        $formatted = $value;

        if (!empty($icon)) {
            $formatted = $icon . ' ' . $formatted;
        }
        
        switch ($style) {
            case 'success':
                $formatted = '✅ ' . $formatted;
                break;
            case 'danger':
            case 'error':
                $formatted = '❌ ' . $formatted;
                break;
            case 'warning':
                $formatted = '⚠️ ' . $formatted;
                break;
            case 'info':
                $formatted = 'ℹ️ ' . $formatted;
                break;
        }

        if (!empty($url)) {
            $formatted = '[' . $formatted . '](' . $url . ')';
        }

        return $formatted;
    }

    private function getClientInfoFields($client)
    {
        $fields = [];

        if (!empty($client['firstname']) && !empty($client['lastname'])) {
            $fields[] = [
                'name' => '👤 Client',
                'value' => $client['firstname'] . ' ' . $client['lastname'],
                'inline' => true
            ];
        }

        if (!empty($client['email'])) {
            $fields[] = [
                'name' => '📧 Email',
                'value' => $client['email'],
                'inline' => true
            ];
        }

        if (!empty($client['companyname'])) {
            $fields[] = [
                'name' => '🏢 Company',
                'value' => $client['companyname'],
                'inline' => true
            ];
        }

        return $fields;
    }
}
