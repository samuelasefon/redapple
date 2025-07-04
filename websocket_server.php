<?php
// WebSocket implementation using Pusher service
// This file is not directly executed on shared hosting
// Install via: composer require pusher/pusher-php-server

/*
 * Shared Hosting WebSocket Solution
 * 
 * For cPanel shared hosting, it's recommended to use a third-party WebSocket service
 * such as Pusher (https://pusher.com) instead of trying to run your own WebSocket server.
 */

// This is the implementation using Pusher instead of self-hosted WebSockets
require 'vendor/autoload.php';

class PusherConfig {
    // The Pusher credentials are already set in the application
    const APP_ID = '1987344';
    const APP_KEY = '30cc8bee2f1452634a48';
    const APP_SECRET = '12fb4f20231798bc1ecd';
    const CLUSTER = 'mt1';
}

function sendWebSocketUpdate($attemptId, $status) {
    try {
        $options = [
            'cluster' => PusherConfig::CLUSTER,
            'useTLS' => true
        ];
        
        $pusher = new Pusher\Pusher(
            PusherConfig::APP_KEY,
            PusherConfig::APP_SECRET,
            PusherConfig::APP_ID,
            $options
        );
        
        // Trigger an event on a channel with the status update
        $data = [
            'type' => 'status_changed',
            'attemptId' => $attemptId,
            'status' => $status
        ];
        
        // Send to the specific channel for this attempt
        $result = $pusher->trigger("attempt-{$attemptId}", 'status-update', $data);
        
        // Log the result
        error_log("Pusher notification sent for attempt ID: $attemptId with status: $status. Result: " . json_encode($result));
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to send Pusher notification: " . $e->getMessage());
        return false;
    }
}
?>